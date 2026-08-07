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
 * Privacy API provider for Moodle MCP.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_system;

/**
 * Privacy provider for local_mcpconnector.
 *
 * The plugin stores MCP key METADATA locally (local_mcpconnector_keys: panel key
 * id, last4, roles, status — never key values or tokens) and sends user data
 * to the external MCP panel service. Moodle core tables it drives
 * (external_tokens, external_services_users) are handled by the core_external
 * privacy provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about user data managed by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_mcpconnector_keys', [
            'userid' => 'privacy:metadata:localkeys:userid',
            'panelkeyid' => 'privacy:metadata:localkeys:panelkeyid',
            'keylast4' => 'privacy:metadata:localkeys:keylast4',
            'roles' => 'privacy:metadata:localkeys:roles',
            'status' => 'privacy:metadata:localkeys:status',
            'sentat' => 'privacy:metadata:localkeys:sentat',
        ], 'privacy:metadata:localkeys');

        $collection->add_external_location_link('moodlemcp.com', [
            'userid' => 'privacy:metadata:moodlemcp:userid',
            'token' => 'privacy:metadata:moodlemcp:token',
            'roles' => 'privacy:metadata:moodlemcp:roles',
            'email' => 'privacy:metadata:moodlemcp:email',
            'firstname' => 'privacy:metadata:moodlemcp:firstname',
            'lastname' => 'privacy:metadata:moodlemcp:lastname',
        ], 'privacy:metadata:moodlemcp');

        return $collection;
    }

    /**
     * Key rows live at system context — return it when the user has any.
     *
     * @param int $userid The user to search.
     * @return contextlist The contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_mcpconnector_keys', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Adds every user with key metadata when the system context is queried.
     *
     * @param userlist $userlist The userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_mcpconnector_keys}',
            []
        );
    }

    /**
     * Exports the user's key metadata under the plugin path.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }
            $rows = $DB->get_records(
                'local_mcpconnector_keys',
                ['userid' => $contextlist->get_user()->id]
            );
            if (!$rows) {
                continue;
            }
            $data = array_values(array_map(static function ($row) {
                return (object) [
                    'panelkeyid' => $row->panelkeyid,
                    'keylast4' => $row->keylast4,
                    'roles' => $row->roles,
                    'status' => $row->status,
                    'sentat' => $row->sentat ? userdate($row->sentat) : null,
                    'timecreated' => userdate($row->timecreated),
                ];
            }, $rows));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_mcpconnector')],
                (object) ['keys' => $data]
            );
        }
    }

    /**
     * Deletes every key row when the system context is wiped.
     *
     * @param context $context The context to delete for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('local_mcpconnector_keys');
        }
    }

    /**
     * Deletes the user's key rows.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $userid = (int) $contextlist->get_user()->id;
                self::revoke_external_keys($userid);
                $DB->delete_records('local_mcpconnector_keys', ['userid' => $userid]);
            }
        }
    }

    /**
     * Best-effort revocation of the user's keys on the external MCP panel.
     *
     * A privacy request must not fail if the panel is unreachable, so the panel
     * error is swallowed here — the local rows are removed either way.
     *
     * @param int $userid
     */
    protected static function revoke_external_keys(int $userid) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        try {
            local_mcpconnector_delete_user_keys($userid);
        } catch (\Throwable $e) {
            debugging('local_mcpconnector: privacy deletion could not revoke panel keys for user '
                . $userid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Deletes key rows for the approved users.
     *
     * @param approved_userlist $userlist The approved users to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if ($userids) {
            foreach ($userids as $userid) {
                self::revoke_external_keys((int) $userid);
            }
            [$insql, $params] = $DB->get_in_or_equal($userids);
            $DB->delete_records_select('local_mcpconnector_keys', "userid $insql", $params);
        }
    }
}
