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
 * Install script for Moodle MCP
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executed on installation of Moodle MCP
 *
 * @return bool
 */
function xmldb_local_mcpconnector_install() {
    global $CFG;

    require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

    local_mcpconnector_ensure_services();
    local_mcpconnector_sync_all_service_functions();

    set_config('license_status', 'missing', 'local_mcpconnector');
    set_config('auto_email', 0, 'local_mcpconnector');
    set_config('email_subject', get_string('email_subject_default', 'local_mcpconnector'), 'local_mcpconnector');
    set_config('email_body', get_string('email_body_default', 'local_mcpconnector'), 'local_mcpconnector');

    return true;
}
