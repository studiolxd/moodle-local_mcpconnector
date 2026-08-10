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
 * Replace a SCORM's package and/or its mod_scorm settings.
 *
 * Fills a Moodle core gap (and this plugin's own, until now): create_scorm
 * only creates, so the only way to fix a published SCORM's content was to
 * delete the activity and recreate it — losing its cmid (breaking direct
 * links/bookmarks), student attempts and tracking (mod_scorm_get_scorm_*), and
 * any settings not reproducible through create_scorm's parameters. This uses
 * the SAME update_moduleinfo() path local_mcpconnector_update_module uses for
 * every other module: the cmid/instance never change, only the fields passed
 * are touched.
 *
 * Verified against Moodle 5.2 core (mod/scorm/lib.php::scorm_update_instance,
 * mod/scorm/datamodels/scormlib.php::scorm_parse_scorm — see
 * scorm_update_test.php): replacing packagefile makes scorm_update_instance()
 * re-run scorm_parse() on the new manifest, bumping scorm.revision and
 * recomputing sha1hash. Scoes are RECONCILED by manifest <item identifier>,
 * not blindly recreated — a SCO whose identifier is unchanged KEEPS its
 * scorm_scoes.id (core's own comment: "keep id so that user tracks are kept
 * against the same ids"), so scorm_scoes_track (student attempts) stays
 * attached across a package replace as long as the corrected package reuses
 * the same item identifiers. Only a SCO whose identifier is REMOVED from the
 * new manifest gets deleted (and its tracks with it) — i.e. fixing content
 * is safe, restructuring the SCO tree is not free.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_scorm extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the SCORM to update', VALUE_REQUIRED),
            'packageurl' => new external_value(
                PARAM_URL,
                'HTTPS URL of the replacement SCORM .zip package. Omit to leave the current package untouched.',
                VALUE_DEFAULT,
                null
            ),
        ] + create_scorm::settings_parameters());
    }

    /**
     * Replace the package and/or apply settings. Combine with
     * local_mcpconnector_update_module for name/intro/visible/completion —
     * this function only touches the package and mod_scorm-specific settings.
     *
     * @param int $cmid
     * @param string|null $packageurl
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
        int $cmid,
        ?string $packageurl = null,
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
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir . '/filelib.php');

        $args = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'packageurl' => $packageurl,
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
        ]);
        $cmid = $args['cmid'];
        $packageurl = $args['packageurl'];

        $cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $modcontext = \context_module::instance($cm->id);

        self::validate_context($modcontext);
        require_capability('moodle/course:manageactivities', $modcontext);

        create_scorm::validate_settings($args);

        if ($packageurl === null) {
            $settingskeys = array_keys(create_scorm::settings_parameters());
            $touchesany = false;
            foreach ($settingskeys as $key) {
                if ($args[$key] !== null) {
                    $touchesany = true;
                    break;
                }
            }
            if (!$touchesany) {
                \local_mcpconnector\local\reject::because(
                    'nothing to update — pass packageurl and/or at least one setting'
                );
            }
        }

        // Download + validate the replacement package BEFORE touching the
        // instance — a rejected download must leave the current SCORM intact.
        $draftitemid = $packageurl !== null
            ? \local_mcpconnector\local\scorm_package::fetch_to_draft($packageurl)
            : null;

        // Load exactly as the settings form would (same helper update_module
        // uses) — everything not overridden below keeps its current value.
        $PAGE->set_context($modcontext);
        [$cm, , , $data] = get_moduleinfo_data($cm, $course);

        if ($draftitemid !== null) {
            $data->packagefile = $draftitemid;
        }
        foreach (create_scorm::settings_parameters() as $key => $unused) {
            if ($args[$key] !== null) {
                $data->$key = $args[$key];
            }
        }

        update_moduleinfo($cm, $data, $course, null);

        return [
            'success' => true,
            'cmid' => $cm->id,
            'instanceid' => (int) $cm->instance,
            'packagereplaced' => $draftitemid !== null,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update was applied'),
            'cmid' => new external_value(PARAM_INT, 'Course module id (unchanged)'),
            'instanceid' => new external_value(PARAM_INT, 'SCORM instance id (unchanged)'),
            'packagereplaced' => new external_value(PARAM_BOOL, 'Whether the .zip package was replaced'),
        ]);
    }
}
