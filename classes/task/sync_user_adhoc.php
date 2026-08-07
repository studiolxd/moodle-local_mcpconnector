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
 * Ad-hoc task to sync a single user.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\task;

use core\task\adhoc_task;

/**
 * Ad-hoc task that reconciles a single user's MoodleMCP services and key.
 */
class sync_user_adhoc extends adhoc_task {
    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        $data = $this->get_custom_data();
        $userid = isset($data->userid) ? (int) $data->userid : 0;
        if (!$userid) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return;
        }

        $servicefilter = null;
        if (isset($data->servicefilter) && is_string($data->servicefilter) && $data->servicefilter !== '') {
            $servicefilter = $data->servicefilter;
        }

        if ($servicefilter !== null) {
            $valid = array_column(local_mcpconnector_get_service_definitions(), 'shortname');
            if (!in_array($servicefilter, $valid, true)) {
                return;
            }
        }

        $removeonly = !empty($data->remove_only);

        local_mcpconnector_ensure_services();

        // On the unscoped (full-sync) automatic path, restrict provisioning to services
        // whose auto-sync flag is on — parity with the scheduled task. A scoped filter
        // (e.g. user_created) is already gated by the observer, so leave it unrestricted.
        $enabledservices = null;
        if ($servicefilter === null) {
            $enabledservices = local_mcpconnector_get_auto_sync_enabled_services();
        }

        $res = local_mcpconnector_sync_user_auto($user, $servicefilter, $removeonly, $enabledservices);
        if ($res && isset($res['ok']) && $res['ok'] === false) {
            // Throw so Moodle retries with backoff; revoke is idempotent and create is
            // guarded by the keep-as-is branch, so a retry cannot double-mint.
            throw new \moodle_exception('taskfailed', 'local_mcpconnector', '', null, (string) ($res['error'] ?? 'unknown'));
        }
    }
}
