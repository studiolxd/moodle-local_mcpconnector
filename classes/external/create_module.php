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
 * Create an activity module in a course section with sensible defaults.
 *
 * Fills a Moodle core gap: core_courseformat_new_module/create_module require
 * FEATURE_QUICKCREATE, which in practice only mod_subsection declares — so no
 * core webservice can create a forum, assignment, etc. This uses the supported
 * add_moduleinfo() path (the same machinery as the settings form) with the
 * module type's defaults.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_module extends external_api {
    /**
     * Extra defaults some module types need beyond the common fields. Extend as
     * real usage surfaces modules whose *_add_instance expects more.
     */
    private const MODULE_DEFAULTS = [
        'forum' => [
            'type' => 'general',
            'forcesubscribe' => 0,
            'assessed' => 0,
            'scale' => 0,
        ],
        // page_add_instance reads these unconditionally (mod/page/lib.php).
        // display 0 = RESOURCELIB_DISPLAY_AUTO; contentformat 1 = HTML.
        'page' => [
            'content' => '',
            'contentformat' => 1,
            'display' => 0,
            'printintro' => 0,
            'printlastmodified' => 1,
        ],
        // url_add_instance reads display unconditionally and popup sizes when
        // display is popup (mod/url/lib.php). externalurl arrives via the
        // dedicated parameter; display 0 = RESOURCELIB_DISPLAY_AUTO.
        'url' => [
            'externalurl' => '',
            'display' => 0,
            'printintro' => 0,
            'popupwidth' => 620,
            'popupheight' => 450,
        ],
        // quiz_add_instance/quiz_process_options read most quiz fields
        // unconditionally — full canonical map (create_quiz is the rich API).
        'quiz' => create_quiz::QUIZ_DEFAULTS,
        // assign::add_instance reads these unconditionally (mod/assign
        // generator map). add_instance also DISABLES every submission/
        // feedback plugin without an _enabled field, so file submissions are
        // enabled by default here (update_assign is the rich API).
        'assign' => [
            'alwaysshowdescription' => 1,
            'submissiondrafts' => 0,
            'requiresubmissionstatement' => 0,
            'sendnotifications' => 0,
            'sendstudentnotifications' => 1,
            'sendlatenotifications' => 0,
            'duedate' => 0,
            'allowsubmissionsfromdate' => 0,
            'grade' => 100,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'teamsubmissiongroupingid' => 0,
            'blindmarking' => 0,
            'attemptreopenmethod' => 'none',
            'maxattempts' => -1,
            'markingworkflow' => 0,
            'markingallocation' => 0,
            'markinganonymous' => 0,
            'timelimit' => 0,
            'submissionattachments' => 0,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 20,
            'assignsubmission_file_maxsizebytes' => 0,
            'assignsubmission_file_filetypes' => '',
            'assignfeedback_comments_enabled' => 1,
            'assignfeedback_comments_commentinline' => 0,
        ],
    ];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'modname' => new external_value(PARAM_PLUGIN, "Module type (e.g. 'forum', 'assign')", VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Section NUMBER (0 = General)', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Activity name', VALUE_REQUIRED),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, 1),
            'content' => new external_value(
                PARAM_RAW,
                "Main body (HTML) for content-based modules like 'page' (the intro is only the description)",
                VALUE_DEFAULT,
                null
            ),
            'externalurl' => new external_value(PARAM_RAW,
                "URL-module only: the external http(s) address the resource opens", VALUE_DEFAULT, null),
            'display' => new external_value(PARAM_INT,
                'URL-module only: display mode (0 auto, 1 embed, 3 new window, 5 open, 6 popup)',
                VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Create the module.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $sectionnum
     * @param string $name
     * @param string $intro
     * @param int $visible
     * @param string|null $content
     * @param string|null $externalurl
     * @param int|null $display
     * @return array
     */
    public static function execute(
        int $courseid,
        string $modname,
        int $sectionnum,
        string $name,
        string $intro = '',
        int $visible = 1,
        ?string $content = null,
        ?string $externalurl = null,
        ?int $display = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        [
            'courseid' => $courseid,
            'modname' => $modname,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'intro' => $intro,
            'visible' => $visible,
            'content' => $content,
            'externalurl' => $externalurl,
            'display' => $display,
        ] = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'modname' => $modname,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'intro' => $intro,
            'visible' => $visible,
            'content' => $content,
            'externalurl' => $externalurl,
            'display' => $display,
        ]);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        if (($externalurl !== null || $display !== null) && $modname !== 'url') {
            \local_mcpconnector\local\reject::because(
                "externalurl/display are only for mod_url (got '{$modname}')"
            );
        }
        if ($modname === 'url') {
            // A url resource without a destination is useless — require it.
            $externalurl = trim((string) $externalurl);
            if ($externalurl === '' || !preg_match('~^https?://~i', $externalurl)
                    || clean_param($externalurl, PARAM_URL) !== $externalurl) {
                \local_mcpconnector\local\reject::because(
                    'externalurl must be a valid http(s) URL (required for mod_url)'
                );
            }
        }

        $extra = $content !== null ? ['content' => $content, 'contentformat' => FORMAT_HTML] : [];
        if ($modname === 'url') {
            $extra['externalurl'] = $externalurl;
            if ($display !== null) {
                $extra['display'] = $display;
            }
        }
        $created = self::add($course, $modname, $sectionnum, $name, $intro, $visible, $extra);

        return [
            'cmid' => (int) $created->coursemodule,
            'instanceid' => (int) $created->instance,
        ];
    }

    /**
     * Shared creation helper (also used by create_scorm).
     *
     * @param \stdClass $course
     * @param string $modname
     * @param int $sectionnum
     * @param string $name
     * @param string $intro
     * @param int $visible
     * @param array $extra Module-specific fields merged over the defaults.
     * @return \stdClass The moduleinfo returned by add_moduleinfo (with coursemodule/instance).
     */
    public static function add(
        \stdClass $course,
        string $modname,
        int $sectionnum,
        string $name,
        string $intro,
        int $visible,
        array $extra = []
    ): \stdClass {
        global $DB, $PAGE;

        $module = $DB->get_record('modules', ['name' => $modname], '*', MUST_EXIST);
        course_create_sections_if_missing($course, $sectionnum);

        $coursecontext = \context_course::instance($course->id);
        $PAGE->set_context($coursecontext);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = $modname;
        $moduleinfo->module = $module->id;
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnum;
        $moduleinfo->name = $name;
        $moduleinfo->visible = $visible ? 1 : 0;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        if (plugin_supports('mod', $modname, FEATURE_MOD_INTRO, true)) {
            $moduleinfo->introeditor = [
                'text' => $intro,
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ];
        }
        foreach ((self::MODULE_DEFAULTS[$modname] ?? []) as $field => $value) {
            $moduleinfo->$field = $value;
        }
        foreach ($extra as $field => $value) {
            $moduleinfo->$field = $value;
        }

        return add_moduleinfo($moduleinfo, $course, null);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'New course module id'),
            'instanceid' => new external_value(PARAM_INT, 'New module instance id'),
        ]);
    }
}
