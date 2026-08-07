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
 * Create an H5P activity (mod_h5pactivity) downloading its .h5p package from
 * an HTTPS URL — core's mobile-oriented webservices can play H5P but nothing
 * can CREATE the activity. Same files-by-URL model as create_scorm.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class create_h5p extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Section NUMBER (0 = General)', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Activity name', VALUE_REQUIRED),
            'packageurl' => new external_value(PARAM_URL, 'HTTPS URL of the .h5p package', VALUE_REQUIRED),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Create the H5P activity.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $packageurl
     * @param string $intro
     * @param int $visible
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        string $packageurl,
        string $intro = '',
        int $visible = 1
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');

        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'packageurl' => $packageurl,
            'intro' => $intro,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'packageurl' => $packageurl,
            'intro' => $intro,
            'visible' => $visible,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        // An .h5p is a zip whose manifest is h5p.json (cheap sanity check —
        // core validates the package fully when it deploys it).
        $draftitemid = \local_mcpconnector\local\url_file::fetch_to_draft(
            $packageurl,
            self::package_filename($packageurl),
            'package.h5p',
            function (string $tmpfile): void {
                $zip = new \ZipArchive();
                $ish5p = $zip->open($tmpfile) === true
                    && $zip->locateName('h5p.json', \ZipArchive::FL_NODIR) !== false;
                $zip->close();
                if (!$ish5p) {
                    \local_mcpconnector\local\reject::because('the file is not an H5P package (no h5p.json in the zip)');
                }
            }
        );

        // Same defaults the generator/mod_form apply (tracking on, highest
        // attempt grades, review on completion, site display options).
        $factory = new \core_h5p\factory();
        $core = $factory->get_core();
        $displayoptions = \core_h5p\helper::get_display_options(
            $core,
            \core_h5p\helper::decode_display_options($core)
        );
        $created = create_module::add($course, 'h5pactivity', $sectionnum, $name, $intro, $visible, [
            'packagefile' => $draftitemid,
            'grade' => 100,
            'displayoptions' => $displayoptions,
            'enabletracking' => 1,
            'grademethod' => \mod_h5pactivity\local\manager::GRADEHIGHESTATTEMPT,
            'reviewmode' => \mod_h5pactivity\local\manager::REVIEWCOMPLETION,
        ]);

        return [
            'cmid' => (int) $created->coursemodule,
            'instanceid' => (int) $created->instance,
        ];
    }

    /**
     * Filename for the staged package, derived from the URL (fallback safe name).
     *
     * @param string $url
     * @return string
     */
    private static function package_filename(string $url): string {
        $name = basename(parse_url($url, PHP_URL_PATH) ?? '');
        $name = clean_param($name, PARAM_FILE);
        if ($name === '' || strtolower(substr($name, -4)) !== '.h5p') {
            $name = 'package.h5p';
        }
        return $name;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'instanceid' => new external_value(PARAM_INT, 'h5pactivity instance id'),
        ]);
    }
}
