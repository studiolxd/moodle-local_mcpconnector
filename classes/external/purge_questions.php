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
 * Delete questions — by explicit ids or by category (optionally with its
 * subcategories, optionally deleting the emptied categories too).
 *
 * Fills a Moodle core gap: no webservice deletes bank questions or
 * categories. Uses core semantics throughout: question_delete_question()
 * HIDES a question that is in use (referenced by a quiz) instead of deleting
 * it, and category deletion goes through core_question\category_manager,
 * which refuses the top category or the context's only category.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class purge_questions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT,
                'Purge this question category (pass this OR questionids)', VALUE_DEFAULT, null),
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question id'),
                'Delete these specific questions (pass this OR categoryid)',
                VALUE_DEFAULT, []
            ),
            'includesubcategories' => new external_value(PARAM_INT,
                'With categoryid: also purge subcategories, 1 yes (default), 0 no', VALUE_DEFAULT, 1),
            'deletecategories' => new external_value(PARAM_INT,
                'With categoryid: also delete the emptied categories, 1 yes, 0 no (default)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Purge.
     *
     * @param int|null $categoryid
     * @param array $questionids
     * @param int $includesubcategories
     * @param int $deletecategories
     * @return array
     */
    public static function execute(
        ?int $categoryid = null,
        array $questionids = [],
        int $includesubcategories = 1,
        int $deletecategories = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/questionlib.php');

        [
            'categoryid' => $categoryid,
            'questionids' => $questionids,
            'includesubcategories' => $includesubcategories,
            'deletecategories' => $deletecategories,
        ] = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'questionids' => $questionids,
            'includesubcategories' => $includesubcategories,
            'deletecategories' => $deletecategories,
        ]);

        if (($categoryid === null) === ($questionids === [])) {
            \local_mcpconnector\local\reject::because('pass exactly one of categoryid or questionids');
        }

        $deleted = 0;
        $hidden = 0;
        $categoriesdeleted = 0;
        $skipped = [];

        if ($questionids !== []) {
            foreach ($questionids as $questionid) {
                $catid = self::question_category_id($questionid);
                $cat = $DB->get_record('question_categories', ['id' => $catid], '*', MUST_EXIST);
                self::require_editall($cat->contextid);
                self::delete_question($questionid, $deleted, $hidden);
            }
            return [
                'questionsdeleted' => $deleted,
                'questionshidden' => $hidden,
                'categoriesdeleted' => 0,
                'skippedcategories' => [],
            ];
        }

        $rootcat = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
        self::require_editall($rootcat->contextid);

        // Collect the category tree (root first; children found breadth-first
        // WITHIN the same context — categories never span contexts).
        $catids = [(int) $rootcat->id];
        if ($includesubcategories) {
            $queue = [(int) $rootcat->id];
            while ($queue !== []) {
                $parent = array_shift($queue);
                $children = $DB->get_fieldset_select('question_categories', 'id',
                    'parent = ? AND contextid = ?', [$parent, $rootcat->contextid]);
                foreach ($children as $child) {
                    $catids[] = (int) $child;
                    $queue[] = (int) $child;
                }
            }
        }

        foreach ($catids as $catid) {
            $qids = $DB->get_fieldset_sql(
                "SELECT q.id
                   FROM {question} q
                   JOIN {question_versions} qv ON qv.questionid = q.id
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  WHERE qbe.questioncategoryid = ?",
                [$catid]
            );
            foreach ($qids as $qid) {
                self::delete_question((int) $qid, $deleted, $hidden);
            }
            // In-use questions got hidden, not deleted; this clears the ones
            // whose last reference has since gone.
            \qbank_managecategories\helper::question_remove_stale_questions_from_category($catid);
        }

        if ($deletecategories) {
            \local_mcpconnector\local\compat::require_category_manager(
                'local_mcpconnector_purge_questions (deletecategories)'
            );
            require_capability('moodle/question:managecategory',
                \core\context::instance_by_id($rootcat->contextid));
            $manager = new \core_question\category_manager();
            // Children first, so every category is empty of subcategories
            // when its turn comes.
            foreach (array_reverse($catids) as $catid) {
                try {
                    $manager->delete_category($catid);
                    $categoriesdeleted++;
                } catch (\Throwable $e) {
                    // Top category, only category in context, or still in use
                    // (e.g. a random-question slot draws from it).
                    $skipped[] = [
                        'categoryid' => $catid,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'questionsdeleted' => $deleted,
            'questionshidden' => $hidden,
            'categoriesdeleted' => $categoriesdeleted,
            'skippedcategories' => $skipped,
        ];
    }

    /**
     * Deletes one question, counting whether it was really deleted or just
     * hidden (core hides questions that are in use).
     *
     * @param int $questionid
     * @param int $deleted
     * @param int $hidden
     */
    private static function delete_question(int $questionid, int &$deleted, int &$hidden): void {
        global $DB;
        question_delete_question($questionid);
        if ($DB->record_exists('question', ['id' => $questionid])) {
            $hidden++;
        } else {
            $deleted++;
        }
    }

    /**
     * The category a question currently belongs to.
     *
     * @param int $questionid
     * @return int
     */
    private static function question_category_id(int $questionid): int {
        global $DB;
        return (int) $DB->get_field_sql(
            "SELECT qbe.questioncategoryid
               FROM {question_bank_entries} qbe
               JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
              WHERE qv.questionid = ?",
            [$questionid],
            MUST_EXIST
        );
    }

    /**
     * Validates the context and requires question-editing rights in it.
     *
     * @param int $contextid
     */
    private static function require_editall(int $contextid): void {
        $context = \core\context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('moodle/question:editall', $context);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questionsdeleted' => new external_value(PARAM_INT, 'Questions fully deleted'),
            'questionshidden' => new external_value(PARAM_INT,
                'Questions in use by a quiz — hidden instead of deleted (core semantics)'),
            'categoriesdeleted' => new external_value(PARAM_INT, 'Categories deleted'),
            'skippedcategories' => new external_multiple_structure(new external_single_structure([
                'categoryid' => new external_value(PARAM_INT, 'Category that could not be deleted'),
                'reason' => new external_value(PARAM_RAW, 'Why'),
            ])),
        ]);
    }
}
