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
 * Back a course up to a .mbz and upload it to an HTTPS PUT URL (the panel's
 * transfer bucket slot). Core has NO webservice for backup — this is the
 * "clone/migrate courses from the chat" differential. Course content only by
 * default (no user data), same engine as the backup UI (backup_controller,
 * MODE_GENERAL), so the resulting .mbz restores anywhere.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_course extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id to back up', VALUE_REQUIRED),
            'uploadurl' => new external_value(
                PARAM_RAW,
                'Presigned HTTPS PUT URL for the .mbz (moodle_request_file_upload mints one)',
                VALUE_REQUIRED
            ),
            'includeusers' => new external_value(
                PARAM_INT,
                'Include user data (enrolments, completion, grades): 1 yes, 0 no (default)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Run the backup and upload it.
     *
     * @param int $courseid
     * @param string $uploadurl
     * @param int $includeusers
     * @return array
     */
    public static function execute(int $courseid, string $uploadurl, int $includeusers = 0): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        [
            'courseid' => $courseid,
            'uploadurl' => $uploadurl,
            'includeusers' => $includeusers,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'uploadurl' => $uploadurl,
            'includeusers' => $includeusers,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/backup:backupcourse', $coursecontext);
        if ($includeusers) {
            require_capability('moodle/backup:userinfo', $coursecontext);
        }

        if (stripos($uploadurl, 'https://') !== 0) {
            \local_mcpconnector\local\reject::because('uploadurl must be an https:// URL '
                . '(mint one with moodle_request_file_upload)');
        }

        [$mbzpath, $filename] = self::create_mbz($course->id, (bool) $includeusers);
        try {
            $size = (int) filesize($mbzpath);
            // The transfer bucket is the delivery channel, not Moodle's disk.
            \local_mcpconnector\local\url_file::put_from_path($uploadurl, $mbzpath);
            return ['success' => true, 'filename' => $filename, 'size' => $size];
        } finally {
            @unlink($mbzpath);
        }
    }

    /**
     * Runs the backup engine and stages the .mbz to a temp path. Separated
     * from the upload so the whole backup path is testable without network.
     * The CALLER must unlink the returned path.
     *
     * @param int $courseid
     * @param bool $includeusers Include user data.
     * @return array{0:string,1:string} [temp path, backup filename]
     */
    public static function create_mbz(int $courseid, bool $includeusers): array {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        // A whole-course backup can legitimately take minutes.
        \core_php_time_limit::raise(300);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            $plan = $bc->get_plan();
            // Content-only by default: user data multiplies the size and is
            // rarely wanted for cloning/migration.
            foreach (['users', 'role_assignments', 'comments', 'userscompletion', 'logs', 'grade_histories'] as $name) {
                if ($plan->setting_exists($name)) {
                    $plan->get_setting($name)->set_value($includeusers ? 1 : 0);
                }
            }
            if ($plan->setting_exists('anonymize')) {
                $plan->get_setting('anonymize')->set_value(0);
            }
            $filename = \backup_plan_dbops::get_default_backup_filename(
                \backup::FORMAT_MOODLE,
                \backup::TYPE_1COURSE,
                $courseid,
                $includeusers,
                false
            );
            if ($plan->setting_exists('filename')) {
                $plan->get_setting('filename')->set_value($filename);
            }

            $bc->execute_plan();
            $results = $bc->get_results();
            $file = $results['backup_destination'] ?? null;
            if (!$file) {
                \local_mcpconnector\local\reject::because('the backup produced no file');
            }

            $mbzpath = tempnam(make_temp_directory('mcpconnector'), 'mbz');
            $file->copy_content_to($mbzpath);
            $file->delete();
            return [$mbzpath, $filename];
        } finally {
            $bc->destroy();
        }
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the backup was uploaded'),
            'filename' => new external_value(PARAM_FILE, 'Backup filename (.mbz)'),
            'size' => new external_value(PARAM_INT, 'Size in bytes'),
        ]);
    }
}
