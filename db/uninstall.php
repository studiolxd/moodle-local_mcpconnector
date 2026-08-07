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
 * Uninstall cleanup for Moodle MCP.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executed on plugin uninstall.
 *
 * @return bool
 */
function xmldb_local_mcpconnector_uninstall() {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/lib/accesslib.php');
    require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
    require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');

    // Best-effort: an unreachable panel must never block the uninstall. Errors
    // are already logged with debugging() inside the shared helper.
    local_mcpconnector_deprovision_resources('uninstall');

    // Clean up plugin config.
    unset_all_config_for_plugin('local_mcpconnector');

    if ($DB->get_manager()->table_exists('task_scheduled')) {
        $DB->delete_records('task_scheduled', ['component' => 'local_mcpconnector']);
    }
    if ($DB->get_manager()->table_exists('task_adhoc')) {
        $DB->delete_records('task_adhoc', ['component' => 'local_mcpconnector']);
    }

    return true;
}
