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

namespace local_mcpconnector;

/**
 * Event observer for the MCP Connector.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Loads the plugin library. Done per handler, not at file scope: this
     * class is autoloaded (included inside core_component's classloader),
     * where $CFG is out of scope — a top-level require would fatal.
     */
    protected static function require_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
    }

    /**
     * Triggered when a role is assigned to a user.
     *
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $DB;
        self::require_lib();
        $userid = $event->relateduserid;
        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);

        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return;
        }

        // Determine which services this role maps to.
        $effectiveroles = local_mcpconnector_get_effective_roles($userid);
        foreach ($effectiveroles as $role) {
            $service = local_mcpconnector_service_for_role($role);

            // Check if auto-sync is enabled for this specific service.
            if (!local_mcpconnector_is_auto_sync_enabled_for_service($service)) {
                continue;
            }

            // Queue a sync task to avoid blocking the web request.
            $task = new \local_mcpconnector\task\sync_user_adhoc();
            $task->set_custom_data(['userid' => $userid]);
            $task->set_component('local_mcpconnector');
            \core\task\manager::queue_adhoc_task($task, true);
            break; // Only need to sync once.
        }
    }

    /**
     * Triggered when a role is unassigned from a user.
     *
     * @param \core\event\role_unassigned $event
     */
    public static function role_unassigned(\core\event\role_unassigned $event) {
        global $DB;
        self::require_lib();
        $userid = $event->relateduserid;
        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);

        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return;
        }

        // Check if ANY MCP service has auto-sync enabled.
        // We need to check all services because we might need to remove a service
        // even if the user no longer has that role.
        $definitions = local_mcpconnector_get_service_definitions();
        $hasanyautosync = false;

        foreach ($definitions as $def) {
            if (local_mcpconnector_is_auto_sync_enabled_for_service($def['shortname'])) {
                $hasanyautosync = true;
                break;
            }
        }

        // Only sync if at least one MCP service has auto-sync enabled.
        if ($hasanyautosync) {
            // Queue a removal-only sync to avoid blocking the web request.
            $task = new \local_mcpconnector\task\sync_user_adhoc();
            $task->set_custom_data(['userid' => $userid, 'remove_only' => true]);
            $task->set_component('local_mcpconnector');
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }

    /**
     * Triggered when a new user is created.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        self::require_lib();
        // Check if auto-sync for 'user' service is enabled.
        if (!local_mcpconnector_is_auto_sync_enabled_for_service('mcpconnector_user')) {
            return;
        }

        global $DB;
        $userid = $event->objectid;
        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);

        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return;
        }

        // Assign user to the 'user' service asynchronously.
        $task = new \local_mcpconnector\task\sync_user_adhoc();
        $task->set_custom_data(['userid' => $userid, 'servicefilter' => 'mcpconnector_user']);
        $task->set_component('local_mcpconnector');
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Triggered when a user is deleted.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        $userid = $event->objectid;

        // Delete all MCP service assignments and keys for this user asynchronously.
        $task = new \local_mcpconnector\task\delete_user_keys_adhoc();
        $task->set_custom_data(['userid' => $userid]);
        $task->set_component('local_mcpconnector');
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
