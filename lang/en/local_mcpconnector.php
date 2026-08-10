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
 * English language pack for Moodle MCP
 *
 * Strings are ordered alphabetically, as moodle.Files.LangFilesOrdering
 * requires — grouping comments are not allowed here, so anything worth saying
 * about a string belongs in this header or next to the code that uses it.
 *
 * 'errordetail' is deliberately just '{$a}': webservice guard errors have to
 * ride the MESSAGE, because with debugging off Moodle never sends debuginfo
 * and invalid_parameter_exception details are invisible to API callers.
 *
 * @package    local_mcpconnector
 * @category   string
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adminpage'] = 'Moodle MCP';
$string['auto_email'] = 'Send MCP keys automatically by email';
$string['auto_email_desc'] = 'When enabled, Moodle MCP emails keys the first time they are created.';
$string['auto_sync_admin'] = 'Auto-sync admins';
$string['auto_sync_admin_desc'] = 'Automatically sync when site administrator role is assigned or removed.';
$string['auto_sync_editingteacher'] = 'Auto-sync editing teachers';
$string['auto_sync_editingteacher_desc'] = 'Automatically sync when editing teacher role is assigned or removed.';
$string['auto_sync_manager'] = 'Auto-sync managers';
$string['auto_sync_manager_desc'] = 'Automatically sync when manager role is assigned or removed.';
$string['auto_sync_section'] = 'Automatic synchronization';
$string['auto_sync_student'] = 'Auto-sync students';
$string['auto_sync_student_desc'] = 'Automatically sync when student is enrolled or unenrolled.';
$string['auto_sync_teacher'] = 'Auto-sync teachers';
$string['auto_sync_teacher_desc'] = 'Automatically sync when non-editing teacher role is assigned or removed.';
$string['auto_sync_user'] = 'Auto-sync users';
$string['auto_sync_user_desc'] = 'Automatically sync when a new user is created on the platform.';
$string['changes_saved'] = 'Changes saved.';
$string['deprovision'] = 'Deprovision';
$string['deprovision_confirm'] = 'This will revoke every MCP key on the panel and permanently delete all services, tokens and user authorizations created by MoodleMCP. This cannot be undone. Continue?';
$string['deprovision_help'] = 'Revokes every MCP key on the panel and removes all services, tokens and authorizations this plugin has created in Moodle. The license and panel connection are kept, so the plugin can re-provision itself afterwards.';
$string['deprovision_panel_warning'] = 'Moodle-side services and tokens were removed, but the panel could not confirm the key revocation: {$a}. Please check the panel manually.';
$string['deprovision_success'] = 'Deprovisioned successfully: {$a} service(s) removed and every panel key revoked.';
$string['editfunctions'] = 'Edit functions';
$string['email_body'] = 'Email body';
$string['email_body_default'] = 'Hello, {$a->firstname}:' . "\n\n" .
    'Your Moodle MCP access is ready. There are two ways to connect your AI assistant:' . "\n\n" .
    '1) Claude Desktop or ChatGPT (recommended): add a connector with this URL and sign in with your Moodle account when prompted — you do NOT need the key below:' . "\n" .
    '   {$a->mcpurl}' . "\n\n" .
    '2) Tools that accept a token (Cursor, scripts, CLI): use the URL above and send this key as a header  Authorization: Bearer <key>' . "\n" .
    '   {$a->mcpkey}' . "\n\n" .
    'Full instructions: {$a->docsurl}' . "\n\n" .
    'Keep the key private. Contact your administrator if you need a new one.';
