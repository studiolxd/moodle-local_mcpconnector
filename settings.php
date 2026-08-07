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
 * Admin settings registration for Moodle MCP.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The dedicated capability gates every page; also let a holder without site:config
// (e.g. a manager) see the category and pages in the admin tree.
$managecap = 'local/mcpconnector:manage';
if ($hassiteconfig || has_capability($managecap, context_system::instance())) {
    // Categoría "Moodle MCP" (título sin link).
    $ADMIN->add('localplugins', new admin_category(
        'local_mcpconnector_category',
        get_string('pluginname', 'local_mcpconnector')
    ));

    // Licencia.
    $ADMIN->add('local_mcpconnector_category', new admin_externalpage(
        'local_mcpconnector',
        get_string('tab_license', 'local_mcpconnector'),
        new moodle_url('/local/mcpconnector/index.php'),
        $managecap
    ));

    // Servicios.
    $ADMIN->add('local_mcpconnector_category', new admin_externalpage(
        'local_mcpconnector_services',
        get_string('tab_services', 'local_mcpconnector'),
        new moodle_url('/local/mcpconnector/services.php'),
        $managecap
    ));

    // Usuarios.
    $ADMIN->add('local_mcpconnector_category', new admin_externalpage(
        'local_mcpconnector_users',
        get_string('tab_users', 'local_mcpconnector'),
        new moodle_url('/local/mcpconnector/users.php'),
        $managecap
    ));

    // Claves.
    $ADMIN->add('local_mcpconnector_category', new admin_externalpage(
        'local_mcpconnector_keys',
        get_string('tab_keys', 'local_mcpconnector'),
        new moodle_url('/local/mcpconnector/keys.php'),
        $managecap
    ));

    // Salud.
    $ADMIN->add('local_mcpconnector_category', new admin_externalpage(
        'local_mcpconnector_health',
        get_string('tab_health', 'local_mcpconnector'),
        new moodle_url('/local/mcpconnector/health.php'),
        $managecap
    ));

    // Configuración.
    $ADMIN->add('local_mcpconnector_category', new admin_externalpage(
        'local_mcpconnector_settings',
        get_string('tab_settings', 'local_mcpconnector'),
        new moodle_url('/local/mcpconnector/settings_page.php'),
        $managecap
    ));
}
