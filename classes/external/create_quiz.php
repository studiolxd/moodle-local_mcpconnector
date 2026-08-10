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
 * Create a quiz (mod_quiz) with its main settings configurable.
 *
 * Fills a Moodle core gap: mod_quiz's webservices are attempt/read only —
 * nothing creates or configures a quiz. Built on add_moduleinfo with the
 * canonical default map from mod/quiz/tests/generator (quiz_add_instance and
 * quiz_process_options read most of these fields unconditionally, so every
 * one must be present). Populate it with questions via
 * local_mcpconnector_import_questions.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class create_quiz extends external_api {
    /**
     * Canonical mod_quiz defaults (mirrors mod/quiz/tests/generator/lib.php).
     * Literals on purpose: QUIZ_GRADEHIGHEST = '1', QUIZ_NAVMETHOD_FREE =
     * 'free' — runtime defines can't appear in a class constant.
     */
    public const QUIZ_DEFAULTS = [
        'timeopen' => 0,
        'timeclose' => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'attempts' => 0,
        'attemptonlast' => 0,
        'grademethod' => 1,
        'decimalpoints' => 2,
        'questiondecimalpoints' => -1,
        'attemptduring' => 1,
        'correctnessduring' => 1,
        'maxmarksduring' => 1,
        'marksduring' => 1,
        'specificfeedbackduring' => 1,
        'generalfeedbackduring' => 1,
        'rightanswerduring' => 1,
        'overallfeedbackduring' => 0,
        'attemptimmediately' => 1,
        'correctnessimmediately' => 1,
        'maxmarksimmediately' => 1,
        'marksimmediately' => 1,
        'specificfeedbackimmediately' => 1,
        'generalfeedbackimmediately' => 1,
        'rightanswerimmediately' => 1,
        'overallfeedbackimmediately' => 1,
        'attemptopen' => 1,
        'correctnessopen' => 1,
        'maxmarksopen' => 1,
        'marksopen' => 1,
        'specificfeedbackopen' => 1,
        'generalfeedbackopen' => 1,
        'rightansweropen' => 1,
        'overallfeedbackopen' => 1,
        'attemptclosed' => 1,
        'correctnessclosed' => 1,
        'maxmarksclosed' => 1,
        'marksclosed' => 1,
        'specificfeedbackclosed' => 1,
        'generalfeedbackclosed' => 1,
        'rightanswerclosed' => 1,
        'overallfeedbackclosed' => 1,
        'questionsperpage' => 1,
        'shuffleanswers' => 1,
        'sumgrades' => 0,
        'grade' => 100,
        'timelimit' => 0,
        'overduehandling' => 'autosubmit',
        'graceperiod' => 86400,
        'quizpassword' => '',
        'subnet' => '',
        'browsersecurity' => '',
        'delay1' => 0,
        'delay2' => 0,
        'showuserpicture' => 0,
        'showblocks' => 0,
        'navmethod' => 'free',
    ];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Section NUMBER (0 = General)', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Quiz name', VALUE_REQUIRED),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, 1),
            'timeopen' => new external_value(PARAM_INT, 'Open timestamp (0 = always)', VALUE_DEFAULT, null),
            'timeclose' => new external_value(PARAM_INT, 'Close timestamp (0 = never)', VALUE_DEFAULT, null),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in seconds (0 = none)', VALUE_DEFAULT, null),
            'attempts' => new external_value(PARAM_INT, 'Allowed attempts (0 = unlimited)', VALUE_DEFAULT, null),
            'grademethod' => new external_value(
                PARAM_INT,
                'Grading: 1 highest, 2 average, 3 first, 4 last attempt',
                VALUE_DEFAULT,
                null
            ),
            'grade' => new external_value(PARAM_FLOAT, 'Maximum grade (default 100)', VALUE_DEFAULT, null),
            'gradepass' => new external_value(PARAM_FLOAT, 'Grade to pass (0 = none)', VALUE_DEFAULT, null),
            'preferredbehaviour' => new external_value(
                PARAM_ALPHANUMEXT,
                'Question behaviour: deferredfeedback (default), immediatefeedback, interactive, adaptive…',
                VALUE_DEFAULT,
                null
            ),
            'questionsperpage' => new external_value(PARAM_INT, 'New page every N questions (default 1)', VALUE_DEFAULT, null),
            'shuffleanswers' => new external_value(
                PARAM_INT,
                'Shuffle within questions: 1 yes (default), 0 no',
                VALUE_DEFAULT,
                null
            ),
            'navmethod' => new external_value(PARAM_ALPHA, "Navigation: 'free' (default) or 'sequential'", VALUE_DEFAULT, null),
            'password' => new external_value(PARAM_TEXT, 'Password required to attempt', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Create the quiz.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $intro
     * @param int $visible
     * @param int|null $timeopen
     * @param int|null $timeclose
     * @param int|null $timelimit
     * @param int|null $attempts
     * @param int|null $grademethod
     * @param float|null $grade
     * @param float|null $gradepass
     * @param string|null $preferredbehaviour
     * @param int|null $questionsperpage
     * @param int|null $shuffleanswers
     * @param string|null $navmethod
     * @param string|null $password
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        string $intro = '',
        int $visible = 1,
        ?int $timeopen = null,
        ?int $timeclose = null,
        ?int $timelimit = null,
        ?int $attempts = null,
        ?int $grademethod = null,
        ?float $grade = null,
        ?float $gradepass = null,
        ?string $preferredbehaviour = null,
        ?int $questionsperpage = null,
        ?int $shuffleanswers = null,
        ?string $navmethod = null,
        ?string $password = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'intro' => $intro,
            'visible' => $visible,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'attempts' => $attempts,
            'grademethod' => $grademethod,
            'grade' => $grade,
            'gradepass' => $gradepass,
            'preferredbehaviour' => $preferredbehaviour,
            'questionsperpage' => $questionsperpage,
            'shuffleanswers' => $shuffleanswers,
            'navmethod' => $navmethod,
            'password' => $password,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $extra = self::QUIZ_DEFAULTS;
        // The form field is quizpassword (quiz_process_options renames it).
        $overridemap = [
            'timeopen' => 'timeopen',
            'timeclose' => 'timeclose',
            'timelimit' => 'timelimit',
            'attempts' => 'attempts',
            'grademethod' => 'grademethod',
            'grade' => 'grade',
            'gradepass' => 'gradepass',
            'preferredbehaviour' => 'preferredbehaviour',
            'questionsperpage' => 'questionsperpage',
            'shuffleanswers' => 'shuffleanswers',
            'navmethod' => 'navmethod',
            'password' => 'quizpassword',
        ];
        foreach ($overridemap as $param => $field) {
            if ($params[$param] !== null) {
                $extra[$field] = $params[$param];
            }
        }

        $created = create_module::add(
            $course,
            'quiz',
            $params['sectionnum'],
            $params['name'],
            $params['intro'],
            $params['visible'],
            $extra
        );

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
            'instanceid' => new external_value(PARAM_INT, 'New quiz instance id'),
        ]);
    }
}
