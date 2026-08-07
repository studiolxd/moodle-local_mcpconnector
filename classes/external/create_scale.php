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
 * Create a grading scale in a course (e.g. "No Apto, Apto").
 *
 * Fills a Moodle core gap: no webservice manages scales. Idempotent by
 * (course, name): if a scale with the same name already exists in the course
 * it is returned instead of duplicated. To grade an activity with the scale,
 * set its grade to MINUS the scale id (e.g. assign grade = -scaleid).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class create_scale extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course the scale belongs to', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Scale name (e.g. "Apto / No Apto")', VALUE_REQUIRED),
            'scale' => new external_value(PARAM_TEXT,
                'Comma-separated values, WORST FIRST (e.g. "No Apto,Apto")', VALUE_REQUIRED),
            'description' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create (or find) the scale.
     *
     * @param int $courseid
     * @param string $name
     * @param string $scale
     * @param string $description
     * @return array
     */
    public static function execute(int $courseid, string $name, string $scale, string $description = ''): array {
        global $CFG, $DB, $USER;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_scale.php');

        [
            'courseid' => $courseid,
            'name' => $name,
            'scale' => $scale,
            'description' => $description,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'scale' => $scale,
            'description' => $description,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:managescales', $coursecontext);

        $items = array_map('trim', explode(',', $scale));
        if (count(array_filter($items, 'strlen')) < 2) {
            \local_mcpconnector\local\reject::because('scale must have at least 2 comma-separated values (worst first)');
        }

        // Idempotent: reuse an existing same-named scale in this course.
        if ($existing = $DB->get_record('scale', ['courseid' => $course->id, 'name' => $name])) {
            return [
                'scaleid' => (int) $existing->id,
                'created' => false,
                'usage' => 'Set an activity grade to -' . $existing->id . ' to grade with this scale.',
            ];
        }

        $gradescale = new \grade_scale();
        $data = (object) [
            'name' => $name,
            'scale' => implode(',', $items),
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'userid' => $USER->id,
        ];
        \grade_scale::set_properties($gradescale, $data);
        $gradescale->courseid = $course->id;
        $gradescale->insert();

        return [
            'scaleid' => (int) $gradescale->id,
            'created' => true,
            'usage' => 'Set an activity grade to -' . $gradescale->id . ' to grade with this scale.',
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'scaleid' => new external_value(PARAM_INT, 'Scale id'),
            'created' => new external_value(PARAM_BOOL, 'False if a same-named scale already existed and was reused'),
            'usage' => new external_value(PARAM_TEXT, 'How to apply the scale to an activity'),
        ]);
    }
}