$string['email_body_desc'] = 'Body template for the MCP key email. Placeholders: {$a->firstname}, {$a->lastname}, {$a->username}, {$a->email}, {$a->mcpkey}, {$a->mcpurl} (the MCP endpoint), {$a->docsurl} (the connection guide).';
$string['email_section'] = 'Email key delivery';
$string['email_subject'] = 'Email subject';
$string['email_subject_default'] = 'Your Moodle MCP access';
$string['email_subject_desc'] = 'Subject line for the MCP key email.';
$string['errordetail'] = '{$a}';
$string['existing_users'] = 'Existing users';
$string['health_auto_sync'] = 'Auto-sync services';
$string['health_heading'] = 'Connection health';
$string['health_keys'] = 'MCP keys';
$string['health_keys_detail'] = '{$a->active} active · {$a->suspended} suspended · {$a->revoked} revoked · {$a->expired} expired';
$string['health_last_sync'] = 'Last user sync';
$string['health_panel_checked'] = 'Last license check';
$string['health_panel_status'] = 'Panel connectivity (cached)';
$string['health_telemetry'] = 'Telemetry (opt-in)';
$string['health_telemetry_failed'] = 'Telemetry failed: {$a}';
$string['health_telemetry_hint'] = 'Enable telemetry in the Settings tab to share versions and key counts with the panel (helps support — never personal data).';
$string['health_telemetry_last'] = 'last sent:';
$string['health_telemetry_send'] = 'Send now';
$string['health_telemetry_sent'] = 'Telemetry sent to the panel.';
$string['health_versions'] = 'Versions';
$string['invalidservice'] = 'Unknown service.';
$string['key_activate'] = 'Activate';
$string['key_activate_failed'] = 'Failed to activate key.';
$string['key_activated'] = 'Key activated.';
$string['key_lifetime_days'] = 'Key lifetime (days)';
$string['key_lifetime_days_desc'] = 'How long a newly issued MCP key stays valid. 0 means no expiry. Keys are renewed automatically before they lapse (the user is emailed a new key).';
$string['key_regen_failed'] = 'Failed to regenerate key.';
$string['key_regenerate_confirm'] = 'Regenerate the MCP key for {$a}? The current key is revoked and a new one is emailed.';
$string['key_regenerate_email'] = 'Regenerate & email key';
$string['key_regenerated'] = 'Key regenerated.';
$string['key_revoke'] = 'Revoke';
$string['key_revoke_confirm'] = 'Revoke the MCP key for {$a}? This is permanent and cannot be undone.';
$string['key_revoke_failed'] = 'Failed to revoke key.';
$string['key_revoked'] = 'Key revoked.';
$string['key_send_failed'] = 'Failed to send key email.';
$string['key_sent'] = 'Key email sent.';
$string['key_status_active'] = 'Active';
$string['key_status_revoked'] = 'Revoked';
$string['key_status_suspended'] = 'Suspended';
$string['key_suspend'] = 'Suspend';
$string['key_suspend_failed'] = 'Failed to suspend key.';
$string['key_suspended'] = 'Key suspended.';
$string['keys_actions'] = 'Actions';
$string['keys_created'] = 'Created';
$string['keys_empty'] = 'No keys are registered for this license yet.';
$string['keys_key'] = 'Key';
$string['keys_missing_license'] = 'Configure a license before managing keys.';
$string['keys_refresh'] = 'Refresh from panel';
$string['keys_refresh_failed'] = 'Failed to refresh keys from the panel: {$a}';
$string['keys_refreshed'] = 'Key statuses refreshed from the panel.';
$string['keys_role'] = 'Roles';
$string['keys_section'] = 'MCP keys';
$string['keys_sent'] = 'Sent';
$string['keys_status'] = 'Status';
$string['keys_user'] = 'User';
$string['license_checked_at'] = 'Last checked: {$a}';
$string['license_empty'] = 'License key is required.';
$string['license_error'] = 'License is incorrect or could not be verified.';
$string['license_heading'] = 'License';
$string['license_help'] = 'Enter your license key and validate it.';
$string['license_label'] = 'License key';
$string['license_ok'] = 'License verified.';
$string['license_recheck'] = 'Verify now';
$string['license_required'] = 'A valid license is required to activate Moodle MCP.';
$string['license_save'] = 'Validate license';
$string['license_status_error'] = 'Incorrect';
$string['license_status_label'] = 'License status: {$a}';
$string['license_status_missing'] = 'Missing';
$string['license_status_ok'] = 'Configured';
$string['mcp_url'] = 'MCP endpoint URL';
$string['mcp_url_help'] = 'The MCP endpoint your AI assistants connect to — your organization\'s subdomain on the panel, e.g. https://your-org.moodlemcp.com/mcp. The panel shows the exact URL when you create the connection (copy it from there). Inserted into key emails via the mcpurl placeholder.';
$string['mcpconnector:manage'] = 'Manage MCP Connector';
$string['missing'] = 'Missing';
$string['missingservice'] = 'Service record is missing.';
$string['ok'] = 'OK';
$string['panel_error_invalid_body'] = 'The panel rejected the request as malformed.';
$string['panel_error_invalid_credentials'] = 'The panel rejected the license key or panel secret.';
$string['panel_error_invalid_license'] = 'Configure and validate a license before performing this action.';
$string['panel_error_invalid_user'] = 'The Moodle user for this key no longer exists.';
$string['panel_error_key_revoked'] = 'The key is revoked and can no longer be changed.';
$string['panel_error_missing_panel_secret'] = 'The panel secret is not configured. Enter the license key and panel secret pair issued by your panel.';
$string['panel_error_missing_signature'] = 'The request signature was missing or could not be verified. Check the panel secret.';
$string['panel_error_not_found'] = 'The key was not found on the panel.';
$string['panel_error_rate_limited'] = 'Too many requests to the panel. Try again in a minute.';
$string['panel_error_server_error'] = 'The panel reported an internal error.';
$string['panel_error_unknown'] = 'The panel returned an unexpected error.';
$string['panel_error_url_mismatch'] = 'This site URL does not match the URL registered in the panel.';
$string['panel_pair_notice'] = 'The license key and panel secret pair is shown only ONCE by the panel when the connection is created. If the secret is lost, it cannot be recovered: rotate (re-generate) the pair in the panel and enter the new values here.';
$string['panel_secret'] = 'Panel secret';
$string['panel_secret_help'] = 'Shared secret used to sign every request the plugin sends to the panel.';
$string['panel_secret_missing'] = 'The panel secret is not configured. Enter the license key and panel secret pair issued by your panel.';
$string['panel_url'] = 'Panel URL';
$string['panel_url_help'] = 'Base URL of your MoodleMCP panel, e.g. https://moodlemcp.com.';
$string['pluginname'] = 'MCP Connector for Moodle';


