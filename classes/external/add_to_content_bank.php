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
 * Upload a file (typically .h5p) into the content bank from an HTTPS URL —
 * core's content bank has no write webservice. Course context when courseid
 * is given, else site-wide.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_to_content_bank extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fileurl' => new external_value(PARAM_URL, 'HTTPS URL of the file (e.g. an .h5p)', VALUE_REQUIRED),
            'courseid' => new external_value(PARAM_INT,
                'Course whose content bank receives it (omit = site content bank)', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT,
                'Content name (default: the file name)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Fetch the file and add it to the content bank.
     *
     * @param string $fileurl
     * @param int $courseid
     * @param string $name
     * @return array
     */
    public static function execute(string $fileurl, int $courseid = 0, string $name = ''): array {
        global $DB, $USER;

        [
            'fileurl' => $fileurl,
            'courseid' => $courseid,
            'name' => $name,
        ] = self::validate_parameters(self::execute_parameters(), [
            'fileurl' => $fileurl,
            'courseid' => $courseid,
            'name' => $name,
        ]);

        if ($courseid > 0) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);
            $context = \context_course::instance($course->id);
        } else {
            $context = \context_system::instance();
        }

        self::validate_context($context);
        require_capability('moodle/contentbank:upload', $context);

        $filename = $name !== ''
            ? clean_param($name, PARAM_FILE)
            : (clean_param(basename(parse_url($fileurl, PHP_URL_PATH) ?? ''), PARAM_FILE) ?: 'content.h5p');

        $draftitemid = \local_mcpconnector\local\url_file::fetch_to_draft($fileurl, $filename, 'content.h5p');
        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files(
            $usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        $file = reset($files);
        if (!$file) {
            \local_mcpconnector\local\reject::because('the download produced no file');
        }

        $cb = new \core_contentbank\contentbank();
        // Rejects unsupported extensions (only content types with an active
        // contenttype plugin, e.g. .h5p, are accepted).
        $content = $cb->create_content_from_file($context, (int) $USER->id, $file);
        if (!$content) {
            \local_mcpconnector\local\reject::because(
                'the content bank did not accept the file (unsupported type?)'
            );
        }

        return [
            'contentid' => (int) $content->get_id(),
            'name' => $content->get_name(),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'contentid' => new external_value(PARAM_INT, 'Content bank item id'),
            'name' => new external_value(PARAM_TEXT, 'Stored content name'),
        ]);
    }
}
