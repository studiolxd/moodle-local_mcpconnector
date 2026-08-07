<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mcpconnector\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Restore a .mbz (fetched from an HTTPS URL — the transfer bucket fileUrl or
 * any public link) into a NEW course in a category. Core has NO webservice
 * for restore. Same engine as the restore UI (restore_controller,
 * MODE_GENERAL), prechecked before executing so a broken archive never leaves
 * a half-restored course behind.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_course extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fileurl' => new external_value(PARAM_RAW,
                'HTTPS URL of the .mbz (the fileUrl from backup_course, or any public link)', VALUE_REQUIRED),
            'categoryid' => new external_value(PARAM_INT,
                'Course category to create the new course in', VALUE_REQUIRED),
            'fullname' => new external_value(PARAM_TEXT,
                'New course full name (default: the one inside the backup)', VALUE_DEFAULT, ''),
            'shortname' => new external_value(PARAM_TEXT,
                'New course short name (default: derived from the backup, deduplicated)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Fetch the archive and restore it.
     *
     * @param string $fileurl
     * @param int $categoryid
     * @param string $fullname
     * @param string $shortname
     * @return array
     */
    public static function execute(
        string $fileurl,
        int $categoryid,
        string $fullname = '',
        string $shortname = ''
    ): array {
        global $DB;

        [
            'fileurl' => $fileurl,
            'categoryid' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
        ] = self::validate_parameters(self::execute_parameters(), [
            'fileurl' => $fileurl,
            'categoryid' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
        ]);

        $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
        $catcontext = \context_coursecat::instance($category->id);

        self::validate_context($catcontext);
        require_capability('moodle/course:create', $catcontext);
        require_capability('moodle/restore:restorecourse', $catcontext);

        $mbzpath = \local_mcpconnector\local\url_file::fetch_to_temp($fileurl);
        try {
            $courseid = self::restore_mbz($mbzpath, $categoryid, $fullname, $shortname);
        } finally {
            @unlink($mbzpath);
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', MUST_EXIST);
        return [
            'courseid' => (int) $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
        ];
    }

    /**
     * Restores a local .mbz into a new course. Separated from the fetch so the
     * whole restore path is testable without network.
     *
     * @param string $mbzpath Local path of the .mbz archive.
     * @param int $categoryid Target category.
     * @param string $fullname Override full name ('' = keep the backup's).
     * @param string $shortname Override short name ('' = derive + deduplicate).
     * @return int The new course id.
     */
    public static function restore_mbz(
        string $mbzpath,
        int $categoryid,
        string $fullname = '',
        string $shortname = ''
    ): int {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        // A whole-course restore can legitimately take minutes.
        \core_php_time_limit::raise(300);

        // Unpack into the backup temp dir, validating it IS a Moodle backup —
        // a corrupt archive must fail here, before any course is created.
        $backupdir = 'mcpconnector_restore_' . time() . '_' . random_int(1000, 9999);
        $tempdir = make_backup_temp_directory($backupdir);
        $packer = get_file_packer('application/vnd.moodle.backup');
        if (!$packer->extract_to_pathname($mbzpath, $tempdir)) {
            \local_mcpconnector\local\reject::because('the file is not a valid .mbz archive');
        }
        if (!file_exists($tempdir . '/moodle_backup.xml')) {
            \local_mcpconnector\local\reject::because('the archive is not a Moodle course backup (no moodle_backup.xml)');
        }

        // Names: use the backup's own when not overridden, deduplicating the
        // shortname (restore aborts on a duplicate).
        [$backupfullname, $backupshortname] = \restore_dbops::calculate_course_names(
            0,
            get_string('restoringcourse', 'backup'),
            get_string('restoringcourseshortname', 'backup')
        );
        $newcourseid = \restore_dbops::create_new_course(
            $fullname !== '' ? $fullname : $backupfullname,
            $shortname !== '' ? $shortname : $backupshortname,
            $categoryid
        );

        $rc = new \restore_controller(
            $backupdir,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        try {
            if (!$rc->execute_precheck()) {
                $results = $rc->get_precheck_results();
                $errors = implode('; ', array_map('strval', $results['errors'] ?? ['precheck failed']));
                // The empty shell must not survive a failed precheck.
                delete_course($newcourseid, false);
                \local_mcpconnector\local\reject::because('restore precheck failed: ' . $errors);
            }
            $rc->execute_plan();
        } finally {
            $rc->destroy();
        }

        // The restore plan restores the ORIGINAL names from the backup; apply
        // the caller's overrides after the fact (same as the restore UI).
        if ($fullname !== '' || $shortname !== '') {
            global $DB;
            $update = ['id' => $newcourseid, 'timemodified' => time()];
            if ($fullname !== '') {
                $update['fullname'] = $fullname;
            }
            if ($shortname !== '') {
                $update['shortname'] = $shortname;
            }
            $DB->update_record('course', (object) $update);
        }
        rebuild_course_cache($newcourseid, true);

        return $newcourseid;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'The new course id'),
            'fullname' => new external_value(PARAM_TEXT, 'The new course full name'),
            'shortname' => new external_value(PARAM_TEXT, 'The new course short name'),
        ]);
    }
}
