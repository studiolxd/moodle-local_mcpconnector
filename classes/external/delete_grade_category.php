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
 * Delete a grade category. Its items and child categories move to the parent
 * (standard gradebook semantics — grades are NOT lost).
 *
 * Fills a Moodle core gap: no webservice deletes grade categories. The
 * course-level root category is refused (grade_category::delete on it would
 * wipe the whole gradebook tree).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delete_grade_category extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'categoryid' => new external_value(PARAM_INT, 'Grade category id to delete', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete the category.
     *
     * @param int $courseid
     * @param int $categoryid
     * @return array
     */
    public static function execute(int $courseid, int $categoryid): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/gradelib.php');

        [
            'courseid' => $courseid,
            'categoryid' => $categoryid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'categoryid' => $categoryid,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('moodle/grade:manage', $coursecontext);

        $category = \grade_category::fetch(['id' => $categoryid, 'courseid' => $course->id]);
        if (!$category) {
            \local_mcpconnector\local\reject::because("grade category {$categoryid} not found in course {$course->id}");
        }
        if ($category->is_course_category()) {
            \local_mcpconnector\local\reject::because('refusing to delete the course-level category (it would wipe the whole gradebook tree)');
        }

        $category->delete('external');

        return ['success' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the category was deleted'),
        ]);
    }
}
