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
 * List a course's question banks and their categories (with ids and counts).
 *
 * Since Moodle 5.0 question banks are course modules: shared mod_qbank
 * instances (including the hidden per-course system bank) plus each quiz's
 * own bank. There is no core webservice to enumerate them, yet category ids
 * are exactly what import_questions/add_random_questions need.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_question_banks extends external_api {
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
     * List banks + categories.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB;

        ['courseid' => $courseid] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid]
        );

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/question:viewall', $coursecontext);

        \local_mcpconnector\local\compat::require_question_bank('local_mcpconnector_list_question_banks');

        $banks = [];
        $modinfo = get_fast_modinfo($course);
        $bankmodname = \core_question\local\bank\question_bank_helper::get_default_question_bank_activity_name();
        foreach ([$bankmodname, 'quiz'] as $modname) {
            foreach ($modinfo->get_instances_of($modname) as $cm) {
                $context = \context_module::instance($cm->id);
                $categories = [];
                $records = $DB->get_records_select(
                    'question_categories',
                    'contextid = ? AND parent <> 0',
                    [$context->id],
                    'id'
                );
                foreach ($records as $cat) {
                    $categories[] = [
                        'id' => (int) $cat->id,
                        'name' => $cat->name,
                        'questioncount' => (int) $DB->count_records(
                            'question_bank_entries',
                            ['questioncategoryid' => $cat->id]
                        ),
                    ];
                }
                // Quiz banks with no categories yet are noise — skip them.
                if ($modname === 'quiz' && $categories === []) {
                    continue;
                }
                $banks[] = [
                    'cmid' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
                    'type' => $modname === 'quiz' ? 'quiz' : 'qbank',
                    'categories' => $categories,
                ];
            }
        }

        return ['banks' => $banks];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'banks' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id of the bank (qbank or quiz)'),
                'name' => new external_value(PARAM_TEXT, 'Bank name'),
                'type' => new external_value(PARAM_ALPHA, "'qbank' (shared) or 'quiz' (that quiz's own bank)"),
                'categories' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Question category id'),
                    'name' => new external_value(PARAM_TEXT, 'Category name'),
                    'questioncount' => new external_value(PARAM_INT, 'Questions in the category'),
                ])),
            ])),
        ]);
    }
}
