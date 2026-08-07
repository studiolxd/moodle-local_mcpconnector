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
 * Add N random-question slots to a quiz, drawing from a question bank category.
 *
 * Core's mod_quiz_add_random_questions exists but expects the whole qbank
 * filtercondition blob as a JSON string (built by the editing UI) — hostile
 * for API callers. This wrapper takes a category id + includesubcategories
 * and builds that condition server-side, exactly like the core WS does for
 * its own newcategory branch.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class add_random_questions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the QUIZ', VALUE_REQUIRED),
            'count' => new external_value(PARAM_INT, 'How many random questions to add', VALUE_REQUIRED),
            'categoryid' => new external_value(PARAM_INT, 'Question category id to draw from', VALUE_REQUIRED),
            'includesubcategories' => new external_value(PARAM_INT,
                'Also draw from subcategories: 1 yes (default), 0 no', VALUE_DEFAULT, 1),
            'page' => new external_value(PARAM_INT, 'Quiz page to add to (0 = at the end, default)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Add the random slots.
     *
     * @param int $cmid
     * @param int $count
     * @param int $categoryid
     * @param int $includesubcategories
     * @param int $page
     * @return array
     */
    public static function execute(
        int $cmid,
        int $count,
        int $categoryid,
        int $includesubcategories = 1,
        int $page = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/editlib.php');

        [
            'cmid' => $cmid,
            'count' => $count,
            'categoryid' => $categoryid,
            'includesubcategories' => $includesubcategories,
            'page' => $page,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'count' => $count,
            'categoryid' => $categoryid,
            'includesubcategories' => $includesubcategories,
            'page' => $page,
        ]);

        if ($count < 1 || $count > 100) {
            \local_mcpconnector\local\reject::because('count must be between 1 and 100');
        }

        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quizcontext = \context_module::instance($cm->id);

        self::validate_context($quizcontext);
        require_capability('mod/quiz:manage', $quizcontext);

        // The filtercondition shape + custom_category_condition class this
        // builds on arrived with the 5.x qbank model.
        \local_mcpconnector\local\compat::require_question_bank('local_mcpconnector_add_random_questions');

        $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);

        // The exact shape the quiz editing UI submits (and the core WS builds
        // for its newcategory branch). structure::add_random_questions
        // re-checks moodle/question:useall on the category's context.
        $filtercondition = [
            'qpage' => 0,
            'cat' => "{$category->id},{$category->contextid}",
            'qperpage' => DEFAULT_QUESTIONS_PER_PAGE,
            'tabname' => 'questions',
            'sortdata' => [],
            'filter' => [
                'category' => [
                    'jointype' => \mod_quiz\question\bank\filter\custom_category_condition::JOINTYPE_DEFAULT,
                    'values' => [$category->id],
                    'filteroptions' => ['includesubcategories' => (bool) $includesubcategories],
                ],
            ],
        ];

        $settings = \mod_quiz\quiz_settings::create_for_cmid($cm->id);
        $structure = \mod_quiz\structure::create_for_quiz($settings);
        $structure->add_random_questions($page, $count, $filtercondition);

        quiz_delete_previews($quiz);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        $slots = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);

        return [
            'added' => $count,
            'totalslots' => $slots,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'added' => new external_value(PARAM_INT, 'Random slots added'),
            'totalslots' => new external_value(PARAM_INT, 'Total slots in the quiz now'),
        ]);
    }
}
