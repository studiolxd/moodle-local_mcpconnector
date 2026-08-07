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
 * Award a badge to a user. Fills a real core gap: badges have webservices to
 * READ (core_badges_get_user_badges) but none to AWARD — manual awarding is
 * form-only. Uses the same core path as the manual-award UI (badge::issue),
 * so events, criteria locking and privacy settings all apply.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue_badge extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'badgeid' => new external_value(PARAM_INT, 'Badge id (from the badges list)', VALUE_REQUIRED),
            'userid' => new external_value(PARAM_INT, 'User to award the badge to', VALUE_REQUIRED),
        ]);
    }

    /**
     * Issue the badge.
     *
     * @param int $badgeid
     * @param int $userid
     * @return array
     */
    public static function execute(int $badgeid, int $userid): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/badgeslib.php');

        [
            'badgeid' => $badgeid,
            'userid' => $userid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'badgeid' => $badgeid,
            'userid' => $userid,
        ]);

        if (empty($CFG->enablebadges)) {
            \local_mcpconnector\local\reject::because('badges are disabled site-wide (enablebadges)');
        }

        $badge = new \core_badges\badge($badgeid);
        $context = $badge->get_context();

        self::validate_context($context);
        require_capability('moodle/badges:awardbadge', $context);

        if (!$badge->is_active()) {
            \local_mcpconnector\local\reject::because(
                "badge {$badgeid} is not active — enable ('activate') it in its badge settings first"
            );
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', IGNORE_MISSING);
        if (!$user) {
            \local_mcpconnector\local\reject::because("user {$userid} not found");
        }

        if ($badge->is_issued($userid)) {
            // Idempotent: awarding twice is a no-op, not an error.
            return ['success' => true, 'badgeid' => $badgeid, 'userid' => $userid, 'alreadyissued' => true];
        }

        $badge->issue($userid);

        return ['success' => true, 'badgeid' => $badgeid, 'userid' => $userid, 'alreadyissued' => false];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the badge is now issued'),
            'badgeid' => new external_value(PARAM_INT, 'Badge id'),
            'userid' => new external_value(PARAM_INT, 'User id'),
            'alreadyissued' => new external_value(PARAM_BOOL, 'True when the user already had the badge'),
        ]);
    }
}
