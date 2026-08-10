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
 * Create a book (mod_book) WITH its chapters in one call — core can only
 * read books (mod_book_get_books_by_courses); authoring is form-only.
 * Chapters follow the import tool's storage model: ordered book_chapters
 * rows (top-level or subchapter) with HTML content.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class create_book extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Section NUMBER (0 = General)', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Book name', VALUE_REQUIRED),
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Chapter title', VALUE_REQUIRED),
                    'content' => new external_value(PARAM_RAW, 'Chapter body (HTML)', VALUE_REQUIRED),
                    'subchapter' => new external_value(
                        PARAM_INT,
                        '1 = subchapter of the previous top-level chapter',
                        VALUE_DEFAULT,
                        0
                    ),
                ]),
                'Ordered chapters',
                VALUE_REQUIRED
            ),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, 1),
            'numbering' => new external_value(
                PARAM_INT,
                'Chapter numbering: 0 none, 1 numbers (default), 2 bullets, 3 indented',
                VALUE_DEFAULT,
                1
            ),
        ]);
    }

    /**
     * Create the book and its chapters.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param array $chapters
     * @param string $intro
     * @param int $visible
     * @param int $numbering
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        array $chapters,
        string $intro = '',
        int $visible = 1,
        int $numbering = 1
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');

        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'chapters' => $chapters,
            'intro' => $intro,
            'visible' => $visible,
            'numbering' => $numbering,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'chapters' => $chapters,
            'intro' => $intro,
            'visible' => $visible,
            'numbering' => $numbering,
        ]);

        if ($chapters === []) {
            \local_mcpconnector\local\reject::because('a book needs at least one chapter');
        }
        if (!empty($chapters[0]['subchapter'])) {
            \local_mcpconnector\local\reject::because('the first chapter cannot be a subchapter');
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        $created = create_module::add($course, 'book', $sectionnum, $name, $intro, $visible, [
            'numbering' => $numbering,
            'navstyle' => 1,
            'customtitles' => 0,
        ]);

        // Chapters are plain ordered rows (the import tool's model — mod_book
        // has no chapter API). pagenum is the 1-based order.
        $now = time();
        $chapterids = [];
        foreach (array_values($chapters) as $i => $chapter) {
            $chapterids[] = (int) $DB->insert_record('book_chapters', (object) [
                'bookid' => (int) $created->instance,
                'pagenum' => $i + 1,
                'subchapter' => empty($chapter['subchapter']) ? 0 : 1,
                'title' => $chapter['title'],
                'content' => $chapter['content'],
                'contentformat' => FORMAT_HTML,
                'hidden' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'importsrc' => '',
            ]);
        }
        $DB->set_field('book', 'revision', 1, ['id' => (int) $created->instance]);
        rebuild_course_cache($course->id, true);

        return [
            'cmid' => (int) $created->coursemodule,
            'instanceid' => (int) $created->instance,
            'chapterids' => $chapterids,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'instanceid' => new external_value(PARAM_INT, 'book instance id'),
            'chapterids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Chapter id'),
                'Created chapter ids, in order'
            ),
        ]);
    }
}
