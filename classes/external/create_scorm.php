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
 * Create a SCORM activity downloading its package from an HTTPS URL.
 *
 * Fills a Moodle core gap: mod_scorm's webservices are read/tracking only —
 * nothing can create a SCORM or upload its package. The download uses
 * Moodle's own curl wrapper (curl_security_helper applies, blocking internal
 * hosts), the zip must contain an imsmanifest.xml, and the instance is built
 * through add_moduleinfo() with the site's mod_scorm defaults (overridable —
 * see execute_parameters), so scorm_parse validates and deploys the manifest
 * exactly like a form upload.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class create_scorm extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
            'sectionnum' => new external_value(PARAM_INT, 'Section NUMBER (0 = General)', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Activity name', VALUE_REQUIRED),
            'packageurl' => new external_value(PARAM_URL, 'HTTPS URL of the SCORM .zip package', VALUE_REQUIRED),
            'intro' => new external_value(PARAM_RAW, 'Description (HTML)', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_INT, 'Visibility: 1 visible, 0 hidden', VALUE_DEFAULT, 1),
        ] + self::settings_parameters());
    }

    /**
     * mod_scorm settings shared by create_scorm and update_scorm, all optional
     * (omit to fall back to the site's mod_scorm defaults — the same ones the
     * settings form pre-fills). Shared so both stay in sync; see
     * settings_from_params() for how each maps onto the moduleinfo/$data object.
     *
     * @return array
     */
    public static function settings_parameters(): array {
        return [
            'grademethod' => new external_value(
                PARAM_INT,
                'Grading method: 0 highest grade of any SCO, 1 highest overall score, '
                . '2 average of all SCOs, 3 sum of all SCOs. Default: site default (0).',
                VALUE_DEFAULT,
                null
            ),
            'maxgrade' => new external_value(
                PARAM_INT,
                'Maximum grade (points). Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'whatgrade' => new external_value(
                PARAM_INT,
                'Which attempt counts: 0 highest, 1 average, 2 first, 3 last. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'maxattempt' => new external_value(
                PARAM_INT,
                'Maximum number of attempts (0 = unlimited). Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'forcenewattempt' => new external_value(
                PARAM_INT,
                'Force new attempt: 0 no, 1 always, 2 only if the previous was incomplete. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'forcecompleted' => new external_value(
                PARAM_INT,
                'Force completed: 1 the attempt is marked completed as soon as it starts, 0 off. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'lastattemptlock' => new external_value(
                PARAM_INT,
                'Lock after final attempt: 1 yes, 0 no. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'masteryoverride' => new external_value(
                PARAM_INT,
                "Mastery score overrides 'status' (if the SCO sets cmi.core.lesson_status, a mastery "
                . "score can still force completed/passed): 1 yes, 0 no. Default: site default.",
                VALUE_DEFAULT,
                null
            ),
            'auto' => new external_value(
                PARAM_INT,
                'Auto-continue to the next SCO: 1 yes, 0 no. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'popup' => new external_value(
                PARAM_INT,
                'Display package: 0 current window (default), 1 new pop-up window (uses width/height).',
                VALUE_DEFAULT,
                null
            ),
            'width' => new external_value(
                PARAM_INT,
                'Pop-up/frame width in pixels. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'height' => new external_value(
                PARAM_INT,
                'Pop-up/frame height in pixels. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'skipview' => new external_value(
                PARAM_INT,
                'Skip content page (single-SCO packages): 0 never, 1 only the first time, 2 always. '
                . 'Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'hidebrowse' => new external_value(
                PARAM_INT,
                "Hide the 'Browse' mode button: 1 yes, 0 no. Default: site default.",
                VALUE_DEFAULT,
                null
            ),
            'displaycoursestructure' => new external_value(
                PARAM_INT,
                'Show the course structure (table of contents) on the entry page: 1 yes, 0 no. '
                . 'Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'hidetoc' => new external_value(
                PARAM_INT,
                'Table of contents display: 0 show, 1 hide but show navigation, 2 hide with floating menu, '
                . '3 hide everything (disable navigation). Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'nav' => new external_value(
                PARAM_INT,
                'Navigation buttons: 0 none, 1 show under content, 2 floating. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'navpositionleft' => new external_value(
                PARAM_INT,
                'Floating navigation X position in pixels (only when nav=2). Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'navpositiontop' => new external_value(
                PARAM_INT,
                'Floating navigation Y position in pixels (only when nav=2). Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'displayattemptstatus' => new external_value(
                PARAM_INT,
                'Attempt/grade status visible to the student: 0 no, 1 my attempts, 2 all attempts, '
                . '3 entire course. Default: site default.',
                VALUE_DEFAULT,
                null
            ),
            'timeopen' => new external_value(
                PARAM_INT,
                'Open date (unix timestamp; 0 = none). Default: 0.',
                VALUE_DEFAULT,
                null
            ),
            'timeclose' => new external_value(
                PARAM_INT,
                'Close date (unix timestamp; 0 = none). Default: 0.',
                VALUE_DEFAULT,
                null
            ),
        ];
    }

    /** Grading methods accepted by grademethod (GRADESCOES..GRADESUM). */
    private const GRADE_METHODS = [0, 1, 2, 3];
    /** Attempt-selection methods accepted by whatgrade. */
    private const WHAT_GRADE = [0, 1, 2, 3];
    /** Values accepted by forcenewattempt: 0 no, 1 always, 2 only if incomplete. */
    private const FORCE_NEW_ATTEMPT = [0, 1, 2];
    /** Values accepted by displayattemptstatus: 0 no, 1 mine, 2 all, 3 course. */
    private const DISPLAY_ATTEMPT_STATUS = [0, 1, 2, 3];
    /** Values accepted by hidetoc: 0 show, 1 hide, 2 floating menu, 3 hide all. */
    private const HIDE_TOC = [0, 1, 2, 3];
    /** Values accepted by nav: 0 none, 1 under content, 2 floating. */
    private const NAV = [0, 1, 2];
    /** Values accepted by skipview: 0 never, 1 first time only, 2 always. */
    private const SKIPVIEW = [0, 1, 2];

    /**
     * Validates the enum-like settings shared by create_scorm/update_scorm.
     * Booleans (0/1 fields) are left to the caller's discretion — Moodle
     * itself treats any truthy int as 1 — but out-of-range enums fail fast
     * with a caller-visible reason instead of silently landing on a value
     * mod_scorm never intended.
     *
     * @param array $s Raw settings, as validated by execute_parameters().
     * @return void
     */
    public static function validate_settings(array $s): void {
        $checks = [
            'grademethod' => self::GRADE_METHODS,
            'whatgrade' => self::WHAT_GRADE,
            'forcenewattempt' => self::FORCE_NEW_ATTEMPT,
            'displayattemptstatus' => self::DISPLAY_ATTEMPT_STATUS,
            'hidetoc' => self::HIDE_TOC,
            'nav' => self::NAV,
            'skipview' => self::SKIPVIEW,
        ];
        foreach ($checks as $key => $allowed) {
            if ($s[$key] !== null && !in_array($s[$key], $allowed, true)) {
                \local_mcpconnector\local\reject::because(
                    "{$key} must be one of: " . implode(', ', $allowed) . " (got '{$s[$key]}')"
                );
            }
        }
    }

    /**
     * Merges the caller's non-null settings over the site's mod_scorm
     * defaults — the same array shape create_module::add()/update_moduleinfo
     * expect (field names match the scorm DB table exactly).
     *
     * @param array $s Raw settings, as validated by execute_parameters().
     * @param \stdClass $cfg get_config('scorm').
     * @return array
     */
    public static function settings_from_params(array $s, \stdClass $cfg): array {
        $defaults = [
            'width' => $cfg->framewidth,
            'height' => $cfg->frameheight,
            'skipview' => $cfg->skipview,
            'hidebrowse' => $cfg->hidebrowse,
            'displaycoursestructure' => $cfg->displaycoursestructure,
            'hidetoc' => $cfg->hidetoc,
            'nav' => $cfg->nav,
            'navpositionleft' => $cfg->navpositionleft,
            'navpositiontop' => $cfg->navpositiontop,
            'displayattemptstatus' => $cfg->displayattemptstatus,
            'timeopen' => 0,
            'timeclose' => 0,
            // Unlike every other field here, the site has no admin setting for
            // this — mod_scorm's grading method is form-only. GRADESCOES (0,
            // "highest grade of any SCO") was this plugin's only option before
            // this parameter existed; kept as the default for callers who omit it.
            'grademethod' => \GRADESCOES,
            'maxgrade' => $cfg->maxgrade,
            'maxattempt' => $cfg->maxattempt,
            'whatgrade' => $cfg->whatgrade,
            'forcenewattempt' => $cfg->forcenewattempt,
            'lastattemptlock' => $cfg->lastattemptlock,
            'forcecompleted' => $cfg->forcecompleted,
            'masteryoverride' => $cfg->masteryoverride,
            'auto' => $cfg->auto,
            'popup' => 0,
        ];
        $out = $defaults;
        foreach ($s as $key => $value) {
            if ($value !== null && array_key_exists($key, $out)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * Create the SCORM.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $name
     * @param string $packageurl
     * @param string $intro
     * @param int $visible
     * @param int|null $grademethod
     * @param int|null $maxgrade
     * @param int|null $whatgrade
     * @param int|null $maxattempt
     * @param int|null $forcenewattempt
     * @param int|null $forcecompleted
     * @param int|null $lastattemptlock
     * @param int|null $masteryoverride
     * @param int|null $auto
     * @param int|null $popup
     * @param int|null $width
     * @param int|null $height
     * @param int|null $skipview
     * @param int|null $hidebrowse
     * @param int|null $displaycoursestructure
     * @param int|null $hidetoc
     * @param int|null $nav
     * @param int|null $navpositionleft
     * @param int|null $navpositiontop
     * @param int|null $displayattemptstatus
     * @param int|null $timeopen
     * @param int|null $timeclose
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sectionnum,
        string $name,
        string $packageurl,
        string $intro = '',
        int $visible = 1,
        ?int $grademethod = null,
        ?int $maxgrade = null,
        ?int $whatgrade = null,
        ?int $maxattempt = null,
        ?int $forcenewattempt = null,
        ?int $forcecompleted = null,
        ?int $lastattemptlock = null,
        ?int $masteryoverride = null,
        ?int $auto = null,
        ?int $popup = null,
        ?int $width = null,
        ?int $height = null,
        ?int $skipview = null,
        ?int $hidebrowse = null,
        ?int $displaycoursestructure = null,
        ?int $hidetoc = null,
        ?int $nav = null,
        ?int $navpositionleft = null,
        ?int $navpositiontop = null,
        ?int $displayattemptstatus = null,
        ?int $timeopen = null,
        ?int $timeclose = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        // SCORM_UPDATE_NEVER and GRADESCOES are defined in locallib.php, not
        // lib.php (only SCORM_TYPE_LOCAL lives there).
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        require_once($CFG->libdir . '/filelib.php');

        $args = [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'packageurl' => $packageurl,
            'intro' => $intro,
            'visible' => $visible,
            'grademethod' => $grademethod,
            'maxgrade' => $maxgrade,
            'whatgrade' => $whatgrade,
            'maxattempt' => $maxattempt,
            'forcenewattempt' => $forcenewattempt,
            'forcecompleted' => $forcecompleted,
            'lastattemptlock' => $lastattemptlock,
            'masteryoverride' => $masteryoverride,
            'auto' => $auto,
            'popup' => $popup,
            'width' => $width,
            'height' => $height,
            'skipview' => $skipview,
            'hidebrowse' => $hidebrowse,
            'displaycoursestructure' => $displaycoursestructure,
            'hidetoc' => $hidetoc,
            'nav' => $nav,
            'navpositionleft' => $navpositionleft,
            'navpositiontop' => $navpositiontop,
            'displayattemptstatus' => $displayattemptstatus,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
        ];
        $args = self::validate_parameters(self::execute_parameters(), $args);
        [
            'courseid' => $courseid,
            'sectionnum' => $sectionnum,
            'name' => $name,
            'packageurl' => $packageurl,
            'intro' => $intro,
            'visible' => $visible,
        ] = $args;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);

        self::validate_context($coursecontext);
        require_capability('moodle/course:manageactivities', $coursecontext);

        self::validate_settings($args);

        // Download + SSRF guard + size cap + draft staging + the SCORM shape
        // check (zip must contain imsmanifest.xml) live in scorm_package.
        $draftitemid = \local_mcpconnector\local\scorm_package::fetch_to_draft($packageurl);

        // Site defaults for mod_scorm — the same set the settings form
        // applies — overridden by any setting the caller passed.
        $cfg = get_config('scorm');
        $settings = self::settings_from_params($args, $cfg);
        $created = create_module::add($course, 'scorm', $sectionnum, $name, $intro, $visible, [
            'scormtype' => \SCORM_TYPE_LOCAL,
            'packagefile' => $draftitemid,
            'packageurl' => '',
            'updatefreq' => \SCORM_UPDATE_NEVER,
        ] + $settings);

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
            'instanceid' => new external_value(PARAM_INT, 'New SCORM course module instance id'),
        ]);
    }
}
