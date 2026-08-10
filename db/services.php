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

/**
 * External functions provided by the MCP connector.
 *
 * These fill webservice gaps in Moodle core: there is no core function to
 * edit an activity's intro, to create a non-quick-create module (forum…), or
 * to create a SCORM from a package. They are added to the plugin's role-scoped
 * external services in db/service_functions.php.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mcpconnector_update_module' => [
        'classname'    => 'local_mcpconnector\external\update_module',
        'methodname'   => 'execute',
        'description'  => 'Update an existing course module: name, intro (description), visibility, completion, idnumber.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_module' => [
        'classname'    => 'local_mcpconnector\external\create_module',
        'methodname'   => 'execute',
        'description'  => 'Create an activity module (forum, assign, …) in a course section with '
            . 'sensible defaults. Unlike core_courseformat_new_module it is not limited to '
            . 'quick-create modules.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_scorm' => [
        'classname'    => 'local_mcpconnector\external\create_scorm',
        'methodname'   => 'execute',
        'description'  => 'Create a SCORM activity downloading its package from an HTTPS URL.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_update_scorm' => [
        'classname'    => 'local_mcpconnector\external\update_scorm',
        'methodname'   => 'execute',
        'description'  => 'Replace an existing SCORM\'s package and/or its mod_scorm settings, preserving cmid/instance/attempts.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_update_section' => [
        'classname'    => 'local_mcpconnector\external\update_section',
        'methodname'   => 'execute',
        'description'  => 'Update a course section\'s name, summary and/or visibility.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_resource' => [
        'classname'    => 'local_mcpconnector\external\create_resource',
        'methodname'   => 'execute',
        'description'  => 'Create a File resource (mod_resource) downloading the file from an HTTPS URL.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_quiz' => [
        'classname'    => 'local_mcpconnector\external\create_quiz',
        'methodname'   => 'execute',
        'description'  => 'Create a quiz (mod_quiz) with its main settings configurable.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_import_questions' => [
        'classname'    => 'local_mcpconnector\external\import_questions',
        'methodname'   => 'execute',
        'description'  => 'Import questions (GIFT, Aiken or Moodle XML) into a quiz, a shared bank or the course bank.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities, moodle/question:add',
        'ajax'         => false,
    ],
    'local_mcpconnector_add_random_questions' => [
        'classname'    => 'local_mcpconnector\external\add_random_questions',
        'methodname'   => 'execute',
        'description'  => 'Add N random-question slots to a quiz drawing from a question bank category.',
        'type'         => 'write',
        'capabilities' => 'mod/quiz:manage, moodle/question:useall',
        'ajax'         => false,
    ],
    'local_mcpconnector_list_question_banks' => [
        'classname'    => 'local_mcpconnector\external\list_question_banks',
        'methodname'   => 'execute',
        'description'  => "List a course's question banks (shared qbank modules and quiz banks) with category ids and counts.",
        'type'         => 'read',
        'capabilities' => 'moodle/question:viewall',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_scale' => [
        'classname'    => 'local_mcpconnector\external\create_scale',
        'methodname'   => 'execute',
        'description'  => 'Create a grading scale in a course (idempotent by name).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:managescales',
        'ajax'         => false,
    ],
    'local_mcpconnector_update_assign' => [
        'classname'    => 'local_mcpconnector\external\update_assign',
        'methodname'   => 'execute',
        'description'  => 'Configure an assignment: dates, grading (points or scale), attempts, submission types.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_update_grade_item' => [
        'classname'    => 'local_mcpconnector\external\update_grade_item',
        'methodname'   => 'execute',
        'description'  => 'Update a gradebook item: grade to pass, weight, visibility, category.',
        'type'         => 'write',
        'capabilities' => 'moodle/grade:manage',
        'ajax'         => false,
    ],
    'local_mcpconnector_get_grade_categories' => [
        'classname'    => 'local_mcpconnector\external\get_grade_categories',
        'methodname'   => 'execute',
        'description'  => "Read a course's grade categories with their configuration (aggregation, scale, grade-to-pass).",
        'type'         => 'read',
        'capabilities' => 'moodle/grade:viewall',
        'ajax'         => false,
    ],
    'local_mcpconnector_update_grade_category' => [
        'classname'    => 'local_mcpconnector\external\update_grade_category',
        'methodname'   => 'execute',
        'description'  => 'Update a grade category: rename, aggregation, empty-grade handling, '
            . "and its total's scale/grade-to-pass.",
        'type'         => 'write',
        'capabilities' => 'moodle/grade:manage',
        'ajax'         => false,
    ],
    'local_mcpconnector_delete_grade_category' => [
        'classname'    => 'local_mcpconnector\external\delete_grade_category',
        'methodname'   => 'execute',
        'description'  => 'Delete a grade category (its items and children move to the parent).',
        'type'         => 'write',
        'capabilities' => 'moodle/grade:manage',
        'ajax'         => false,
    ],
    'local_mcpconnector_purge_questions' => [
        'classname'    => 'local_mcpconnector\external\purge_questions',
        'methodname'   => 'execute',
        'description'  => 'Delete questions by id or by category (optionally with subcategories and the categories themselves).',
        'type'         => 'write',
        'capabilities' => 'moodle/question:editall',
        'ajax'         => false,
    ],
    'local_mcpconnector_set_course_completion' => [
        'classname'    => 'local_mcpconnector\external\set_course_completion',
        'methodname'   => 'execute',
        'description'  => "Replace a course's completion criteria (activities and/or passing grade).",
        'type'         => 'write',
        'capabilities' => 'moodle/course:update',
        'ajax'         => false,
    ],
    'local_mcpconnector_issue_badge' => [
        'classname'    => 'local_mcpconnector\external\issue_badge',
        'methodname'   => 'execute',
        'description'  => 'Award a badge to a user (core has webservices to read badges but none to award).',
        'type'         => 'write',
        'capabilities' => 'moodle/badges:awardbadge',
        'ajax'         => false,
    ],
    'local_mcpconnector_backup_course' => [
        'classname'    => 'local_mcpconnector\external\backup_course',
        'methodname'   => 'execute',
        'description'  => 'Back a course up to .mbz and upload it to an HTTPS PUT URL (core has no backup webservice).',
        'type'         => 'write',
        'capabilities' => 'moodle/backup:backupcourse',
        'ajax'         => false,
    ],
    'local_mcpconnector_restore_course' => [
        'classname'    => 'local_mcpconnector\external\restore_course',
        'methodname'   => 'execute',
        'description'  => 'Restore a .mbz from an HTTPS URL into a new course (core has no restore webservice).',
        'type'         => 'write',
        'capabilities' => 'moodle/restore:restorecourse',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_h5p' => [
        'classname'    => 'local_mcpconnector\external\create_h5p',
        'methodname'   => 'execute',
        'description'  => 'Create an H5P activity downloading its .h5p package from an HTTPS URL.',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_create_book' => [
        'classname'    => 'local_mcpconnector\external\create_book',
        'methodname'   => 'execute',
        'description'  => 'Create a book with its chapters in one call (core can only read books).',
        'type'         => 'write',
        'capabilities' => 'moodle/course:manageactivities',
        'ajax'         => false,
    ],
    'local_mcpconnector_add_to_content_bank' => [
        'classname'    => 'local_mcpconnector\external\add_to_content_bank',
        'methodname'   => 'execute',
        'description'  => 'Upload a file (e.g. .h5p) into the content bank from an HTTPS URL.',
        'type'         => 'write',
        'capabilities' => 'moodle/contentbank:upload',
        'ajax'         => false,
    ],
];