$string['potential_users'] = 'Potential users';
$string['privacy:metadata:localkeys'] = 'MCP key metadata stored locally (never key values or tokens).';
$string['privacy:metadata:localkeys:keylast4'] = 'The last 4 characters of the key, for identification.';
$string['privacy:metadata:localkeys:panelkeyid'] = 'The panel-side identifier of the key.';
$string['privacy:metadata:localkeys:roles'] = 'The Moodle roles the key may act with.';
$string['privacy:metadata:localkeys:sentat'] = 'When the key was emailed to the user.';
$string['privacy:metadata:localkeys:status'] = 'The key status (active, suspended or revoked).';
$string['privacy:metadata:localkeys:userid'] = 'The user the MCP key belongs to.';
$string['privacy:metadata:moodlemcp'] = 'Data sent to the MCP panel service to create and manage API keys.';
$string['privacy:metadata:moodlemcp:email'] = 'The user email address, used when sending MCP keys.';
$string['privacy:metadata:moodlemcp:firstname'] = 'The user first name, used in email templates.';
$string['privacy:metadata:moodlemcp:lastname'] = 'The user last name, used in email templates.';
$string['privacy:metadata:moodlemcp:roles'] = 'The user roles mapped to MCP services.';
$string['privacy:metadata:moodlemcp:token'] = 'The web service token generated for the user.';
$string['privacy:metadata:moodlemcp:userid'] = 'The Moodle user ID.';


$string['secret_keep_blank'] = 'Leave blank to keep the current value.';
$string['service_edit_heading'] = 'Edit functions for service "{$a}"';
$string['service_functions'] = 'Allowed functions';
$string['service_name_admin'] = 'Administrator';
$string['service_name_editingteacher'] = 'Teacher';
$string['service_name_manager'] = 'Manager';
$string['service_name_student'] = 'Student';
$string['service_name_teacher'] = 'Non-editing teacher';
$string['service_name_user'] = 'Authenticated user';
$string['service_restore'] = 'Restore service';
$string['service_restore_confirm'] = 'Restore service "{$a}" to its baseline function list? This overwrites the current whitelist.';
$string['service_restore_failed'] = 'Unable to restore service baseline.';
$string['service_restored'] = 'Service "{$a}" restored to baseline.';
$string['service_updated'] = 'Service "{$a}" updated.';
$string['services_created'] = '{$a} MoodleMCP service(s) were created.';

$string['services_heading'] = 'Services';
$string['services_table_actions'] = 'Actions';
$string['services_table_service'] = 'Service';
$string['services_table_status'] = 'Status';
$string['tab_health'] = 'Health';
$string['tab_keys'] = 'Keys';
$string['tab_license'] = 'License';
$string['tab_services'] = 'Services';
$string['tab_settings'] = 'Settings';
$string['tab_users'] = 'Users';
$string['task_sync_users'] = 'Sync MoodleMCP users';
$string['taskfailed'] = 'MoodleMCP task failed: {$a}';
$string['telemetry_enabled'] = 'Send telemetry to the panel';
$string['telemetry_enabled_desc'] = 'Opt-in: shares plugin/Moodle/PHP versions and key COUNTS with the panel about once a day (never personal data). Helps support diagnose issues proactively.';
$string['telemetry_section'] = 'Telemetry';










$string['users_add'] = 'Add';
$string['users_add_failed_plural'] = '{$a} users could not be added.';
$string['users_add_failed_singular'] = '1 user could not be added.';
$string['users_added_plural'] = '{$a} users added.';
$string['users_added_singular'] = '1 user added.';
$string['users_assigned'] = 'Assigned users';
$string['users_available'] = 'Available users';
$string['users_manage'] = 'Manage users';
$string['users_remove'] = 'Remove';
$string['users_removed_plural'] = '{$a} users removed.';
$string['users_removed_singular'] = '1 user removed.';
$string['users_sync_all'] = 'Sync all';
$string['users_sync_queued'] = 'Sync queued. It will run in the background.';
