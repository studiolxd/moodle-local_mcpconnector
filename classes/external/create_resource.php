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
use local_mcpconnector\local\url_file;

/**
 * Create a File resource (mod_resource) downloading the file from an HTTPS URL.
 *
 * The general instance of the plugin's files-by-URL model (see
 * local_mcpconnector\local\url_file): MCP tool arguments cannot carry binary
 * payloads, so the Moodle server downloads the file itself. Covers "add this
 * PDF/DOCX/image/video to the course" — any file type; Moodle picks icon and
 * viewer from the stored filename's extension, hence the optional `filename`
 * override for URLs whose path hides it (Drive, signed URLs).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class create_resource extends external_api {
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
            'fileurl' => new external_value(PARAM_URL, 'HTTPS URL of the file', VALUE_REQUIRED),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, 1),
            'filename' => new external_value(
                PARAM_FILE,
                'Stored filename (extension drives icon/viewer); defaults to the URL path basename',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Create the resource.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $fileurl
     * @param string $intro
     * @param int $visible
     * @param string|null $filename
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        string $fileurl,
        string $intro = '',
        int $visible = 1,
        ?string $filename = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'fileurl' => $fileurl,
            'intro' => $intro,
            'visible' => $visible,
            'filename' => $filename,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'fileurl' => $fileurl,
            'intro' => $intro,
            'visible' => $visible,
            'filename' => $filename,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $draftitemid = url_file::fetch_to_draft($fileurl, $filename !== '' ? $filename : null, 'file.bin');

        // Site defaults for mod_resource — the same set the settings form
        // applies. resource_set_display_options reads display/popup*/print*
        // unconditionally on the paths their display mode selects.
        $cfg = get_config('resource');
        $display = (int) ($cfg->display ?? \RESOURCELIB_DISPLAY_AUTO);
        $created = create_module::add($course, 'resource', $sectionnum, $name, $intro, $visible, [
            'files' => $draftitemid,
            'display' => $display,
            'popupwidth' => (int) ($cfg->popupwidth ?? 620),
            'popupheight' => (int) ($cfg->popupheight ?? 450),
            'printintro' => (int) ($cfg->printintro ?? 1),
            'showsize' => (int) ($cfg->showsize ?? 0),
            'showtype' => (int) ($cfg->showtype ?? 0),
            'showdate' => (int) ($cfg->showdate ?? 0),
            'filterfiles' => (int) ($cfg->filterfiles ?? 0),
            'uploaded' => 0,
        ]);

        return [
            'cmid' => (int) $created->coursemodule,
            'instanceid' => (int) $created->instance,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'New course module id'),
            'instanceid' => new external_value(PARAM_INT, 'New resource instance id'),
        ]);
    }
}
