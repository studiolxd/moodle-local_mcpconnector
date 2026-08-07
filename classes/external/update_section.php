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
 * Update a course section's name, summary and/or visibility.
 *
 * Fills a Moodle core gap: of a course's three text surfaces (a label's body,
 * the course summary and a section's summary), the section summary is the
 * only one with no core or local_mcpconnector webservice able to write it —
 * core_courseformat_update_course only moves/hides/highlights sections.
 * Uses course_update_section(), the same core helper the course-editing AJAX
 * calls (cache rebuild, event trigger, and — for visible — the cascade to the
 * section's modules, all included).
 *
 * Verified against Moodle 5.2 core (course/lib.php::course_update_section —
 * see update_section_test.php, incl. the hide-cascades-to-modules case).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_section extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Section NUMBER (0 = General)', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT,
                "New section name. Empty string restores the course format's default "
                . "('Topic N'/'Week N').", VALUE_DEFAULT, null),
            'summary' => new external_value(PARAM_RAW, 'New section summary/description, as HTML',
                VALUE_DEFAULT, null),
            'summaryformat' => new external_value(PARAM_INT, 'Format of summary (1 = HTML, default)',
                VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Update the section.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string|null $name
     * @param string|null $summary
     * @param int|null $summaryformat
     * @param int|null $visible
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        ?string $name = null,
        ?string $summary = null,
        ?int $summaryformat = null,
        ?int $visible = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => $summaryformat,
            'visible' => $visible,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'summary' => $summary,
            'summaryformat' => $summaryformat,
            'visible' => $visible,
        ]);

        if ($name === null && $summary === null && $summaryformat === null && $visible === null) {
            \local_mcpconnector\local\reject::because(
                'nothing to update — pass at least one of name, summary, summaryformat or visible'
            );
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:update', $coursecontext);

        $section = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => $sectionnum],
            '*',
            MUST_EXIST
        );

        $data = [];
        if ($name !== null) {
            $data['name'] = $name;
        }
        if ($summary !== null || $summaryformat !== null) {
            $data['summary'] = $summary ?? $section->summary;
            $data['summaryformat'] = $summaryformat ?? $section->summaryformat ?? FORMAT_HTML;
        }
        if ($visible !== null) {
            $data['visible'] = $visible ? 1 : 0;
        }

        course_update_section($course, $section, $data);

        return ['success' => true, 'courseid' => $course->id, 'sectionnum' => $sectionnum];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was applied'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number'),
        ]);
    }
}
