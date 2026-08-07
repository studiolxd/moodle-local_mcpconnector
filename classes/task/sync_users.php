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
 * Scheduled task to sync MoodleMCP users and keys.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\task;

use core\task\scheduled_task;

/**
 * Scheduled task to sync users and keys with the panel.
 */
class sync_users extends scheduled_task {
    /**
     * Returns the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_users', 'local_mcpconnector');
    }

    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        // Telemetry rides this task (opt-in, self-throttled to ~daily) and is
        // independent of auto-sync — send it before the auto-sync early-return.
        try {
            $telemetry = local_mcpconnector_send_telemetry();
            if ($telemetry['ok'] && empty($telemetry['skipped'])) {
                mtrace('MoodleMCP: telemetry sent.');
            }
        } catch (\Throwable $e) {
            mtrace('MoodleMCP: telemetry failed: ' . $e->getMessage());
        }

        // Only provision to services whose per-service auto-sync flag is on. Passing the
        // enabled set down prevents mass-provisioning users to flag-off services.
        $enabledservices = local_mcpconnector_get_auto_sync_enabled_services();
        if (empty($enabledservices)) {
            return;
        }

        $result = local_mcpconnector_sync_all_users(null, $enabledservices);
        if (!$result['ok']) {
            mtrace('MoodleMCP: sync skipped (invalid license).');
            return;
        }
        mtrace('MoodleMCP: synced users: ' . $result['synced'] . ', revoked keys: ' . $result['revoked']);
    }
}
