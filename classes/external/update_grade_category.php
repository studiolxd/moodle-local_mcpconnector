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
 * Update a grade category: rename, aggregation, empty-grade handling, and its
 * TOTAL item's scale / grade-to-pass.
 *
 * Fills a Moodle core gap: core_grades_create_gradecategories only CREATES,
 * and its options can't set a scale on the category total (scaleid isn't a
 * declared option — the WS's own scale validation is unreachable). Setting
 * the total to a scale (e.g. Apto/No Apto) with min aggregation turns the
 * category into an automatic all-must-pass verdict.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_grade_category extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'categoryid' => new external_value(PARAM_INT, 'Grade category id', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'New category name', VALUE_DEFAULT, null),
            'aggregation' => new external_value(PARAM_INT,
                'Aggregation method (0 mean, 2 median, 4 min, 6 max, 8 mode, 10 weighted mean, 13 natural)',
                VALUE_DEFAULT, null),
            'aggregateonlygraded' => new external_value(PARAM_INT,
                'Exclude empty grades: 1 yes, 0 no (0 = ungraded items count as 0)', VALUE_DEFAULT, null),
            'scaleid' => new external_value(PARAM_INT,
                'Show the CATEGORY TOTAL with this scale (sets gradetype to scale)', VALUE_DEFAULT, null),
            'gradepass' => new external_value(PARAM_FLOAT,
                'Grade to pass on the category total (with a scale: the 1-based value, e.g. 2)',
                VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Update the category.
     *
     * @param int $courseid
     * @param int $categoryid
     * @param string|null $name
     * @param int|null $aggregation
     * @param int|null $aggregateonlygraded
     * @param int|null $scaleid
     * @param float|null $gradepass
     * @return array
     */
    public static function execute(
        int $courseid,
        int $categoryid,
        ?string $name = null,
        ?int $aggregation = null,
        ?int $aggregateonlygraded = null,
        ?int $scaleid = null,
        ?float $gradepass = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/gradelib.php');

        [
            'courseid' => $courseid,
            'categoryid' => $categoryid,
            'name' => $name,
            'aggregation' => $aggregation,
            'aggregateonlygraded' => $aggregateonlygraded,
            'scaleid' => $scaleid,
            'gradepass' => $gradepass,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'categoryid' => $categoryid,
            'name' => $name,
            'aggregation' => $aggregation,
            'aggregateonlygraded' => $aggregateonlygraded,
            'scaleid' => $scaleid,
            'gradepass' => $gradepass,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/grade:manage', $coursecontext);

        $category = \grade_category::fetch(['id' => $categoryid, 'courseid' => $course->id]);
        if (!$category) {
            \local_mcpconnector\local\reject::because("grade category {$categoryid} not found in course {$course->id}");
        }

        if ($name !== null && trim($name) !== '') {
            $category->fullname = trim($name);
        }
        if ($aggregation !== null) {
            $category->aggregation = $aggregation;
        }
        if ($aggregateonlygraded !== null) {
            $category->aggregateonlygraded = $aggregateonlygraded ? 1 : 0;
        }
        $category->update('external');

        if ($scaleid !== null || $gradepass !== null) {
            $item = $category->load_grade_item();
            if ($scaleid !== null) {
                $DB->get_record('scale', ['id' => $scaleid], 'id', MUST_EXIST);
                $item->gradetype = GRADE_TYPE_SCALE;
                $item->scaleid = $scaleid;
                // A scale grades 1..N; keep the range consistent.
                $scale = \grade_scale::fetch(['id' => $scaleid]);
                $scale->load_items();
                $item->grademin = 1;
                $item->grademax = count($scale->scale_items);
            }
            if ($gradepass !== null) {
                $item->gradepass = $gradepass;
            }
            $item->update('external');
        }

        return ['success' => true, 'categoryid' => (int) $category->id];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was applied'),
            'categoryid' => new external_value(PARAM_INT, 'Grade category id'),
        ]);
    }
}
