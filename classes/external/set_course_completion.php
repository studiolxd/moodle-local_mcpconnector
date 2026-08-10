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
 * Set a course's completion criteria: complete N activities (all tracked ones
 * by default) and optionally reach a passing grade.
 *
 * Fills a Moodle core gap: course completion criteria are form-only. Mirrors
 * course/completion.php's save sequence. WARNING (Moodle semantics): saving
 * REPLACES all existing criteria and resets users' course-completion progress
 * (completion_info->clear_criteria), exactly like saving the form — send the
 * full desired criteria set each time. Enables course completion tracking if
 * it was off.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_course_completion extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id'),
                'Activities required for completion; empty/omitted = ALL activities with completion tracking configured',
                VALUE_DEFAULT,
                []
            ),
            'gradepass' => new external_value(
                PARAM_FLOAT,
                'Also require reaching this course grade (omit for no grade criterion)',
                VALUE_DEFAULT,
                null
            ),
            'overallaggregation' => new external_value(
                PARAM_ALPHA,
                "Between criteria groups: 'all' (default) or 'any'",
                VALUE_DEFAULT,
                'all'
            ),
            'activityaggregation' => new external_value(
                PARAM_ALPHA,
                "Between the listed activities: 'all' (default) or 'any'",
                VALUE_DEFAULT,
                'all'
            ),
        ]);
    }

    /**
     * Replace the course completion criteria.
     *
     * @param int $courseid
     * @param array $cmids
     * @param float|null $gradepass
     * @param string $overallaggregation
     * @param string $activityaggregation
     * @return array
     */
    public static function execute(
        int $courseid,
        array $cmids = [],
        ?float $gradepass = null,
        string $overallaggregation = 'all',
        string $activityaggregation = 'all'
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        foreach (['self', 'date', 'unenrol', 'activity', 'duration', 'grade', 'role', 'course'] as $type) {
            require_once($CFG->dirroot . '/completion/criteria/completion_criteria_' . $type . '.php');
        }

        [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'gradepass' => $gradepass,
            'overallaggregation' => $overallaggregation,
            'activityaggregation' => $activityaggregation,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'gradepass' => $gradepass,
            'overallaggregation' => $overallaggregation,
            'activityaggregation' => $activityaggregation,
        ]);

        $aggmap = ['all' => COMPLETION_AGGREGATION_ALL, 'any' => COMPLETION_AGGREGATION_ANY];
        if (!isset($aggmap[$overallaggregation]) || !isset($aggmap[$activityaggregation])) {
            \local_mcpconnector\local\reject::because("aggregation must be 'all' or 'any'");
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:update', $coursecontext);

        if (empty($CFG->enablecompletion)) {
            throw new \moodle_exception(
                'completionnotenabled',
                'completion',
                '',
                null,
                'completion tracking is disabled site-wide (Site administration > Advanced features)'
            );
        }

        // Course-level completion tracking must be on for criteria to apply.
        if (empty($course->enablecompletion)) {
            update_course((object) ['id' => $course->id, 'enablecompletion' => 1]);
            $course = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        }

        // Resolve the activity set: explicit cmids, or every tracked activity.
        if ($cmids === []) {
            $cmids = array_keys($DB->get_records_select(
                'course_modules',
                'course = ? AND completion > 0 AND deletioninprogress = 0',
                [$course->id],
                'id',
                'id'
            ));
        } else {
            foreach ($cmids as $cmid) {
                $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', MUST_EXIST);
                if ((int) $cm->course !== (int) $course->id) {
                    \local_mcpconnector\local\reject::because("cmid {$cmid} is not in course {$course->id}");
                }
                if ((int) $cm->completion === COMPLETION_TRACKING_NONE) {
                    \local_mcpconnector\local\reject::because(
                        "cmid {$cmid} has no completion tracking configured — "
                        . 'set it first with local_mcpconnector_update_module'
                    );
                }
            }
        }
        if ($cmids === [] && $gradepass === null) {
            \local_mcpconnector\local\reject::because(
                'nothing to require: no tracked activities in the course and no gradepass given'
            );
        }

        // Mirror course/completion.php's save sequence (it clears ALL previous
        // criteria — and users' course-completion progress — before recreating).
        $completion = new \completion_info($course);
        $completion->clear_criteria(false);

        $data = new \stdClass();
        $data->id = $course->id;
        $data->criteria_activity = array_fill_keys($cmids, 1);
        if ($gradepass !== null) {
            $data->criteria_grade = 1;
            $data->criteria_grade_value = $gradepass;
        }

        // Core declares this global in uppercase (lib/completionlib.php), so the
        // naming sniff has to be waived rather than the variable renamed.
        // phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        // phpcs:disable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
        global $COMPLETION_CRITERIA_TYPES;
        foreach ($COMPLETION_CRITERIA_TYPES as $type) {
            // phpcs:enable moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
            // phpcs:enable moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
            $class = 'completion_criteria_' . $type;
            $criterion = new $class();
            $criterion->update_config($data);
        }

        $aggdata = ['course' => $course->id, 'criteriatype' => null];
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($aggmap[$overallaggregation]);
        $aggregation->save();

        foreach (
            [COMPLETION_CRITERIA_TYPE_ACTIVITY => $aggmap[$activityaggregation],
                COMPLETION_CRITERIA_TYPE_COURSE => COMPLETION_AGGREGATION_ALL,
                COMPLETION_CRITERIA_TYPE_ROLE => COMPLETION_AGGREGATION_ALL] as $criteriatype => $method
        ) {
            $aggdata['criteriatype'] = $criteriatype;
            $aggregation = new \completion_aggregation($aggdata);
            $aggregation->setMethod($method);
            $aggregation->save();
        }

        \core\event\course_completion_updated::create([
            'courseid' => $course->id,
            'context' => $coursecontext,
        ])->trigger();

        return [
            'activities' => count($cmids),
            'gradecriterion' => $gradepass !== null,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_value(PARAM_INT, 'Activities now required for course completion'),
            'gradecriterion' => new external_value(PARAM_BOOL, 'Whether a passing-grade criterion was set'),
        ]);
    }
}
