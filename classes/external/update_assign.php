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
 * Configure an assignment (mod_assign): dates, grading (points or scale),
 * attempts, and submission types.
 *
 * Fills a Moodle core gap: mod_assign webservices are submission/grading
 * only — nothing configures the activity. Uses get_moduleinfo_data +
 * update_moduleinfo. CRITICAL trap handled here: assign::update_instance
 * DISABLES every submission/feedback plugin whose {subtype}_{type}_enabled
 * field is absent from the form data (mod/assign/locallib.php,
 * update_plugin_instance) — so the current enabled-state and settings of ALL
 * plugins are seeded before applying the requested changes.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_assign extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the assignment', VALUE_REQUIRED),
            'duedate' => new external_value(PARAM_INT, 'Due date (unix timestamp; 0 = none)', VALUE_DEFAULT, null),
            'allowsubmissionsfromdate' => new external_value(
                PARAM_INT,
                'Allow submissions from (timestamp; 0 = always)',
                VALUE_DEFAULT,
                null
            ),
            'cutoffdate' => new external_value(PARAM_INT, 'Cut-off date (timestamp; 0 = none)', VALUE_DEFAULT, null),
            'gradingduedate' => new external_value(PARAM_INT, 'Remind to grade by (timestamp; 0 = none)', VALUE_DEFAULT, null),
            'grade' => new external_value(PARAM_INT, 'Maximum points (positive number)', VALUE_DEFAULT, null),
            'scaleid' => new external_value(
                PARAM_INT,
                'Grade with this scale instead of points (positive scale id)',
                VALUE_DEFAULT,
                null
            ),
            'maxattempts' => new external_value(PARAM_INT, 'Max attempts (-1 = unlimited)', VALUE_DEFAULT, null),
            'attemptreopenmethod' => new external_value(
                PARAM_ALPHA,
                "Reopen attempts: 'none', 'manual' or 'untilpass'",
                VALUE_DEFAULT,
                null
            ),
            'onlinetext' => new external_value(PARAM_INT, 'Online text submissions: 1 on, 0 off', VALUE_DEFAULT, null),
            'file' => new external_value(PARAM_INT, 'File submissions: 1 on, 0 off', VALUE_DEFAULT, null),
            'maxfiles' => new external_value(PARAM_INT, 'Max files per submission', VALUE_DEFAULT, null),
            'maxsizebytes' => new external_value(PARAM_INT, 'Max submission size in bytes (0 = course limit)', VALUE_DEFAULT, null),
            'submissiondrafts' => new external_value(
                PARAM_INT,
                'Require clicking Submit button: 1 yes, 0 no',
                VALUE_DEFAULT,
                null
            ),
            'completionsubmit' => new external_value(
                PARAM_INT,
                'Completion rule "student must submit": 1 on, 0 off (switches completion to automatic)',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Update the assignment.
     *
     * @param int $cmid
     * @param int|null $duedate
     * @param int|null $allowsubmissionsfromdate
     * @param int|null $cutoffdate
     * @param int|null $gradingduedate
     * @param int|null $grade
     * @param int|null $scaleid
     * @param int|null $maxattempts
     * @param string|null $attemptreopenmethod
     * @param int|null $onlinetext
     * @param int|null $file
     * @param int|null $maxfiles
     * @param int|null $maxsizebytes
     * @param int|null $submissiondrafts
     * @param int|null $completionsubmit
     * @return array
     */
    public static function execute(
        int $cmid,
        ?int $duedate = null,
        ?int $allowsubmissionsfromdate = null,
        ?int $cutoffdate = null,
        ?int $gradingduedate = null,
        ?int $grade = null,
        ?int $scaleid = null,
        ?int $maxattempts = null,
        ?string $attemptreopenmethod = null,
        ?int $onlinetext = null,
        ?int $file = null,
        ?int $maxfiles = null,
        ?int $maxsizebytes = null,
        ?int $submissiondrafts = null,
        ?int $completionsubmit = null
    ): array {
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'duedate' => $duedate,
            'allowsubmissionsfromdate' => $allowsubmissionsfromdate,
            'cutoffdate' => $cutoffdate,
            'gradingduedate' => $gradingduedate,
            'grade' => $grade,
            'scaleid' => $scaleid,
            'maxattempts' => $maxattempts,
            'attemptreopenmethod' => $attemptreopenmethod,
            'onlinetext' => $onlinetext,
            'file' => $file,
            'maxfiles' => $maxfiles,
            'maxsizebytes' => $maxsizebytes,
            'submissiondrafts' => $submissiondrafts,
            'completionsubmit' => $completionsubmit,
        ]);

        if ($params['grade'] !== null && $params['scaleid'] !== null) {
            \local_mcpconnector\local\reject::because('pass grade (points) OR scaleid, not both');
        }
        if (
            $params['attemptreopenmethod'] !== null
                && !in_array($params['attemptreopenmethod'], ['none', 'manual', 'untilpass'], true)
        ) {
            \local_mcpconnector\local\reject::because("attemptreopenmethod must be 'none', 'manual' or 'untilpass'");
        }

        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $modcontext = \context_module::instance($cm->id);

        self::validate_context($modcontext);
        require_capability('moodle/course:manageactivities', $modcontext);

        $PAGE->set_context($modcontext);
        [$cm, , , $data] = get_moduleinfo_data($cm, $course);

        // Above, get_moduleinfo_data prefilled gradepass LOCALE-FORMATTED for the
        // form ('0,00' in es) — the form undoes it with unformat_float; we must
        // too, or the grade_item update crashes with a DB truncation error.
        if (isset($data->gradepass)) {
            $data->gradepass = unformat_float($data->gradepass);
        }

        // Seed the CURRENT enabled-state and settings of every plugin so
        // update_instance doesn't silently disable/reset the ones we're not
        // touching (it reads {subtype}_{type}_enabled with !empty()).
        $assignment = new \assign($modcontext, $cm, $course);
        foreach (array_merge($assignment->get_submission_plugins(), $assignment->get_feedback_plugins()) as $plugin) {
            if (!$plugin->is_visible()) {
                continue;
            }
            $prefix = $plugin->get_subtype() . '_' . $plugin->get_type();
            $data->{$prefix . '_enabled'} = $plugin->is_enabled() ? 1 : 0;
        }
        // Settings whose form-field name differs from the stored config name
        // (each plugin's save_settings reads its fields unconditionally).
        $fileconfig = $assignment->get_submission_plugin_by_type('file');
        $data->assignsubmission_file_maxfiles = (int) ($fileconfig->get_config('maxfilesubmissions') ?: 20);
        $data->assignsubmission_file_maxsizebytes = (int) ($fileconfig->get_config('maxsubmissionsizebytes') ?: 0);
        $data->assignsubmission_file_filetypes = (string) ($fileconfig->get_config('filetypeslist') ?: '');
        $onlinetextconfig = $assignment->get_submission_plugin_by_type('onlinetext');
        $data->assignsubmission_onlinetext_wordlimit = (int) ($onlinetextconfig->get_config('wordlimit') ?: 0);
        $data->assignsubmission_onlinetext_wordlimit_enabled = (int) ($onlinetextconfig->get_config('wordlimitenabled') ?: 0);
        $commentsconfig = $assignment->get_feedback_plugin_by_type('comments');
        $data->assignfeedback_comments_commentinline = (int) ($commentsconfig->get_config('commentinline') ?: 0);

        // Apply the requested changes.
        foreach (
            ['duedate', 'allowsubmissionsfromdate', 'cutoffdate', 'gradingduedate',
                'maxattempts', 'attemptreopenmethod', 'submissiondrafts'] as $field
        ) {
            if ($params[$field] !== null) {
                $data->$field = $params[$field];
            }
        }
        if ($params['grade'] !== null) {
            $data->grade = abs($params['grade']);
        }
        if ($params['scaleid'] !== null) {
            // A scale is encoded by mod_assign as MINUS the scale id.
            $DB->get_record('scale', ['id' => $params['scaleid']], 'id', MUST_EXIST);
            $data->grade = -abs($params['scaleid']);
        }
        if ($params['onlinetext'] !== null) {
            $data->assignsubmission_onlinetext_enabled = $params['onlinetext'] ? 1 : 0;
        }
        if ($params['file'] !== null) {
            $data->assignsubmission_file_enabled = $params['file'] ? 1 : 0;
        }
        if ($params['maxfiles'] !== null) {
            $data->assignsubmission_file_maxfiles = $params['maxfiles'];
        }
        if ($params['maxsizebytes'] !== null) {
            $data->assignsubmission_file_maxsizebytes = $params['maxsizebytes'];
        }
        if ($params['completionsubmit'] !== null) {
            // Completion fields are only applied by update_moduleinfo when the
            // form says they're unlocked; the rule needs automatic tracking.
            $data->completionunlocked = 1;
            $data->completion = COMPLETION_TRACKING_AUTOMATIC;
            $data->completionsubmit = $params['completionsubmit'] ? 1 : 0;
        }

        update_moduleinfo($cm, $data, $course, null);

        return ['success' => true, 'cmid' => $cm->id];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was applied'),
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }
}
