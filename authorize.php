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
 * OAuth authorize endpoint for the Moodle-identity MCP flow (Fase C).
 *
 * The panel's /mcp/login ("sign in with your Moodle") redirects the browser here
 * with a panel-signed `state`. We authenticate the user with require_login()
 * (native login OR the site's configured SSO), mint their role-scoped webservice
 * token, and deliver it to the panel over the existing signed server-to-server
 * channel (never through the browser), then bounce the browser to the panel's
 * one-time callback to complete the OAuth flow.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Resolve config.php via the web document root (works for both the legacy and
// 5.x `public/` layouts and when the plugin is symlinked); fall back to the
// relative path for non-symlinked installs.
require_once(
    (!empty($_SERVER['DOCUMENT_ROOT'])
        && is_file(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php'))
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php'
        : __DIR__ . '/../../config.php'
);
require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

// Authenticate: native login or the site's SSO, whichever this Moodle uses.
require_login(0, false);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mcpconnector/authorize.php'));

$state = required_param('state', PARAM_RAW);

$fail = function (string $msg): void {
    throw new \moodle_exception('generalexceptionmessage', 'error', '', 'MCP authorize: ' . $msg);
};

// Verify the panel-signed state (authenticity + not expired).
$panelsecret = local_mcpconnector_get_panel_secret();
if ($panelsecret === '' || !local_mcpconnector_verify_oauth_state($state, $panelsecret)) {
    $fail('invalid or expired request');
}

$userid = (int) $USER->id;

// Effective role -> target role-scoped service; refuse if not eligible.
$roles = local_mcpconnector_get_effective_roles($userid);
$primaryrole = local_mcpconnector_primary_role_for_roles($roles);
$shortname = local_mcpconnector_service_for_role($primaryrole);

if (!local_mcpconnector_user_is_eligible_for_service($userid, $shortname)) {
    $fail('not eligible for the MCP service');
}

$idmap = local_mcpconnector_get_service_id_map();
$serviceid = isset($idmap[$shortname]) ? (int) $idmap[$shortname] : 0;
if ($serviceid <= 0) {
    $fail('MCP service is not provisioned');
}

// Ensure the user is authorised for the service, then mint (or reuse) their token.
local_mcpconnector_authorize_user_for_service($userid, $serviceid);
$token = local_mcpconnector_get_user_service_token($userid, $serviceid);
if (!$token) {
    $token = local_mcpconnector_rotate_user_token($userid, $serviceid, 0);
}

// Deliver server-to-server; the panel returns the one-time browser callback.
$result = local_mcpconnector_oauth_deliver_token($state, $userid, $token, $roles, 0);
if (empty($result['ok']) || empty($result['data']['callbackUrl'])) {
    $fail('token delivery to the panel failed');
}

redirect(new moodle_url($result['data']['callbackUrl']));
