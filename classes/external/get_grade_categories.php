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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Read a course's grade categories WITH their configuration — aggregation,
 * empty-grade handling, and the total item's grade type/scale/grade-to-pass.
 *
 * Fills a Moodle core gap: nothing reads category configuration (the grade
 * tree WS shows structure, not settings), so deployments couldn't be audited
 * — only re-asserted.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_grade_categories extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        ]);
    }

    /**
     * List the categories.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/gradelib.php');

        ['courseid' => $courseid] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid]
        );

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/grade:viewall', $coursecontext);

        $categories = [];
        foreach (\grade_category::fetch_all(['courseid' => $course->id]) ?: [] as $category) {
            $total = $category->load_grade_item();
            $categories[] = [
                'id' => (int) $category->id,
                'name' => $category->is_course_category()
                    ? get_string('coursegradecategory', 'grades')
                    : (string) $category->fullname,
                'parentid' => $category->parent !== null ? (int) $category->parent : 0,
                'iscoursecategory' => (bool) $category->is_course_category(),
                'aggregation' => (int) $category->aggregation,
                'aggregateonlygraded' => (int) $category->aggregateonlygraded,
                'itemcount' => (int) $DB->count_records('grade_items', ['categoryid' => $category->id]),
                'total' => [
                    'itemid' => (int) $total->id,
                    'gradetype' => (int) $total->gradetype,
                    'scaleid' => $total->scaleid !== null ? (int) $total->scaleid : 0,
                    'grademax' => (float) $total->grademax,
                    'gradepass' => (float) $total->gradepass,
                ],
            ];
        }

        return ['categories' => $categories];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categories' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Grade category id'),
                'name' => new external_value(PARAM_TEXT, 'Category name'),
                'parentid' => new external_value(PARAM_INT, 'Parent category id (0 = none/course level)'),
                'iscoursecategory' => new external_value(PARAM_BOOL, 'Whether this is the course-level root'),
                'aggregation' => new external_value(PARAM_INT,
                    'Aggregation (0 mean, 2 median, 4 min, 6 max, 8 mode, 10 weighted mean, 13 natural)'),
                'aggregateonlygraded' => new external_value(PARAM_INT, '1 excludes empty grades; 0 counts them as 0'),
                'itemcount' => new external_value(PARAM_INT, 'Grade items directly in the category'),
                'total' => new external_single_structure([
                    'itemid' => new external_value(PARAM_INT, 'Grade item id of the category total'),
                    'gradetype' => new external_value(PARAM_INT, '0 none, 1 value, 2 scale, 3 text'),
                    'scaleid' => new external_value(PARAM_INT, 'Scale id when gradetype=2 (0 = none)'),
                    'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade of the total'),
                    'gradepass' => new external_value(PARAM_FLOAT, 'Grade to pass of the total'),
                ]),
            ])),
        ]);
    }
}
