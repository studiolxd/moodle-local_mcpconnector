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
use local_mcpconnector\local\url_file;

/**
 * Import questions (GIFT, Aiken or Moodle XML) into a question bank —
 * a quiz's own bank (adding them to the quiz by default), a shared qbank
 * module, or the course's system bank (created on first use).
 *
 * Fills a Moodle core gap: question import is form-only (qbank_importquestions).
 * GIFT/Aiken/XML are TEXT formats, so unlike binary files they can travel
 * inline in the `questions` parameter; `fileurl` is the alternative for large
 * files (files-by-URL model, see local_mcpconnector\local\url_file). Questions
 * land in the default category of the QUIZ's own context — the exact flow of
 * question/bank/importquestions/import.php, with the qformat progress/error
 * echoes captured via output buffering (they become the error detail).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_questions extends external_api {
    /** Supported qformat plugins and the temp-file extension each reader expects. */
    private const FORMATS = ['gift' => 'txt', 'aiken' => 'txt', 'xml' => 'xml'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(
                PARAM_INT,
                'Course module id of the target QUIZ or QBANK (shared question bank)',
                VALUE_DEFAULT,
                null
            ),
            'courseid' => new external_value(
                PARAM_INT,
                "Alternative to cmid: import into the COURSE's question bank (created if missing)",
                VALUE_DEFAULT,
                null
            ),
            'format' => new external_value(PARAM_ALPHA, "Question format: 'gift', 'aiken' or 'xml'", VALUE_REQUIRED),
            'questions' => new external_value(
                PARAM_RAW,
                'The questions as text in the given format (preferred for these text formats)',
                VALUE_DEFAULT,
                null
            ),
            'fileurl' => new external_value(
                PARAM_URL,
                'Alternative to questions: HTTPS URL of the file to import',
                VALUE_DEFAULT,
                null
            ),
            'addtoquiz' => new external_value(
                PARAM_INT,
                'When the target is a quiz: append the imported questions to it, 1 yes (default), 0 bank only. '
                . 'Ignored for qbank/course targets',
                VALUE_DEFAULT,
                1
            ),
            'matchgrades' => new external_value(
                PARAM_ALPHA,
                "Non-standard grades: 'error' (default, reject) or 'nearest'",
                VALUE_DEFAULT,
                'error'
            ),
            'stoponerror' => new external_value(
                PARAM_INT,
                'Abort on first parse error: 1 yes (default), 0 import the valid ones',
                VALUE_DEFAULT,
                1
            ),
            'category' => new external_value(
                PARAM_RAW,
                "Category path or name inside the target bank (created if missing): '/'-separated, "
                . "e.g. 'top/INAD0011/Módulo 1' ('top/' optional; '//' escapes a literal slash)",
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Import the questions.
     *
     * @param int|null $cmid
     * @param int|null $courseid
     * @param string $format
     * @param string|null $questions
     * @param string|null $fileurl
     * @param int $addtoquiz
     * @param string $matchgrades
     * @param int $stoponerror
     * @return array
     */
    public static function execute(
        ?int $cmid,
        ?int $courseid,
        string $format,
        ?string $questions = null,
        ?string $fileurl = null,
        int $addtoquiz = 1,
        string $matchgrades = 'error',
        int $stoponerror = 1,
        ?string $category = null
    ): array {
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');

        [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'format' => $format,
            'questions' => $questions,
            'fileurl' => $fileurl,
            'addtoquiz' => $addtoquiz,
            'matchgrades' => $matchgrades,
            'stoponerror' => $stoponerror,
            'category' => $category,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'courseid' => $courseid,
            'format' => $format,
            'questions' => $questions,
            'fileurl' => $fileurl,
            'addtoquiz' => $addtoquiz,
            'matchgrades' => $matchgrades,
            'stoponerror' => $stoponerror,
            'category' => $category,
        ]);

        if (!isset(self::FORMATS[$format])) {
            \local_mcpconnector\local\reject::because("format must be one of: " . implode(', ', array_keys(self::FORMATS)));
        }
        if (!in_array($matchgrades, ['error', 'nearest'], true)) {
            \local_mcpconnector\local\reject::because("matchgrades must be 'error' or 'nearest'");
        }
        if (($questions === null || $questions === '') === ($fileurl === null || $fileurl === '')) {
            \local_mcpconnector\local\reject::because('pass exactly one of questions (inline text) or fileurl');
        }
        if (($cmid === null) === ($courseid === null)) {
            \local_mcpconnector\local\reject::because('pass exactly one of cmid (quiz or qbank) or courseid (course bank)');
        }

        \local_mcpconnector\local\compat::require_question_bank('local_mcpconnector_import_questions');

        $bankmodname = \core_question\local\bank\question_bank_helper::get_default_question_bank_activity_name();
        $quiz = null;

        if ($courseid !== null) {
            // Course target: the course's shared system bank (a hidden qbank
            // module since Moodle 5.0), created on first use.
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $coursecontext = \context_course::instance($course->id);
            self::validate_context($coursecontext);
            require_capability('moodle/question:add', $coursecontext);

            $bankcm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
            $targetcontext = \context_module::instance($bankcm->id);
            $targetcmid = (int) $bankcm->id;
        } else {
            $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $targetcontext = \context_module::instance($cm->id);
            $targetcmid = (int) $cm->id;

            self::validate_context($targetcontext);
            require_capability('moodle/question:add', $targetcontext);

            if ($cm->modname === 'quiz') {
                require_capability('mod/quiz:manage', $targetcontext);
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            } else if ($cm->modname !== $bankmodname) {
                \local_mcpconnector\local\reject::because("cmid must be a quiz or a question bank module (got '{$cm->modname}')");
            }
        }

        $PAGE->set_context($targetcontext);

        // Stage the material as a file, as the qformat readers expect.
        if ($fileurl !== null && $fileurl !== '') {
            $importfile = url_file::fetch_to_temp($fileurl);
            $realfilename = basename(parse_url($fileurl, PHP_URL_PATH) ?? '') ?: "questions.{$format}";
        } else {
            $importfile = make_request_directory() . '/questions.' . self::FORMATS[$format];
            file_put_contents($importfile, $questions);
            $realfilename = basename($importfile);
        }

        try {
            // Questions land in the target context's default category — or in
            // the requested category path, created on demand (per-course
            // isolation holds either way: paths are created IN this context).
            $defaultcategory = question_get_default_category($targetcontext->id, true);
            $contexts = new \core_question\local\bank\question_edit_contexts($targetcontext);
            $targetcategory = $defaultcategory;
            if ($category !== null && trim($category) !== '') {
                $targetcategory = \local_mcpconnector\local\category_path::ensure(
                    $course,
                    $contexts,
                    $defaultcategory,
                    trim($category)
                );
            }

            $formatfile = $CFG->dirroot . '/question/format/' . $format . '/format.php';
            require_once($formatfile);
            $classname = 'qformat_' . $format;
            /** @var \qformat_default $qformat */
            $qformat = new $classname();
            $qformat->setCategory($targetcategory);
            $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
            $qformat->setCourse($course);
            $qformat->setFilename($importfile);
            $qformat->setRealfilename($realfilename);
            $qformat->setMatchgrades($matchgrades);
            // Standard Moodle behaviour: $CATEGORY: lines in the file create/
            // use their nested path. Paths are created in the TARGET context
            // (create_category_path with contextfromfile off), so courses stay
            // isolated from each other.
            $qformat->setCatfromfile(true);
            $qformat->setContextfromfile(false);
            $qformat->setStoponerror((bool) $stoponerror);
            $qformat->set_display_progress(false);

            // The import pipeline echoes its notifications (parse errors,
            // invalid grades…) — capture them; on failure they ARE the detail.
            ob_start();
            try {
                $ok = $qformat->importpreprocess()
                    && $qformat->importprocess()
                    && $qformat->importpostprocess();
            } finally {
                $notices = trim(html_to_text(ob_get_clean() ?: '', 0, false));
            }
            if (!$ok) {
                throw new \moodle_exception(
                    'cannotimport',
                    'question',
                    '',
                    null,
                    $notices !== '' ? \core_text::substr($notices, 0, 1000) : 'import failed'
                );
            }

            $questionids = array_map('intval', $qformat->questionids);

            \core\event\questions_imported::create([
                'contextid' => $targetcategory->contextid,
                'other' => ['format' => $format, 'categoryid' => $targetcategory->id],
            ])->trigger();
        } finally {
            @unlink($importfile);
        }

        $added = 0;
        if ($quiz !== null && $addtoquiz && $questionids !== []) {
            foreach ($questionids as $questionid) {
                quiz_add_quiz_question($questionid, $quiz);
                $added++;
            }
            \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        }

        return [
            'imported' => count($questionids),
            'addedtoquiz' => $added,
            'questionids' => $questionids,
            'categoryid' => (int) $targetcategory->id,
            'cmid' => $targetcmid,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'imported' => new external_value(PARAM_INT, 'Number of questions imported into the bank'),
            'addedtoquiz' => new external_value(PARAM_INT, 'Number of questions appended to the quiz'),
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question id'),
                'Ids of the imported questions'
            ),
            'categoryid' => new external_value(
                PARAM_INT,
                'Question category the questions landed in (usable with add_random_questions)'
            ),
            'cmid' => new external_value(PARAM_INT, 'Course module id of the target quiz/bank'),
        ]);
    }
}
