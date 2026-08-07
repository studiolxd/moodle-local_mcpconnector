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
 * Ad-hoc task to sync all users for a service (or all services).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\task;

use core\task\adhoc_task;

/**
 * Ad-hoc task that syncs all users for a service (or every service).
 */
class sync_all_users_adhoc extends adhoc_task {
    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        $data = $this->get_custom_data();
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

        $res = local_mcpconnector_sync_all_users($servicefilter);
        if (empty($res['ok'])) {
            // Throw so Moodle retries with backoff (e.g. invalid license, transient panel error).
            throw new \moodle_exception('taskfailed', 'local_mcpconnector', '', null, 'invalid_license');
        }
        if (!empty($res['first_error'])) {
            throw new \moodle_exception('taskfailed', 'local_mcpconnector', '', null, (string) $res['first_error']);
        }
    }
}
