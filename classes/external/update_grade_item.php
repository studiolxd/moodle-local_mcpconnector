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
 * Update a gradebook item: grade to pass, weight, visibility, category.
 *
 * Fills a Moodle core gap: no webservice edits grade items (core only creates
 * grade CATEGORIES). Find item ids with core_grades_get_gradeitems. Weight
 * follows the gradebook's Natural aggregation semantics: a 0-100 percentage
 * stored as aggregationcoef2 (/100) with weightoverride set, exactly like the
 * grader setup form (grade/edit/tree/item.php).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_grade_item extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'itemid' => new external_value(
                PARAM_INT,
                'Grade item id (from core_grades_get_gradeitems)',
                VALUE_REQUIRED
            ),
            'grademax' => new external_value(
                PARAM_FLOAT,
                'Maximum grade. Module items: only quiz (updates the quiz "Maximum grade" '
                . 'setting and rescales attempts); manual/category items: set directly.',
                VALUE_DEFAULT,
                null
            ),
            'gradepass' => new external_value(PARAM_FLOAT, 'Grade to pass (0 = none)', VALUE_DEFAULT, null),
            'weight' => new external_value(
                PARAM_FLOAT,
                'Weight as 0-100 percentage (Natural aggregation; sets the override flag)',
                VALUE_DEFAULT,
                null
            ),
            'hidden' => new external_value(PARAM_INT, 'Hide the item: 1 hidden, 0 visible', VALUE_DEFAULT, null),
            'categoryid' => new external_value(
                PARAM_INT,
                'Move the item into this grade category id',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Update the grade item.
     *
     * @param int $courseid
     * @param int $itemid
     * @param float|null $grademax
     * @param float|null $gradepass
     * @param float|null $weight
     * @param int|null $hidden
     * @param int|null $categoryid
     * @return array
     */
    public static function execute(
        int $courseid,
        int $itemid,
        ?float $grademax = null,
        ?float $gradepass = null,
        ?float $weight = null,
        ?int $hidden = null,
        ?int $categoryid = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/gradelib.php');

        [
            'courseid' => $courseid,
            'itemid' => $itemid,
            'grademax' => $grademax,
            'gradepass' => $gradepass,
            'weight' => $weight,
            'hidden' => $hidden,
            'categoryid' => $categoryid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'itemid' => $itemid,
            'grademax' => $grademax,
            'gradepass' => $gradepass,
            'weight' => $weight,
            'hidden' => $hidden,
            'categoryid' => $categoryid,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/grade:manage', $coursecontext);

        $gradeitem = \grade_item::fetch(['id' => $itemid, 'courseid' => $course->id]);
        if (!$gradeitem) {
            \local_mcpconnector\local\reject::because("grade item {$itemid} not found in course {$course->id}");
        }

        if ($grademax !== null) {
            $gradeitem = self::apply_grademax($gradeitem, $grademax, $course->id);
        }

        if ($gradepass !== null) {
            $gradeitem->gradepass = $gradepass;
        }
        if ($weight !== null) {
            if ($weight < 0 || $weight > 100) {
                \local_mcpconnector\local\reject::because('weight must be a 0-100 percentage');
            }
            // The grader setup form shows the percentage and stores /100.
            $gradeitem->aggregationcoef2 = $weight / 100;
            $gradeitem->weightoverride = 1;
        }
        $gradeitem->update('external');

        if ($categoryid !== null) {
            $category = \grade_category::fetch(['id' => $categoryid, 'courseid' => $course->id]);
            if (!$category) {
                \local_mcpconnector\local\reject::because("grade category {$categoryid} not found in course {$course->id}");
            }
            $gradeitem->set_parent($categoryid);
        }
        if ($hidden !== null) {
            $gradeitem->set_hidden($hidden ? 1 : 0, true);
        }

        // A grademax change can strand an existing gradepass above the new
        // maximum (e.g. pass 10 over a new max of 10 = 100%). The caller
        // adjusts gradepass deliberately, so warn instead of auto-fixing.
        $warning = '';
        $final = \grade_item::fetch(['id' => $itemid, 'courseid' => $course->id]);
        if ($final && (float) $final->gradepass > (float) $final->grademax) {
            $warning = "gradepass ({$final->gradepass}) is above grademax ({$final->grademax}); "
                . 'set a coherent gradepass with this same tool';
        }

        return ['success' => true, 'itemid' => (int) $gradeitem->id, 'warning' => $warning];
    }

    /**
     * Applies a new maximum grade using the authoritative path per item type.
     *
     * For a module item the grade_item's grademax only MIRRORS the activity's
     * own grade setting — writing it directly desyncs and Moodle overwrites it
     * on the next recalculation. mod_quiz's grade_calculator updates the quiz
     * setting, rescales attempts and feedback boundaries, and pushes the
     * grade_item itself (transactional, idempotent). Manual/category/course
     * items have no backing activity, so the direct write IS the setting.
     *
     * @param \grade_item $gradeitem
     * @param float $grademax
     * @param int $courseid
     * @return \grade_item the fresh item after the change
     */
    private static function apply_grademax(\grade_item $gradeitem, float $grademax, int $courseid): \grade_item {
        if ($grademax <= 0) {
            \local_mcpconnector\local\reject::because('grademax must be greater than 0');
        }

        if ($gradeitem->itemtype === 'mod') {
            if ($gradeitem->itemmodule !== 'quiz') {
                \local_mcpconnector\local\reject::because(
                    "grademax is supported for quiz module items only (this item belongs to "
                    . "mod_{$gradeitem->itemmodule}); change that activity's own grade setting instead"
                );
            }
            if (!class_exists('\mod_quiz\quiz_settings')) {
                \local_mcpconnector\local\reject::because('changing the quiz maximum grade requires Moodle 4.2 or later');
            }
            $quizsettings = \mod_quiz\quiz_settings::create((int) $gradeitem->iteminstance);
            $quizsettings->get_grade_calculator()->update_quiz_maximum_grade($grademax);
            // The calculator updated the grade_item row — work with a fresh copy.
            return \grade_item::fetch(['id' => $gradeitem->id, 'courseid' => $courseid]);
        }

        if (!in_array($gradeitem->itemtype, ['manual', 'category', 'course'], true)) {
            \local_mcpconnector\local\reject::because(
                "grademax is not supported on '{$gradeitem->itemtype}' items"
            );
        }
        $gradeitem->grademax = $grademax;
        return $gradeitem;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was applied'),
            'itemid' => new external_value(PARAM_INT, 'Grade item id'),
            'warning' => new external_value(
                PARAM_TEXT,
                'Non-empty when the item ended in a state worth reviewing (e.g. gradepass above grademax)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }
}
