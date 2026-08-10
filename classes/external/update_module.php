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
 * Update an existing course module (name, intro, visibility, completion, idnumber).
 *
 * Fills a Moodle core gap: there is no core webservice able to edit an
 * activity's intro. Uses the supported get_moduleinfo_data/update_moduleinfo
 * pair so files, caches, events and completion recalculation behave exactly
 * like a save from the settings form.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_module extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'New activity name', VALUE_DEFAULT, null),
            'intro' => new external_value(PARAM_RAW, 'New description (HTML)', VALUE_DEFAULT, null),
            'introformat' => new external_value(PARAM_INT, 'Format of intro (1 = HTML)', VALUE_DEFAULT, null),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, null),
            'idnumber' => new external_value(PARAM_RAW, 'ID number (gradebook/reporting identifier)', VALUE_DEFAULT, null),
            'completion' => new external_value(
                PARAM_INT,
                'Completion tracking: 0 none, 1 manual, 2 automatic',
                VALUE_DEFAULT,
                null
            ),
            'content' => new external_value(
                PARAM_RAW,
                "New main body (HTML). Only for content modules (currently mod_page); a label's body is its intro",
                VALUE_DEFAULT,
                null
            ),
            'completionview' => new external_value(
                PARAM_INT,
                'Automatic rule "student must view": 1 on, 0 off (implies completion=2)',
                VALUE_DEFAULT,
                null
            ),
            'completionusegrade' => new external_value(
                PARAM_INT,
                'Automatic rule "student must receive a grade": 1 on, 0 off (implies completion=2)',
                VALUE_DEFAULT,
                null
            ),
            'completionpassgrade' => new external_value(
                PARAM_INT,
                'Automatic rule "student must PASS" (needs a gradepass on the grade item): 1 on, 0 off',
                VALUE_DEFAULT,
                null
            ),
            'completionexpected' => new external_value(
                PARAM_INT,
                'Expected completion date (unix timestamp; 0 = none)',
                VALUE_DEFAULT,
                null
            ),
            'completionscormstatus' => new external_value(
                PARAM_ALPHA,
                "SCORM-only automatic rule 'require status': 'passed', 'completed', "
                . "'passedorcompleted' (either counts) or 'none' (rule off). Implies completion=2",
                VALUE_DEFAULT,
                null
            ),
            'completionstatusallscos' => new external_value(
                PARAM_INT,
                'SCORM-only: the required status must be reached in ALL SCOs, 1 yes, 0 no',
                VALUE_DEFAULT,
                null
            ),
            'completionpassorattemptsexhausted' => new external_value(
                PARAM_INT,
                'QUIZ-only automatic rule: completed when the student PASSES OR runs out of attempts. '
                . '1 on (also enables usegrade+passgrade — Moodle requires them for this rule), 0 off',
                VALUE_DEFAULT,
                null
            ),
            'completionminattempts' => new external_value(
                PARAM_INT,
                'QUIZ-only automatic rule: require at least N attempts (0 = rule off)',
                VALUE_DEFAULT,
                null
            ),
            'availability' => new external_value(
                PARAM_RAW,
                'Access restrictions: Moodle availability JSON tree as a STRING, e.g. '
                . '{"op":"&","c":[{"type":"completion","cm":123,"e":1}],"showc":[true]}. '
                . 'Empty string removes all restrictions.',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Update the module.
     *
     * @param int $cmid
     * @param string|null $name
     * @param string|null $intro
     * @param int|null $introformat
     * @param int|null $visible
     * @param string|null $idnumber
     * @param int|null $completion
     * @param string|null $content
     * @param int|null $completionview
     * @param int|null $completionusegrade
     * @param int|null $completionpassgrade
     * @param int|null $completionexpected
     * @param string|null $completionscormstatus
     * @param int|null $completionstatusallscos
     * @param int|null $completionpassorattemptsexhausted
     * @param int|null $completionminattempts
     * @param string|null $availability
     * @return array
     */
    public static function execute(
        int $cmid,
        ?string $name = null,
        ?string $intro = null,
        ?int $introformat = null,
        ?int $visible = null,
        ?string $idnumber = null,
        ?int $completion = null,
        ?string $content = null,
        ?int $completionview = null,
        ?int $completionusegrade = null,
        ?int $completionpassgrade = null,
        ?int $completionexpected = null,
        ?string $completionscormstatus = null,
        ?int $completionstatusallscos = null,
        ?int $completionpassorattemptsexhausted = null,
        ?int $completionminattempts = null,
        ?string $availability = null
    ): array {
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/completionlib.php');

        [
            'cmid' => $cmid,
            'name' => $name,
            'intro' => $intro,
            'introformat' => $introformat,
            'visible' => $visible,
            'idnumber' => $idnumber,
            'completion' => $completion,
            'content' => $content,
            'completionview' => $completionview,
            'completionusegrade' => $completionusegrade,
            'completionpassgrade' => $completionpassgrade,
            'completionexpected' => $completionexpected,
            'completionscormstatus' => $completionscormstatus,
            'completionstatusallscos' => $completionstatusallscos,
            'completionpassorattemptsexhausted' => $completionpassorattemptsexhausted,
            'completionminattempts' => $completionminattempts,
            'availability' => $availability,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'name' => $name,
            'intro' => $intro,
            'introformat' => $introformat,
            'visible' => $visible,
            'idnumber' => $idnumber,
            'completion' => $completion,
            'content' => $content,
            'completionview' => $completionview,
            'completionusegrade' => $completionusegrade,
            'completionpassgrade' => $completionpassgrade,
            'completionexpected' => $completionexpected,
            'completionscormstatus' => $completionscormstatus,
            'completionstatusallscos' => $completionstatusallscos,
            'completionpassorattemptsexhausted' => $completionpassorattemptsexhausted,
            'completionminattempts' => $completionminattempts,
            'availability' => $availability,
        ]);

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $modcontext = \context_module::instance($cm->id);

        self::validate_context($modcontext);
        require_capability('moodle/course:manageactivities', $modcontext);

        if ($content !== null && $cm->modname !== 'page') {
            \local_mcpconnector\local\reject::because(
                "content is only supported for mod_page (got '{$cm->modname}'); "
                . "a label's body is its intro"
            );
        }

        $scormrules = $completionscormstatus !== null || $completionstatusallscos !== null;
        if ($scormrules && $cm->modname !== 'scorm') {
            \local_mcpconnector\local\reject::because(
                'completionscormstatus/completionstatusallscos are only for mod_scorm '
                . "(got '{$cm->modname}')"
            );
        }
        $quizrules = $completionpassorattemptsexhausted !== null || $completionminattempts !== null;
        if ($quizrules && $cm->modname !== 'quiz') {
            \local_mcpconnector\local\reject::because(
                'completionpassorattemptsexhausted/completionminattempts are only for mod_quiz '
                . "(got '{$cm->modname}')"
            );
        }
        if (
            $completionscormstatus !== null
                && !in_array($completionscormstatus, ['passed', 'completed', 'passedorcompleted', 'none'], true)
        ) {
            \local_mcpconnector\local\reject::because(
                "completionscormstatus must be 'passed', 'completed', 'passedorcompleted' or 'none'"
            );
        }

        if ($availability !== null) {
            if (empty($CFG->enableavailability)) {
                \local_mcpconnector\local\reject::because('restricted access is disabled site-wide — '
                    . 'enable the core setting enableavailability first (Site administration > Advanced features)');
            }
            $availability = trim($availability);
            // Accept the JSON literal null as "remove", like the empty string.
            if ($availability === 'null') {
                $availability = '';
            }
            if ($availability !== '') {
                // Validate BEFORE handing to update_moduleinfo: a corrupt tree
                // stored in course_modules.availability breaks the course page,
                // and core's own failure is an opaque coding_exception.
                $decoded = json_decode($availability);
                if (!is_object($decoded)) {
                    \local_mcpconnector\local\reject::because('availability must be a JSON availability tree '
                        . '(e.g. {"op":"&","c":[{"type":"completion","cm":123,"e":1}],"showc":[true]}) '
                        . 'or an empty string to remove all restrictions');
                }
                try {
                    new \core_availability\tree($decoded);
                } catch (\Throwable $e) {
                    \local_mcpconnector\local\reject::because('invalid availability tree: ' . $e->getMessage());
                }
            }
        }

        $completionrules = $completionview !== null || $completionusegrade !== null
            || $completionpassgrade !== null || $scormrules || $quizrules;
        // Only ENABLING a rule implies automatic tracking (turning one off
        // must not flip a module to automatic).
        $enablesrule = $completionview === 1 || $completionusegrade === 1
            || $completionpassgrade === 1 || $completionstatusallscos === 1
            || ($completionscormstatus !== null && $completionscormstatus !== 'none')
            || $completionpassorattemptsexhausted === 1
            || ($completionminattempts !== null && $completionminattempts > 0);
        $touchescompletion = $completion !== null || $completionrules || $completionexpected !== null;

        if ($touchescompletion && empty($course->enablecompletion)) {
            \local_mcpconnector\local\reject::because('the course has completion tracking disabled — enable it first '
                . '(local_mcpconnector_set_course_completion does, or core_course_update_courses enablecompletion=1)');
        }

        $needsfullupdate = $intro !== null || $introformat !== null
            || $idnumber !== null || $touchescompletion || $content !== null
            || $availability !== null;

        if (!$needsfullupdate) {
            // Fast path: name/visibility have dedicated core setters that avoid
            // the whole moduleinfo machinery (and its introeditor requirement).
            if ($name !== null && $name !== '') {
                set_coursemodule_name($cm->id, $name);
            }
            if ($visible !== null) {
                set_coursemodule_visible($cm->id, $visible ? 1 : 0);
            }
            return ['success' => true, 'cmid' => $cm->id];
        }

        // Full path: load the module exactly as the settings form would —
        // get_moduleinfo_data returns $data prefilled including a valid
        // introeditor draft (update_moduleinfo unconditionally expects it for
        // modules with FEATURE_MOD_INTRO) — then override only what changed.
        $PAGE->set_context($modcontext);
        [$cm, , , $data] = get_moduleinfo_data($cm, $course);

        // Above, get_moduleinfo_data prefilled gradepass LOCALE-FORMATTED for the
        // form ('0,00' in es) — the form undoes it with unformat_float; we must
        // too, or graded modules crash with a DB truncation error on update.
        if (isset($data->gradepass)) {
            $data->gradepass = unformat_float($data->gradepass);
        }

        if ($cm->modname === 'quiz') {
            // Several fields needed by quiz_update_instance are only seeded by
            // the mod_form; without them the update CRASHES (password NOT NULL)
            // or silently WIPES settings. Seed them from the current record,
            // mirroring mod_form's data_preprocessing.
            require_once($CFG->dirroot . '/mod/quiz/lib.php');

            // 1. quiz_process_options does $quiz->password = $quiz->quizpassword
            // unconditionally.
            $data->quizpassword = (string) ($data->password ?? '');

            // 2. Review options are rebuilt from the form's checkbox fields —
            // absent fields would zero every review bitmask.
            $times = [
                'during' => \mod_quiz\question\display_options::DURING,
                'immediately' => \mod_quiz\question\display_options::IMMEDIATELY_AFTER,
                'open' => \mod_quiz\question\display_options::LATER_WHILE_OPEN,
                'closed' => \mod_quiz\question\display_options::AFTER_CLOSE,
            ];
            $reviewfields = ['attempt', 'correctness', 'maxmarks', 'marks',
                'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'];
            foreach ($reviewfields as $field) {
                $dbvalue = (int) ($data->{'review' . $field} ?? 0);
                foreach ($times as $whenname => $when) {
                    $data->{$field . $whenname} = ($dbvalue & $when) ? 1 : 0;
                }
            }
            // The form hard-codes these two.
            $data->attemptduring = 1;
            $data->overallfeedbackduring = 0;

            // Completionminattempts is zeroed by quiz_process_options when its
            // 'enabled' form checkbox is absent (and completion is unlocked) —
            // derive it from the current value so unrelated completion
            // updates don't wipe the rule.
            $data->completionminattemptsenabled = !empty($data->completionminattempts) ? 1 : 0;

            // 3. quiz_after_add_or_update DELETES all quiz_feedback rows and
            // recreates them from these arrays — absent = overall feedback
            // silently wiped. Rebuild them from the existing rows, carrying
            // embedded files through a draft area.
            $feedbackrows = array_values(
                $DB->get_records('quiz_feedback', ['quizid' => $cm->instance], 'mingrade DESC')
            );
            $data->feedbacktext = [];
            $data->feedbackboundaries = [];
            if ($feedbackrows === []) {
                $data->feedbacktext[0] = ['text' => '', 'format' => FORMAT_HTML,
                    'itemid' => file_get_unused_draft_itemid()];
            } else {
                foreach ($feedbackrows as $i => $row) {
                    $draftid = null;
                    file_prepare_draft_area(
                        $draftid,
                        $modcontext->id,
                        'mod_quiz',
                        'feedback',
                        (int) $row->id,
                        ['subdirs' => false]
                    );
                    $data->feedbacktext[$i] = [
                        'text' => $row->feedbacktext,
                        'format' => $row->feedbacktextformat,
                        'itemid' => $draftid,
                    ];
                    if ($i < count($feedbackrows) - 1) {
                        // Boundaries are every band's lower edge except the
                        // last one (always 0).
                        $data->feedbackboundaries[$i] = $row->mingrade + 0;
                    }
                }
            }
        }

        if ($name !== null && $name !== '') {
            $data->name = $name;
        }
        if (isset($data->introeditor) && is_array($data->introeditor)) {
            if ($intro !== null) {
                $data->introeditor['text'] = $intro;
            }
            if ($introformat !== null) {
                $data->introeditor['format'] = $introformat;
            }
        }
        if ($visible !== null) {
            $data->visible = $visible ? 1 : 0;
        }
        if ($idnumber !== null) {
            $data->cmidnumber = $idnumber;
        }
        if ($availability !== null) {
            // This is update_moduleinfo's own path: validates with
            // core_availability\tree, treats '' as remove (NULL), saves and
            // rebuilds the course cache — the same code the settings form runs.
            $data->availabilityconditionsjson = $availability;
        }
        if ($touchescompletion) {
            // The completion/completionview/completionusegrade/completionpassgrade
            // fields are only applied by update_moduleinfo when the form flags
            // them as unlocked — without this, they are silently preserved.
            $data->completionunlocked = 1;
            // Rules imply automatic tracking unless the caller says otherwise.
            $data->completion = $completion
                ?? ($enablesrule ? COMPLETION_TRACKING_AUTOMATIC : $data->completion);
            if ($completionview !== null) {
                $data->completionview = $completionview ? 1 : 0;
            }
            if ($completionpassgrade !== null && $completionpassgrade) {
                // Must-pass needs the grade rule on (gradeitemnumber set).
                $completionusegrade = 1;
            }
            if ($completionusegrade !== null) {
                $data->completionusegrade = $completionusegrade ? 1 : 0;
                $data->completiongradeitemnumber = $completionusegrade ? 0 : null;
            }
            if ($completionpassgrade !== null) {
                $data->completionpassgrade = $completionpassgrade ? 1 : 0;
            }
            if ($completionexpected !== null) {
                $data->completionexpected = $completionexpected;
            }
            if ($completionscormstatus !== null) {
                // Scorm table column: bitmask of scorm_status_options()
                // (2 = passed, 4 = completed); null = rule off.
                $data->completionstatusrequired = [
                    'passed' => 2,
                    'completed' => 4,
                    'passedorcompleted' => 6,
                    'none' => null,
                ][$completionscormstatus];
            }
            if ($completionstatusallscos !== null) {
                $data->completionstatusallscos = $completionstatusallscos ? 1 : 0;
            }
            if ($completionpassorattemptsexhausted !== null) {
                if ($completionpassorattemptsexhausted) {
                    // Completionattemptsexhausted is zeroed by
                    // quiz_process_options unless usegrade AND passgrade are on
                    // — the OR with 'attempts exhausted' is exactly what softens
                    // the pass requirement into 'done'.
                    $data->completionusegrade = 1;
                    $data->completiongradeitemnumber = 0;
                    $data->completionpassgrade = 1;
                    $data->completionattemptsexhausted = 1;
                } else {
                    $data->completionattemptsexhausted = 0;
                }
            }
            if ($completionminattempts !== null) {
                $data->completionminattemptsenabled = $completionminattempts > 0 ? 1 : 0;
                $data->completionminattempts = $completionminattempts;
            }
        }
        if ($cm->modname === 'page') {
            // Page_get_editor_options(), called by page_update_instance, lives in
            // locallib.php — loaded by the mod_form flow but NOT by
            // update_moduleinfo (it only includes the module's lib.php).
            require_once($CFG->dirroot . '/mod/page/locallib.php');

            // Unconditionally, page_update_instance reads $data->page['itemid']
            // and the display fields (normally seeded by the mod_form's
            // data_preprocessing, which we bypass with $mform = null) — so the
            // page array must be seeded on EVERY full update, keeping the
            // existing body when content isn't being changed.
            // An EMPTY draft id makes file_prepare_draft_area create the area
            // AND copy the existing embedded files into it, so @@PLUGINFILE@@
            // references keep resolving.
            $draftid = null;
            file_prepare_draft_area($draftid, $modcontext->id, 'mod_page', 'content', 0, ['subdirs' => true]);
            $data->page = [
                'text' => $content ?? $data->content,
                'format' => $content !== null ? FORMAT_HTML : ($data->contentformat ?? FORMAT_HTML),
                'itemid' => $draftid,
            ];

            // Read through unserialize_array(), not unserialize(): a restored
            // .mbz can put arbitrary bytes in this column (mod_page's restore
            // step inserts the record verbatim), and plain unserialize() would
            // instantiate whatever objects they describe. Core reads it the
            // same way — see mod/page/view.php.
            $opts = empty($data->displayoptions) ? [] : (array) unserialize_array($data->displayoptions);
            $data->printintro = $opts['printintro'] ?? 0;
            $data->printlastmodified = $opts['printlastmodified'] ?? 1;
            $data->popupwidth = $opts['popupwidth'] ?? 620;
            $data->popupheight = $opts['popupheight'] ?? 450;
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
