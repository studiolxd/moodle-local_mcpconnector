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
 * Library functions for Moodle MCP.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include generated service function definitions.
require_once(__DIR__ . '/db/service_functions.php');

/**
 * Returns the base URL for the MoodleMCP panel API.
 *
 * @return string
 */
function local_mcpconnector_api_base_url(): string {
    $configured = trim((string) get_config('local_mcpconnector', 'panel_url'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    return 'https://moodlemcp.com';
}

/**
 * Returns the configured panel secret used to sign API requests.
 *
 * @return string
 */
function local_mcpconnector_get_panel_secret(): string {
    return trim((string) get_config('local_mcpconnector', 'panel_secret'));
}

/**
 * Returns the configured MCP endpoint URL (the URL AI assistants connect to).
 *
 * This is NOT the panel URL: the panel manages licenses and keys, the MCP
 * endpoint serves the actual MCP protocol. Used in key emails.
 *
 * @return string
 */
function local_mcpconnector_get_mcp_url(): string {
    return rtrim(trim((string) get_config('local_mcpconnector', 'mcp_url')), '/');
}

/**
 * Verify a signed OAuth `state` produced by the panel (signOAuthState in
 * src/lib/moodle/oauth-state.ts). Format: `<base64url(json)>.<hex hmac>`, HMAC
 * over the base64url payload with the per-install panel secret. Proves the
 * authorize request came from the panel and hasn't expired. Constant-time compare.
 *
 * @param string $state The full state string from the query.
 * @param string $panelsecret The install's panel secret.
 * @return bool True when the signature is valid and the payload not expired.
 */
function local_mcpconnector_verify_oauth_state(string $state, string $panelsecret): bool {
    $dot = strrpos($state, '.');
    if ($dot === false || $dot === 0) {
        return false;
    }
    $body = substr($state, 0, $dot);
    $sig = substr($state, $dot + 1);

    $expected = hash_hmac('sha256', $body, $panelsecret);
    if (!hash_equals($expected, $sig)) {
        return false;
    }

    // Base64url decode (PHP base64_decode tolerates missing padding).
    $json = base64_decode(strtr($body, '-_', '+/'), true);
    if ($json === false) {
        return false;
    }
    $payload = json_decode($json, true);
    if (!is_array($payload) || empty($payload['exp']) || (int) $payload['exp'] <= time()) {
        return false;
    }
    return true;
}

/**
 * Deliver a freshly minted webservice token to the panel for the OAuth flow,
 * over the existing signed server-to-server channel (never the browser).
 *
 * @param string $state The signed state echoed back so the panel matches the handshake.
 * @param int $userid The Moodle user id.
 * @param string $token The webservice token.
 * @param array $roles The user's effective roles.
 * @param int $expiresat Token expiry (unix seconds; 0 = permanent).
 * @return array The panel API result: ['ok' => bool, 'data' => ['callbackUrl' => ...], 'error' => ...].
 */
function local_mcpconnector_oauth_deliver_token(
    string $state,
    int $userid,
    string $token,
    array $roles,
    int $expiresat = 0
): array {
    return local_mcpconnector_call_panel_api('/api/moodle/oauth/deliver', [
        'state' => $state,
        'moodleUserId' => $userid,
        'moodleToken' => $token,
        'moodleRoles' => array_values($roles),
        'expiresAt' => $expiresat,
    ]);
}

// Note: local_mcpconnector_get_service_definitions() is now defined in db/service_functions.php.

/**
 * Ensures the MoodleMCP services exist (creates missing ones only).
 *
 * @return int Number of services created.
 */
function local_mcpconnector_ensure_services(): int {
    global $DB;

    $created = 0;
    $now = time();

    foreach (local_mcpconnector_get_service_definitions() as $service) {
        if ($DB->record_exists('external_services', ['shortname' => $service['shortname']])) {
            continue;
        }

        $record = new stdClass();
        $record->name = $service['name'];
        $record->shortname = $service['shortname'];
        $record->enabled = 1;
        $record->restrictedusers = 1;
        $record->requiredcapability = '';
        // Deliberately NOT component-owned: Moodle's post-upgrade sync deletes
        // component services missing from db/services.php — WITH their tokens
        // (lib/upgradelib.php, external_update_descriptions). These services
        // are managed dynamically by the plugin, so they must be "custom"
        // (component null) for the sync to ignore them.
        $record->component = null;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $serviceid = $DB->insert_record('external_services', $record);
        local_mcpconnector_set_service_functions($serviceid, $service['functions']);
        $created++;
    }

    return $created;
}

/**
 * Tears down every Moodle-side resource the plugin has provisioned: external
 * services, their function/user/role assignments, tokens, and the panel's MCP
 * keys. Shared by the uninstall hook and the manual "Deprovision" action, so
 * both stay in sync.
 *
 * Deliberately leaves plugin config (license, panel URL/secret) untouched —
 * unlike uninstall, this can run while the plugin stays installed, and the
 * connection must survive so the admin can re-provision without re-entering
 * credentials.
 *
 * @param string $reason Sent to the panel revoke-all call for its audit log.
 * @return array{servicesremoved:int,panel:array{ok:bool,error:string|null}}
 */
function local_mcpconnector_deprovision_resources(string $reason): array {
    global $DB;

    // Revoke every live MCP key on the panel BEFORE wiping the local service/token
    // rows below (the license + panel secret authenticate this call). The result is
    // returned rather than swallowed — callers decide whether to surface it.
    try {
        $panel = local_mcpconnector_call_panel_api('/api/moodle/keys/revoke-all', ['reason' => $reason]);
    } catch (\Throwable $e) {
        debugging('local_mcpconnector deprovision: panel revoke-all failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $panel = ['ok' => false, 'data' => null, 'error' => $e->getMessage()];
    }

    $serviceids = [];
    $bycomponent = $DB->get_records('external_services', ['component' => 'local_mcpconnector'], '', 'id');
    foreach ($bycomponent as $service) {
        $serviceids[] = (int) $service->id;
    }
    // Since 2.3.1 the plugin's services are custom (component NULL — the
    // token-nuke lesson), so they must be collected by shortname too.
    foreach (local_mcpconnector_get_service_definitions() as $definition) {
        $service = $DB->get_record('external_services', ['shortname' => $definition['shortname']], 'id');
        if ($service) {
            $serviceids[] = (int) $service->id;
        }
    }

    $serviceids = array_values(array_unique($serviceids));

    foreach ($serviceids as $serviceid) {
        $DB->delete_records('external_services_functions', ['externalserviceid' => $serviceid]);
        $DB->delete_records('external_services_users', ['externalserviceid' => $serviceid]);
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);
        if ($DB->get_manager()->table_exists('external_services_roles')) {
            $DB->delete_records('external_services_roles', ['externalserviceid' => $serviceid]);
        }
        $DB->delete_records('external_services', ['id' => $serviceid]);
    }

    // Mark local key metadata as revoked (never delete — it's the audit trail,
    // same convention as the per-user revoke path in local_mcpconnector_recalculate_user_key()).
    $DB->set_field_select(
        'local_mcpconnector_keys',
        'status',
        'revoked',
        "status <> 'revoked'"
    );

    return [
        'servicesremoved' => count($serviceids),
        'panel' => ['ok' => (bool) $panel['ok'], 'error' => $panel['error'] ?? null],
    ];
}

/**
 * Restores a service's function list to the baseline definitions.
 *
 * @param string $shortname
 * @return bool
 */
function local_mcpconnector_restore_service_baseline(string $shortname): bool {
    global $DB;

    $definition = null;
    foreach (local_mcpconnector_get_service_definitions() as $service) {
        if ($service['shortname'] === $shortname) {
            $definition = $service;
            break;
        }
    }
    if ($definition === null) {
        return false;
    }

    $record = $DB->get_record('external_services', ['shortname' => $shortname], 'id', IGNORE_MISSING);
    if (!$record) {
        return false;
    }

    local_mcpconnector_set_service_functions((int) $record->id, $definition['functions']);
    return true;
}

/**
 * Updates the function whitelist for a service.
 *
 * @param int $serviceid
 * @param string[] $functions
 * @return void
 */
function local_mcpconnector_set_service_functions(int $serviceid, array $functions): void {
    global $DB;

    $DB->delete_records('external_services_functions', ['externalserviceid' => $serviceid]);
    foreach ($functions as $functionname) {
        // Only add functions that exist in this Moodle installation.
        if ($DB->record_exists('external_functions', ['name' => $functionname])) {
            $sf = new stdClass();
            $sf->externalserviceid = $serviceid;
            $sf->functionname = $functionname;
            $DB->insert_record('external_services_functions', $sf);
        }
    }
}

/**
 * Syncs functions for ALL services based on their definitions.
 * Called during install and upgrade.
 *
 * @return void
 */
function local_mcpconnector_sync_all_service_functions(): void {
    foreach (local_mcpconnector_get_service_definitions() as $service) {
        local_mcpconnector_restore_service_baseline($service['shortname']);
    }
}

/**
 * Returns a list of available external functions for form selection.
 *
 * @return array<string,string>
 */
function local_mcpconnector_get_external_function_choices(): array {
    global $DB;

    $records = $DB->get_records('external_functions', null, 'name ASC', 'name,component');
    $choices = [];
    foreach ($records as $record) {
        $component = $record->component !== '' ? $record->component : 'core';
        $choices[$record->name] = $record->name . ' (' . $component . ')';
    }

    return $choices;
}

/**
 * Determines whether a user has any of the given role shortnames at system context.
 *
 * @param int $userid
 * @param string[] $shortnames
 * @return bool
 */
function local_mcpconnector_user_has_system_role(int $userid, array $shortnames): bool {
    global $DB;

    if (empty($shortnames)) {
        return false;
    }

    $systemcontext = context_system::instance();
    [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;
    $params['contextid'] = $systemcontext->id;

    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {role} r ON r.id = ra.roleid
             WHERE ra.userid = :userid
               AND ra.contextid = :contextid
               AND r.shortname {$insql}";

    return $DB->record_exists_sql($sql, $params);
}

/**
 * Determines whether a user has any of the given role shortnames in any course context.
 *
 * @param int $userid
 * @param string[] $shortnames
 * @return bool
 */
function local_mcpconnector_user_has_course_role(int $userid, array $shortnames): bool {
    global $DB;

    if (empty($shortnames)) {
        return false;
    }

    [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;
    $params['contextlevel'] = CONTEXT_COURSE;

    $sql = "SELECT 1
              FROM {role_assignments} ra
              JOIN {role} r ON r.id = ra.roleid
              JOIN {context} c ON c.id = ra.contextid
             WHERE ra.userid = :userid
               AND r.shortname {$insql}
               AND c.contextlevel = :contextlevel";

    return $DB->record_exists_sql($sql, $params);
}

/**
 * Calculates the effective role array for a Moodle user.
 *
 * @param int $userid
 * @return string[]
 */
function local_mcpconnector_get_effective_roles(int $userid): array {
    $roles = [];

    if (is_siteadmin($userid)) {
        $roles[] = 'admin';
    }

    // Check for manager role at system level OR course level.
    if (
        local_mcpconnector_user_has_system_role($userid, ['manager']) ||
        local_mcpconnector_user_has_course_role($userid, ['manager'])
    ) {
        $roles[] = 'manager';
    }

    $haseditingteacher = local_mcpconnector_user_has_course_role($userid, ['editingteacher']);
    if ($haseditingteacher) {
        $roles[] = 'editingteacher';
    }

    $hasnonediting = local_mcpconnector_user_has_course_role($userid, ['teacher', 'noneditingteacher']);
    if ($hasnonediting) {
        $roles[] = 'teacher';
    }

    if (local_mcpconnector_user_has_course_role($userid, ['student'])) {
        $roles[] = 'student';
    }

    $hascourse = in_array('editingteacher', $roles, true) ||
        in_array('teacher', $roles, true) ||
        in_array('student', $roles, true);

    if (!$hascourse) {
        $roles[] = 'user';
    }

    return array_values(array_unique($roles));
}

/**
 * Returns the role name from a MoodleMCP service shortname.
 *
 * @param string $shortname
 * @return string
 */
function local_mcpconnector_role_from_service(string $shortname): string {
    $prefix = 'mcpconnector_';
    if (strpos($shortname, $prefix) === 0) {
        return substr($shortname, strlen($prefix));
    }

    return $shortname;
}

/**
 * Checks if auto-sync is enabled for a specific service.
 *
 * @param string $service Service shortname (e.g., 'mcpconnector_admin', 'mcpconnector_teacher')
 * @return bool
 */
function local_mcpconnector_is_auto_sync_enabled_for_service(string $service): bool {
    $role = local_mcpconnector_role_from_service($service);
    $configkey = 'auto_sync_' . $role;
    return (int) get_config('local_mcpconnector', $configkey) === 1;
}

/**
 * Checks if auto-sync is enabled for any MoodleMCP service.
 *
 * @return bool
 */
function local_mcpconnector_has_any_auto_sync_enabled(): bool {
    foreach (local_mcpconnector_get_service_definitions() as $service) {
        if (local_mcpconnector_is_auto_sync_enabled_for_service($service['shortname'])) {
            return true;
        }
    }

    // Backward compatibility for legacy global flag.
    return (int) get_config('local_mcpconnector', 'auto_sync') === 1;
}

/**
 * Returns the shortnames of every service whose auto-sync flag is enabled.
 *
 * Automatic provisioning paths (scheduled/adhoc/observer) must restrict work to
 * this set so a user is never auto-provisioned to a service whose flag is off.
 *
 * @return string[]
 */
function local_mcpconnector_get_auto_sync_enabled_services(): array {
    $enabled = [];
    foreach (local_mcpconnector_get_service_definitions() as $service) {
        if (local_mcpconnector_is_auto_sync_enabled_for_service($service['shortname'])) {
            $enabled[] = $service['shortname'];
        }
    }

    return $enabled;
}

/**
 * Gets the human-readable name for a service.
 *
 * @param string $service Service shortname (e.g., 'mcpconnector_admin')
 * @return string
 */
function local_mcpconnector_get_service_display_name(string $service): string {
    $role = local_mcpconnector_role_from_service($service);
    $stringkey = 'service_name_' . $role;

    if (get_string_manager()->string_exists($stringkey, 'local_mcpconnector')) {
        return get_string($stringkey, 'local_mcpconnector');
    }

    // Fallback to service shortname.
    return $service;
}


/**
 * Determines whether a user is eligible for a MoodleMCP service.
 *
 * @param int $userid
 * @param string $shortname
 * @return bool
 */
function local_mcpconnector_user_is_eligible_for_service(int $userid, string $shortname): bool {
    $role = local_mcpconnector_role_from_service($shortname);

    switch ($role) {
        case 'admin':
            return is_siteadmin($userid);
        case 'manager':
            return local_mcpconnector_user_has_system_role($userid, ['manager']) ||
                local_mcpconnector_user_has_course_role($userid, ['manager']);
        case 'editingteacher':
            return local_mcpconnector_user_has_course_role($userid, ['editingteacher']);
        case 'teacher':
            // Checks for non-editing teacher role (legacy names: teacher, noneditingteacher).
            return local_mcpconnector_user_has_course_role($userid, ['teacher', 'noneditingteacher']);
        case 'student':
            return local_mcpconnector_user_has_course_role($userid, ['student']);
        case 'user':
            return true;
        default:
            return false;
    }
}

/**
 * Maps a role name to the corresponding MoodleMCP service shortname.
 *
 * @param string $role
 * @return string
 */
function local_mcpconnector_service_for_role(string $role): string {
    return 'mcpconnector_' . $role;
}

/**
 * Picks the primary role name for a user based on role priority.
 *
 * @param string[] $roles
 * @return string
 */
function local_mcpconnector_primary_role_for_roles(array $roles): string {
    $priority = ['admin', 'manager', 'editingteacher', 'teacher', 'student', 'user'];
    foreach ($priority as $role) {
        if (in_array($role, $roles, true)) {
            return $role;
        }
    }

    return 'user';
}

/**
 * Ensures a user is authorized for a service.
 *
 * @param int $userid
 * @param int $serviceid
 * @return void
 */
function local_mcpconnector_authorize_user_for_service(int $userid, int $serviceid): void {
    global $DB;

    if (
        $DB->record_exists('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $userid,
        ])
    ) {
        return;
    }

    $record = new stdClass();
    $record->externalserviceid = $serviceid;
    $record->userid = $userid;
    $DB->insert_record('external_services_users', $record);
}

/**
 * Revokes tokens for a user and service.
 *
 * @param int $userid
 * @param int $serviceid
 * @return void
 */
function local_mcpconnector_revoke_user_tokens(int $userid, int $serviceid): void {
    global $DB;

    $DB->delete_records('external_tokens', [
        'userid' => $userid,
        'externalserviceid' => $serviceid,
    ]);
}

/**
 * Revokes all MoodleMCP tokens for a user except the given service id.
 *
 * @param int $userid
 * @param int $serviceid
 * @return void
 */
function local_mcpconnector_revoke_other_service_tokens(int $userid, int $serviceid): void {
    global $DB;

    $serviceids = local_mcpconnector_get_service_ids();
    if (empty($serviceids)) {
        return;
    }

    $serviceids = array_values(array_diff($serviceids, [$serviceid]));
    if (empty($serviceids)) {
        return;
    }

    [$insql, $params] = $DB->get_in_or_equal($serviceids, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;
    $DB->delete_records_select('external_tokens', "userid = :userid AND externalserviceid {$insql}", $params);
}

/**
 * Revokes all MoodleMCP tokens for a user across all MoodleMCP services.
 *
 * @param int $userid
 * @return void
 */
function local_mcpconnector_revoke_service_tokens(int $userid): void {
    global $DB;

    $serviceids = local_mcpconnector_get_service_ids();
    if (empty($serviceids)) {
        return;
    }

    [$insql, $params] = $DB->get_in_or_equal($serviceids, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;
    $DB->delete_records_select('external_tokens', "userid = :userid AND externalserviceid {$insql}", $params);
}

/**
 * Generates a permanent web service token, compatible with Moodle 4.2+ and older releases.
 *
 * Moodle 4.2 (MDL-76583) moved token generation to \core_external\util::generate_token()
 * with signature (int $tokentype, stdClass $service, int $userid, context $context,
 * int $validuntil = 0, string $iprestriction = '') — it requires the service RECORD,
 * unlike the legacy global external_generate_token() which accepted a service id.
 * The EXTERNAL_TOKEN_PERMANENT constant remains a global define in lib/moodlelib.php
 * on all supported branches; the guards below cover a hypothetical move to a class constant.
 *
 * @param int $serviceid
 * @param int $userid
 * @param context $context
 * @param int $validuntil
 * @return string
 */
function local_mcpconnector_generate_permanent_token(int $serviceid, int $userid, context $context, int $validuntil = 0): string {
    global $CFG, $DB;

    if (defined('EXTERNAL_TOKEN_PERMANENT')) {
        $tokentype = EXTERNAL_TOKEN_PERMANENT;
    } else if (class_exists('core_external\util') && defined('core_external\util::TOKEN_PERMANENT')) {
        $tokentype = constant('core_external\util::TOKEN_PERMANENT');
    } else {
        // Permanent tokens have always been type 0.
        $tokentype = 0;
    }

    if (class_exists('core_external\util') && method_exists('core_external\util', 'generate_token')) {
        $service = $DB->get_record('external_services', ['id' => $serviceid], '*', MUST_EXIST);
        return \core_external\util::generate_token($tokentype, $service, $userid, $context, $validuntil, '');
    }

    require_once($CFG->libdir . '/externallib.php');
    return external_generate_token($tokentype, $serviceid, $userid, $context, $validuntil, '');
}

/**
 * Creates a new Moodle web service token for a user/service, rotating any previous one.
 *
 * @param int $userid
 * @param int $serviceid
 * @param int $validuntil
 * @return string
 */
function local_mcpconnector_rotate_user_token(int $userid, int $serviceid, int $validuntil = 0): string {
    global $CFG;

    require_once($CFG->dirroot . '/webservice/lib.php');

    $systemcontext = context_system::instance();

    local_mcpconnector_revoke_user_tokens($userid, $serviceid);

    return local_mcpconnector_generate_permanent_token($serviceid, $userid, $systemcontext, $validuntil);
}

/**
 * Returns an existing token for a user/service pair.
 *
 * @param int $userid
 * @param int $serviceid
 * @return string|null
 */
function local_mcpconnector_get_user_service_token(int $userid, int $serviceid): ?string {
    global $DB;

    $record = $DB->get_record('external_tokens', [
        'userid' => $userid,
        'externalserviceid' => $serviceid,
    ], 'token', IGNORE_MISSING);

    return $record ? (string) $record->token : null;
}

/**
 * Returns the external service id for a MoodleMCP service shortname.
 *
 * @param string $shortname
 * @return int|null
 */
function local_mcpconnector_get_service_id(string $shortname): ?int {
    global $DB;

    $record = $DB->get_record('external_services', ['shortname' => $shortname], 'id', IGNORE_MISSING);
    return $record ? (int) $record->id : null;
}

/**
 * Returns all MoodleMCP service ids that exist.
 *
 * @return int[]
 */
function local_mcpconnector_get_service_ids(): array {
    global $DB;

    $shortnames = array_column(local_mcpconnector_get_service_definitions(), 'shortname');
    if (empty($shortnames)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $records = $DB->get_records_select('external_services', "shortname {$insql}", $params, '', 'id');

    $ids = [];
    foreach ($records as $record) {
        $ids[] = (int) $record->id;
    }

    return $ids;
}

/**
 * Returns a shortname => service id map for all MoodleMCP services in one query.
 *
 * Memoized per request to avoid the N+1 get_service_id() lookups a bulk sync would
 * otherwise incur. The cache is only populated once every expected service exists,
 * so a call made before ensure_services() has created them cannot poison the run.
 *
 * @return array<string,int>
 */
function local_mcpconnector_get_service_id_map(): array {
    global $DB;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $shortnames = array_column(local_mcpconnector_get_service_definitions(), 'shortname');
    if (empty($shortnames)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $records = $DB->get_records_select('external_services', "shortname {$insql}", $params, '', 'id,shortname');

    $map = [];
    foreach ($records as $record) {
        $map[$record->shortname] = (int) $record->id;
    }

    if (count($map) === count($shortnames)) {
        $cache = $map;
    }

    return $map;
}

/**
 * Records a key minted on the panel in the local metadata table.
 *
 * Only metadata is stored — the mcpKey VALUE exists exclusively in the create
 * response and must never be persisted.
 *
 * @param int $userid
 * @param string $panelkeyid Panel-side key id (uuid).
 * @param string $keylast4
 * @param string[] $roles
 * @return void
 */
function local_mcpconnector_record_local_key(
    int $userid,
    string $panelkeyid,
    string $keylast4,
    array $roles,
    ?int $expiresat = null
): void {
    global $DB;

    $now = time();
    $record = new stdClass();
    $record->userid = $userid;
    $record->panelkeyid = $panelkeyid;
    $record->keylast4 = $keylast4;
    $record->roles = implode(',', $roles);
    $record->status = 'active';
    $record->sentat = null;
    $record->expiresat = $expiresat;
    $record->timecreated = $now;
    $record->timemodified = $now;
    $DB->insert_record('local_mcpconnector_keys', $record);
}

/**
 * The configured key lifetime as a unix expiry, or null when keys never expire.
 *
 * @return int|null Absolute expiry timestamp, or null for no expiry.
 */
function local_mcpconnector_key_expiry(): ?int {
    $days = (int) get_config('local_mcpconnector', 'key_lifetime_days');
    if ($days <= 0) {
        return null;
    }
    return time() + ($days * DAYSECS);
}

/**
 * Updates the local status of a key.
 *
 * @param string $panelkeyid
 * @param string $status active|suspended|revoked
 * @return void
 */
function local_mcpconnector_set_local_key_status(string $panelkeyid, string $status): void {
    global $DB;

    $record = $DB->get_record('local_mcpconnector_keys', ['panelkeyid' => $panelkeyid], 'id', IGNORE_MISSING);
    if (!$record) {
        return;
    }

    $DB->set_field('local_mcpconnector_keys', 'status', $status, ['id' => $record->id]);
    $DB->set_field('local_mcpconnector_keys', 'timemodified', time(), ['id' => $record->id]);
}

/**
 * Marks a key as emailed to its user (sent tracking is local since panel API v2).
 *
 * @param string $panelkeyid
 * @return void
 */
function local_mcpconnector_mark_local_key_sent(string $panelkeyid): void {
    global $DB;

    $record = $DB->get_record('local_mcpconnector_keys', ['panelkeyid' => $panelkeyid], 'id', IGNORE_MISSING);
    if (!$record) {
        return;
    }

    $now = time();
    $DB->set_field('local_mcpconnector_keys', 'sentat', $now, ['id' => $record->id]);
    $DB->set_field('local_mcpconnector_keys', 'timemodified', $now, ['id' => $record->id]);
}

/**
 * Revokes every live panel key tracked locally for a user.
 *
 * @param int $userid
 * @return array{ok:bool,error:string|null}
 */
function local_mcpconnector_revoke_user_panel_keys(int $userid): array {
    global $DB;

    $rows = $DB->get_records_select(
        'local_mcpconnector_keys',
        "userid = :userid AND status <> 'revoked'",
        ['userid' => $userid]
    );

    $ok = true;
    $error = null;
    foreach ($rows as $row) {
        $result = local_mcpconnector_panel_revoke_key((string) $row->panelkeyid);
        // A not_found means the key is already gone panel-side — the local row is dead either way.
        if (!$result['ok'] && ($result['error'] ?? '') !== 'not_found') {
            $ok = false;
            if ($error === null) {
                $error = $result['error'] ?? 'revoke_failed';
            }
        }
    }

    return ['ok' => $ok, 'error' => $error];
}

/**
 * Suspends a user's currently-active panel keys.
 *
 * Called when the Moodle account is suspended: Moodle already rejects the
 * account's web-service token, but suspending the panel key too gives a clean
 * key-level cutoff and stops the key counting against the license. Only touches
 * rows that are locally 'active', so a settled state costs no panel calls.
 * Reactivation happens through the normal reconciliation once the account is
 * reinstated.
 *
 * @param int $userid
 * @return array{ok:bool,error:?string}
 */
function local_mcpconnector_suspend_user_panel_keys(int $userid): array {
    global $DB;

    $rows = $DB->get_records('local_mcpconnector_keys', ['userid' => $userid, 'status' => 'active']);

    $ok = true;
    $error = null;
    foreach ($rows as $row) {
        $result = local_mcpconnector_panel_suspend_key((string) $row->panelkeyid, true);
        if (!$result['ok']) {
            $ok = false;
            if ($error === null) {
                $error = $result['error'] ?? 'suspend_failed';
            }
        }
    }

    return ['ok' => $ok, 'error' => $error];
}

/**
 * Revokes MCP keys for a user in the panel and removes their Moodle tokens.
 *
 * Revocation is terminal in panel API v2 (there is no delete endpoint), so this
 * covers both the legacy "delete" and "revoke" cleanup semantics.
 *
 * @param int $userid
 * @return array{ok:bool,error:string|null}
 */
function local_mcpconnector_delete_user_keys(int $userid): array {
    $result = local_mcpconnector_revoke_user_panel_keys($userid);
    // Moodle tokens are local rows; remove them regardless so no stale token lingers.
    local_mcpconnector_revoke_service_tokens($userid);
    return $result;
}

/**
 * Recalculates and updates the user's MCP key based on their remaining service assignments.
 *
 * Use this after removing a user from a service to ensure their key downgrades to
 * the next available role or is revoked if no services remain.
 *
 * @param int $userid
 * @return array{ok:bool,data:array|null,error:string|null}
 */
function local_mcpconnector_recalculate_user_key(int $userid): array {
    global $DB;

    // Serialise reconciliation per user: two concurrent workers (e.g. an adhoc
    // task and the scheduled sync) would otherwise both revoke and re-mint,
    // producing duplicate keys. If another worker holds the lock, skip — it will
    // do the work.
    $lockfactory = \core\lock\lock_config::get_lock_factory('local_mcpconnector');
    $lock = $lockfactory->get_lock("user_{$userid}", 10);
    if (!$lock) {
        return ['ok' => true, 'data' => null, 'error' => 'locked'];
    }

    try {
        // 1. Get all MoodleMCP services (cached shortname => id map, one query per run).
        $idmap = local_mcpconnector_get_service_id_map();
        $serviceids = array_values($idmap);
        if (empty($serviceids)) {
            return ['ok' => false, 'data' => null, 'error' => 'no_services_defined'];
        }

        // 2. Find which of these the user is assigned to
        [$insql, $params] = $DB->get_in_or_equal($serviceids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $assignments = $DB->get_records_select(
            'external_services_users',
            "userid = :userid AND externalserviceid {$insql}",
            $params
        );

        if (empty($assignments)) {
            // User has no remaining MoodleMCP services. DELETE everything (cleanup).
            $del = local_mcpconnector_delete_user_keys($userid);
            if (!$del['ok']) {
                return ['ok' => false, 'data' => null, 'error' => $del['error'] ?? 'revoke_failed'];
            }
            // Return OK since deletion was successful/intended.
            return ['ok' => true, 'data' => null, 'error' => 'key_deleted'];
        }

        // 3. Determine the best remaining role
        $assignedserviceids = array_column($assignments, 'externalserviceid');
        $definitions = local_mcpconnector_get_service_definitions();

        $userroles = [];
        foreach ($definitions as $def) {
            $sid = $idmap[$def['shortname']] ?? null;
            if ($sid && in_array($sid, $assignedserviceids)) {
                $userroles[] = local_mcpconnector_role_from_service($def['shortname']);
            }
        }

        $primaryrole = local_mcpconnector_primary_role_for_roles($userroles);
        $targetservice = local_mcpconnector_service_for_role($primaryrole);
        $targetserviceid = $idmap[$targetservice] ?? null;

        if (!$targetserviceid) {
            return ['ok' => false, 'data' => null, 'error' => 'no_target_service'];
        }

        // 4. Ensure token points to this target service.
        local_mcpconnector_revoke_other_service_tokens($userid, $targetserviceid);

        $tokenrotated = false;
        $token = local_mcpconnector_get_user_service_token($userid, $targetserviceid);
        if (!$token) {
            $token = local_mcpconnector_rotate_user_token($userid, $targetserviceid, 0);
            $tokenrotated = true;
        }

        // 5. Reconcile with the local key table. Panel API v2 always mints a NEW key
        // on create (no upsert by token), and the key value can never be re-read, so
        // an existing live key with the same roles and an unchanged token is kept as-is.
        $localkeys = $DB->get_records_select(
            'local_mcpconnector_keys',
            "userid = :userid AND status <> 'revoked'",
            ['userid' => $userid],
            'timecreated DESC'
        );

        if (!$tokenrotated && count($localkeys) === 1) {
            $existing = reset($localkeys);
            $rolesmatch = (string) $existing->roles === implode(',', $userroles);
            // Renew before the key lapses so a user is never cut off (the sync
            // runs every 30 min; renew within a day of expiry).
            $expiringsoon = !empty($existing->expiresat)
                && (int) $existing->expiresat <= time() + DAYSECS;

            if ($rolesmatch && !$expiringsoon) {
                if ($existing->status === 'active') {
                    // Nothing changed — keep the key (its value can't be re-read).
                    return ['ok' => true, 'data' => null, 'error' => null];
                }
                if ($existing->status === 'suspended') {
                    // The Moodle account was reinstated: reactivate the existing
                    // key on the panel, preserving its value (no re-email).
                    $react = local_mcpconnector_panel_suspend_key((string) $existing->panelkeyid, false);
                    return $react['ok']
                        ? ['ok' => true, 'data' => null, 'error' => null]
                        : $react;
                }
            }
        }

        // The key must be rotated: revoke every live key the user still has on the
        // panel (revocation is terminal), then mint a fresh one. A failed revoke
        // would leave a second live local row, so the next sync would see count != 1
        // and churn a new key every run — abort instead of leaving partial state.
        foreach ($localkeys as $row) {
            $revoke = local_mcpconnector_panel_revoke_key((string) $row->panelkeyid);
            if (!$revoke['ok'] && ($revoke['error'] ?? '') !== 'not_found') {
                return ['ok' => false, 'data' => $revoke['data'] ?? null, 'error' => $revoke['error'] ?? 'revoke_failed'];
            }
        }

        return local_mcpconnector_panel_create_key($userid, $token, $userroles, null);
    } finally {
        $lock->release();
    }
}

/**
 * Sends the MCP key email to a user.
 *
 * @param stdClass $user
 * @param string $mcpkey
 * @param string $mcpurl
 * @return bool
 */
function local_mcpconnector_send_key_email(stdClass $user, string $mcpkey, string $mcpurl): bool {
    $subjecttemplate = (string) get_config('local_mcpconnector', 'email_subject');
    $bodytemplate = (string) get_config('local_mcpconnector', 'email_body');

    // Docs link derived from the configured panel URL — generic, no hardcoded host.
    $panelurl = rtrim((string) get_config('local_mcpconnector', 'panel_url'), '/');
    $docsurl = $panelurl !== '' ? $panelurl . '/docs/mcp-connection' : '';

    $replacements = [
        '{$a->firstname}' => $user->firstname ?? '',
        '{$a->lastname}' => $user->lastname ?? '',
        '{$a->username}' => $user->username ?? '',
        '{$a->email}' => $user->email ?? '',
        '{$a->mcpkey}' => $mcpkey,
        '{$a->mcpurl}' => $mcpurl,
        '{$a->docsurl}' => $docsurl,
    ];

    $subject = strtr($subjecttemplate, $replacements);
    $body = strtr($bodytemplate, $replacements);

    return email_to_user($user, core_user::get_noreply_user(), $subject, $body);
}

/**
 * Calls a MoodleMCP panel API endpoint (v2 signed contract).
 *
 * Every request is an HMAC-signed JSON POST: the x-panel-signature header covers
 * the timestamp and the EXACT raw body string sent (t=<unix>,v1=<hex hmac_sha256>,
 * ±300s replay window on the panel side).
 *
 * @param string $path
 * @param array $payload licenseKey is injected when absent.
 * @return array{ok:bool,data:array|null,error:string|null}
 */
function local_mcpconnector_call_panel_api(string $path, array $payload): array {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    if (empty($payload['licenseKey'])) {
        $payload['licenseKey'] = local_mcpconnector_get_license_key();
    }

    $panelsecret = local_mcpconnector_get_panel_secret();
    if ($panelsecret === '') {
        debugging('local_mcpconnector: panel secret is not configured, cannot sign API call to ' . $path, DEBUG_DEVELOPER);
        return ['ok' => false, 'data' => null, 'error' => 'missing_panel_secret'];
    }

    // Encode ONCE: the signature must cover the exact raw body string that is sent.
    $body = json_encode($payload);
    if ($body === false) {
        return ['ok' => false, 'data' => null, 'error' => 'payload_encoding_failed'];
    }
    $timestamp = time();
    $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, $panelsecret);

    $curl = new curl(['timeout' => 15]);
    $curl->setHeader('Accept: application/json');
    $curl->setHeader('Content-Type: application/json');
    $curl->setHeader('x-panel-signature: ' . $signature);
    $curl->setHeader('x-panel-version: 2');

    $response = $curl->post(local_mcpconnector_api_base_url() . $path, $body);
    $info = $curl->get_info();

    if (!empty($curl->error)) {
        $errormsg = $curl->error . ' (Status: ' . ($info['http_code'] ?? 'N/A') . ')';
        debugging('local_mcpconnector: API call to ' . $path . ' failed: ' . $errormsg, DEBUG_DEVELOPER);
        return ['ok' => false, 'data' => null, 'error' => $errormsg];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        // Never log the raw body: a key-create response carries a live mcpKey.
        $httpcode = $info['http_code'] ?? 'N/A';
        debugging('local_mcpconnector: API call to ' . $path . ' returned invalid JSON (HTTP ' . $httpcode . ')', DEBUG_DEVELOPER);
        return ['ok' => false, 'data' => null, 'error' => 'invalid_json'];
    }

    // An HTTP error status is a failure even when the JSON body carries no
    // 'error' key (a 4xx with an unexpected shape used to pass as ok).
    $httpcode = (int) ($info['http_code'] ?? 0);
    if ($httpcode >= 400 && !isset($decoded['error'])) {
        debugging('local_mcpconnector: API call to ' . $path . ' returned HTTP ' . $httpcode, DEBUG_DEVELOPER);
        return ['ok' => false, 'data' => $decoded, 'error' => 'http_' . $httpcode];
    }
    if (isset($decoded['error'])) {
        $message = is_string($decoded['error']) ? $decoded['error'] : 'api_error';
        if (isset($decoded['message']) && is_string($decoded['message'])) {
            $message = $decoded['message'];
        }
        debugging('local_mcpconnector: API call to ' . $path . ' returned error: ' . $message, DEBUG_DEVELOPER);
        return ['ok' => false, 'data' => $decoded, 'error' => $message];
    }

    if (array_key_exists('success', $decoded) && empty($decoded['success'])) {
        debugging('local_mcpconnector: API call to ' . $path . ' returned success=false', DEBUG_DEVELOPER);
        return ['ok' => false, 'data' => $decoded, 'error' => 'api_error'];
    }

    return ['ok' => true, 'data' => $decoded, 'error' => null];
}

/**
 * Returns the configured license key.
 *
 * @return string
 */
function local_mcpconnector_get_license_key(): string {
    return (string) get_config('local_mcpconnector', 'license_key');
}

/**
 * Returns whether the license is validated.
 *
 * @return bool
 */
function local_mcpconnector_license_is_valid(): bool {
    return get_config('local_mcpconnector', 'license_status') === 'ok';
}

/**
 * Translates a panel API error code into a user-facing message.
 *
 * @param string $code
 * @return string
 */
function local_mcpconnector_panel_error_message(string $code): string {
    if ($code === '') {
        return get_string('panel_error_unknown', 'local_mcpconnector');
    }

    $stringkey = 'panel_error_' . $code;
    if (get_string_manager()->string_exists($stringkey, 'local_mcpconnector')) {
        return get_string($stringkey, 'local_mcpconnector');
    }

    return $code;
}

/**
 * Creates a new MCP key on the panel and records its metadata locally.
 *
 * The mcpKey value appears ONLY in this response and can never be retrieved
 * again — callers must use it immediately (email) and never persist it.
 *
 * @param int $userid
 * @param string $moodletoken
 * @param string|array $moodleroles Single role string or array of role strings.
 * @param string|null $expiresat ISO-8601 UTC datetime, or null for no expiry.
 * @return array{ok:bool,data:array|null,error:string|null}
 */
function local_mcpconnector_panel_create_key(int $userid, string $moodletoken, $moodleroles, ?string $expiresat = null): array {
    global $DB;

    $license = local_mcpconnector_get_license_key();
    if ($license === '' || !local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_license'];
    }

    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
    if (!$user) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_user'];
    }

    // Ensure we send an array.
    $roles = is_array($moodleroles) ? array_values($moodleroles) : [$moodleroles];
    $primaryrole = local_mcpconnector_primary_role_for_roles($roles);

    // Expiry: an explicit ISO-8601 argument wins; otherwise apply the
    // configured key_lifetime_days (null = never). Track it locally so the
    // sync can renew a key before it lapses.
    $expiryts = $expiresat ? strtotime($expiresat) : local_mcpconnector_key_expiry();
    $expiryts = $expiryts ?: null;

    $payload = [
        'licenseKey' => $license,
        // Panel API v2 has no moodleUsername field: the user identity is folded
        // into the key name, e.g. "Ana García (teacher)". Panel max length: 120.
        'name' => core_text::substr(fullname($user) . ' (' . $primaryrole . ')', 0, 120),
        'moodleToken' => $moodletoken,
        'moodleRoles' => $roles,
        'expiresAt' => $expiryts ? gmdate('Y-m-d\TH:i:s\Z', $expiryts) : null,
    ];

    $result = local_mcpconnector_call_panel_api('/api/moodle/keys', $payload);

    if ($result['ok'] && !empty($result['data']['id'])) {
        local_mcpconnector_record_local_key(
            $userid,
            (string) $result['data']['id'],
            (string) ($result['data']['keyLast4'] ?? ''),
            $roles,
            $expiryts
        );
    }

    return $result;
}

/**
 * Fetches metadata for this license's plugin-created keys from the panel.
 *
 * Metadata only — panel API v2 never returns key values or Moodle tokens.
 *
 * @return array{ok:bool,data:array|null,error:string|null}
 */
function local_mcpconnector_panel_list_keys(): array {
    $license = local_mcpconnector_get_license_key();
    if ($license === '' || !local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_license'];
    }

    return local_mcpconnector_call_panel_api('/api/moodle/keys/list', [
        'licenseKey' => $license,
        'createdBy' => 'moodle',
    ]);
}

/**
 * Revokes a key in the panel by its panel key id (terminal, idempotent).
 *
 * @param string $keyid Panel key id (uuid).
 * @return array{ok:bool,data:array|null,error:string|null}
 */
function local_mcpconnector_panel_revoke_key(string $keyid): array {
    $license = local_mcpconnector_get_license_key();
    if ($license === '' || $keyid === '' || !local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_license'];
    }

    $result = local_mcpconnector_call_panel_api('/api/moodle/keys/revoke', [
        'licenseKey' => $license,
        'keyId' => $keyid,
    ]);

    // A not_found means the key no longer exists panel-side; the local row is dead either way.
    if ($result['ok'] || ($result['error'] ?? '') === 'not_found') {
        local_mcpconnector_set_local_key_status($keyid, 'revoked');
    }

    return $result;
}

/**
 * Suspends or reactivates a key in the panel by its panel key id.
 *
 * @param string $keyid Panel key id (uuid).
 * @param bool $suspend
 * @return array{ok:bool,data:array|null,error:string|null}
 */
function local_mcpconnector_panel_suspend_key(string $keyid, bool $suspend): array {
    $license = local_mcpconnector_get_license_key();
    if ($license === '' || $keyid === '' || !local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'data' => null, 'error' => 'invalid_license'];
    }

    $result = local_mcpconnector_call_panel_api('/api/moodle/keys/suspend', [
        'licenseKey' => $license,
        'keyId' => $keyid,
        'suspend' => $suspend,
    ]);

    if ($result['ok']) {
        $status = (string) ($result['data']['status'] ?? ($suspend ? 'suspended' : 'active'));
        local_mcpconnector_set_local_key_status($keyid, $status);
    }

    return $result;
}

/**
 * Assigns a user to a MoodleMCP service and creates/updates their MCP key.
 *
 * @param int $userid
 * @param string $serviceshortname
 * @return array{ok:bool,error:string|null,mcpkey:string|null,mcpurl:string|null}
 */
function local_mcpconnector_assign_user_to_service(int $userid, string $serviceshortname): array {
    global $DB;

    if (!local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'error' => 'invalid_license', 'mcpkey' => null, 'mcpurl' => null];
    }

    if (!local_mcpconnector_user_is_eligible_for_service($userid, $serviceshortname)) {
        return ['ok' => false, 'error' => 'not_eligible', 'mcpkey' => null, 'mcpurl' => null];
    }

    local_mcpconnector_ensure_services();
    $serviceid = local_mcpconnector_get_service_id($serviceshortname);
    if (!$serviceid) {
        return ['ok' => false, 'error' => 'missing_service', 'mcpkey' => null, 'mcpurl' => null];
    }

    try {
        local_mcpconnector_authorize_user_for_service($userid, $serviceid);

        // We do NOT remove from other services anymore to support multi-role keys.
        // Instead, we recalculate the key which handles token rotation if primary role changes.
        $result = local_mcpconnector_recalculate_user_key($userid);

        $mcpkey = '';
        $mcpurl = local_mcpconnector_get_mcp_url();

        if ($result && isset($result['ok']) && $result['ok'] === true) {
            // If key deleted or unchanged (ok=true, data=null), mcpkey remains empty.
            if (isset($result['data']['mcpKey'])) {
                $mcpkey = (string) $result['data']['mcpKey'];
                $panelkeyid = (string) ($result['data']['id'] ?? '');

                // A freshly minted key has never been emailed: its value exists
                // only in this response, so this is the single send opportunity.
                $autoemail = (int) get_config('local_mcpconnector', 'auto_email') === 1;
                $sent = false;
                if ($autoemail) {
                    $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
                    if ($user && local_mcpconnector_send_key_email($user, $mcpkey, $mcpurl)) {
                        local_mcpconnector_mark_local_key_sent($panelkeyid);
                        $sent = true;
                    }
                }
                if (!$sent) {
                    // The key value is unrecoverable once this response is discarded.
                    // Surface the state (sentat stays null) so an admin can regenerate
                    // and resend from the Keys tab rather than losing it silently.
                    $reason = $autoemail ? 'email delivery failed' : 'auto-email disabled';
                    debugging('local_mcpconnector: minted key for user ' . $userid
                        . ' was not sent (' . $reason . '); sentat left null.', DEBUG_NORMAL);
                }
            }
        } else {
            throw new Exception($result['error'] ?? 'recalculate_failed');
        }

        return ['ok' => true, 'error' => null, 'mcpkey' => $mcpkey, 'mcpurl' => $mcpurl];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'mcpkey' => null, 'mcpurl' => null];
    }
}

/**
 * Syncs a user to their primary MoodleMCP role and key.
 *
 * @param stdClass $user
 * @param string|null $limittoservice If set, only reconcile this specific service (add/remove). Leave others as is.
 * @param bool $removeonly If true, only remove services, don't add new ones (used when a role is unassigned)
 * @param string[]|null $enabledservices If set (automatic paths), only add/remove services in this set — never
 *                                        provision or de-provision a service whose auto-sync flag is off.
 * @return array{ok:bool,added:int,removed:int,result:array|null,error:string|null,data:array|null}
 */
function local_mcpconnector_sync_user_auto(
    stdClass $user,
    ?string $limittoservice = null,
    bool $removeonly = false,
    ?array $enabledservices = null
): array {
    global $DB;

    if (!local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'added' => 0, 'removed' => 0, 'result' => null, 'error' => 'invalid_license', 'data' => null];
    }

    // Services are ensured once by the bulk/entry callers (sync_all_users, the adhoc
    // task, assign_user_to_service) — not per user, to avoid O(N) existence checks.
    $idmap = local_mcpconnector_get_service_id_map();

    // 1. Determine Target Services based on current Moodle Roles
    $effectiveroles = local_mcpconnector_get_effective_roles($user->id);
    $targetserviceids = [];
    foreach ($effectiveroles as $role) {
        $shortname = local_mcpconnector_service_for_role($role);
        // If limiting to a service, only consider this role if it maps to the limited service.
        if ($limittoservice !== null && $shortname !== $limittoservice) {
            continue;
        }
        // Automatic paths must never provision a service whose auto-sync flag is off.
        if ($enabledservices !== null && !in_array($shortname, $enabledservices, true)) {
            continue;
        }
        $sid = $idmap[$shortname] ?? null;
        if ($sid) {
            $targetserviceids[] = $sid;
        }
    }

    // 2. Reconcile with Current Assignments
    // Get current assignments
    $mcpserviceids = array_values($idmap);
    if (empty($mcpserviceids)) {
        // Should have been ensured, but safety check.
        return ['ok' => false, 'added' => 0, 'removed' => 0, 'result' => null, 'error' => 'no_services_defined', 'data' => null];
    }

    [$insql, $params] = $DB->get_in_or_equal($mcpserviceids, SQL_PARAMS_NAMED);
    $params['userid'] = $user->id;
    $currentassignments = $DB->get_records_select(
        'external_services_users',
        "userid = :userid AND externalserviceid {$insql}",
        $params
    );
    $currentserviceids = array_map(function ($a) {
        return (int) $a->externalserviceid;
    }, $currentassignments);

    // Filter scope for reconciliation.
    if ($limittoservice !== null) {
        $limitsid = $idmap[$limittoservice] ?? null;
        if ($limitsid) {
            // We only care about adding/removing THAT service.

            // ToAdd: If eligible (in target) AND not assigned.
            // targetServiceIds only has limited service if eligible.
            $iseligible = in_array($limitsid, $targetserviceids);
            $isassigned = in_array($limitsid, $currentserviceids);

            $toadd = ($iseligible && !$isassigned && !$removeonly) ? [$limitsid] : [];
            $toremove = (!$iseligible && $isassigned) ? [$limitsid] : [];
        } else {
            $toadd = [];
            $toremove = [];
        }
    } else {
        // Full Sync
        // Add missing (unless remove_only mode).
        $toadd = $removeonly ? [] : array_diff($targetserviceids, $currentserviceids);
        // Remove extra.
        $toremove = array_diff($currentserviceids, $targetserviceids);

        // On automatic paths, only touch services whose auto-sync flag is enabled:
        // a manual assignment to a flag-off service must not be auto-removed.
        if ($enabledservices !== null) {
            $enabledserviceids = [];
            foreach ($enabledservices as $shortname) {
                if (isset($idmap[$shortname])) {
                    $enabledserviceids[] = $idmap[$shortname];
                }
            }
            $toremove = array_intersect($toremove, $enabledserviceids);
        }
    }

    $addedcount = 0;
    $removedcount = 0;

    foreach ($toremove as $sid) {
        $DB->delete_records('external_services_users', ['externalserviceid' => $sid, 'userid' => $user->id]);
        $removedcount++;
    }
    foreach ($toadd as $sid) {
        local_mcpconnector_authorize_user_for_service((int) $user->id, (int) $sid);
        $addedcount++;
    }

    // 3. Recalculate Key (handles token rotation, key creation/deletion on panel)
    $result = local_mcpconnector_recalculate_user_key($user->id);

    // 4. Handle Email Notification
    // A create response always carries a brand-new key that was never emailed;
    // this response is the only moment its value exists.

    $mcpkey = '';
    $panelkeyid = '';

    if ($result && isset($result['ok']) && $result['ok'] === true) {
        if (isset($result['data']['mcpKey'])) {
            $mcpkey = (string) $result['data']['mcpKey'];
            $panelkeyid = (string) ($result['data']['id'] ?? '');
        }
    }

    if ($mcpkey !== '') {
        $autoemail = (int) get_config('local_mcpconnector', 'auto_email') === 1;
        $sent = false;
        if ($autoemail) {
            $sent = local_mcpconnector_send_key_email($user, $mcpkey, local_mcpconnector_get_mcp_url());
            if ($sent) {
                local_mcpconnector_mark_local_key_sent($panelkeyid);
            }
        }
        if (!$sent) {
            // The minted key value is unrecoverable once this response is discarded.
            // Don't swallow it: leave sentat null (a "created but never sent" marker on
            // the Keys tab) and log so the admin can regenerate + resend.
            $reason = $autoemail ? 'email delivery failed' : 'auto-email disabled';
            mtrace('MoodleMCP: minted key for user ' . (int) $user->id
                . ' was not sent (' . $reason . '); sentat left null.');
            debugging('local_mcpconnector: minted key for user ' . (int) $user->id
                . ' was not sent (' . $reason . ')', DEBUG_NORMAL);
        }
    }

    // Propagate the recalc outcome so bulk callers can detect and surface failures
    // (a failed recalc otherwise looked like a successful no-op).
    $recalcok = ($result && isset($result['ok'])) ? (bool) $result['ok'] : false;

    return [
        'ok' => $recalcok,
        'added' => $addedcount,
        'removed' => $removedcount,
        'result' => $result,
        'error' => $recalcok ? null : ($result['error'] ?? 'recalculate_failed'),
        'data' => $result['data'] ?? null,
    ];
}

/**
 * Syncs users and revokes keys for deleted accounts.
 *
 * @param string|null $servicefilter Shortname of the service to filter sync by (optional).
 * @param string[]|null $enabledservices If set (scheduled/auto path), restrict provisioning to these services only.
 * @return array{ok:bool,synced:int,added:int,removed:int,revoked:int}
 */
function local_mcpconnector_sync_all_users(?string $servicefilter = null, ?array $enabledservices = null): array {
    global $CFG, $DB;

    if (!local_mcpconnector_license_is_valid()) {
        return ['ok' => false, 'synced' => 0, 'added' => 0, 'removed' => 0, 'revoked' => 0];
    }

    local_mcpconnector_ensure_services();

    $revoked = 0;
    // Revocation only happens on FULL sync or can happen targeted?
    // If filtering, we probably shouldn't bulk revoke unrelated users.
    if ($servicefilter === null) {
        // Revoke live keys whose Moodle user no longer exists, based on the local table.
        $rows = $DB->get_records_select('local_mcpconnector_keys', "status <> 'revoked'");
        foreach ($rows as $row) {
            if (!$DB->record_exists('user', ['id' => $row->userid, 'deleted' => 0])) {
                $revoke = local_mcpconnector_panel_revoke_key((string) $row->panelkeyid);
                // Only count (and drop the Moodle token) once the panel confirms the
                // revoke — otherwise mtrace over-reports and the next run retries.
                if ($revoke['ok'] || ($revoke['error'] ?? '') === 'not_found') {
                    local_mcpconnector_revoke_service_tokens((int) $row->userid);
                    $revoked++;
                } else {
                    mtrace('MoodleMCP: failed to revoke key ' . $row->panelkeyid
                        . ' for deleted user ' . $row->userid . ': ' . ($revoke['error'] ?? 'unknown'));
                }
            }
        }
    }

    $synced = 0;
    $totaladded = 0;
    $totalremoved = 0;
    $guestid = isset($CFG->siteguest) ? (int) $CFG->siteguest : 0;

    // Build SQL based on filter.
    $extrajoin = '';
    $extrawhere = '';
    $params = ['guestid' => $guestid];

    if ($servicefilter) {
        // If filtering by service, we only want users who are eligible for this service
        // This effectively means users who have the role mapping to this service.
        $role = local_mcpconnector_role_from_service($servicefilter);
        if ($role === 'admin') {
            // Admins are special, check is_siteadmin equivalent or just sync all for now?
            // Since is_siteadmin is a function, we can't easily SQL join it without specific tables.
            // We'll iterate all users and check capability in the loop for admins.
            // Or optimize: get_admins() returns list.
            $admins = get_admins();
            foreach ($admins as $admin) {
                $res = local_mcpconnector_sync_user_auto($admin, $servicefilter);
                if ($res && $res['ok']) {
                    $totaladded += $res['added'];
                    $totalremoved += $res['removed'];
                }
                $synced++;
            }
            return ['ok' => true, 'synced' => $synced, 'added' => $totaladded, 'removed' => $totalremoved, 'revoked' => $revoked];
        }
        // For non-admin roles we fall through and iterate all users below, filtering in PHP by
        // eligibility — role allocation cannot be cheaply expressed as a single SQL join here.
    }

    // If filtered, pre-fetch currently assigned users to efficient removal checks.
    $assignedidsmap = [];
    if ($servicefilter) {
        $filtersid = local_mcpconnector_get_service_id($servicefilter);
        if ($filtersid) {
            $assignedrecords = $DB->get_records('external_services_users', ['externalserviceid' => $filtersid], '', 'userid');
            foreach ($assignedrecords as $rec) {
                $assignedidsmap[$rec->userid] = true;
            }
        }
    }

    $rs = $DB->get_recordset_select(
        'user',
        'deleted = 0 AND id <> :guestid',
        $params,
        'id',
        'id,username,firstname,lastname,email,suspended'
    );

    $firsterror = null;

    foreach ($rs as $user) {
        if (!empty($user->suspended)) {
            // Suspended Moodle account: suspend its panel keys too (only the
            // still-active ones, so this is a no-op once settled).
            local_mcpconnector_suspend_user_panel_keys($user->id);
            continue;
        }

        if ($servicefilter) {
            // Check eligibility OR current assignment
            // We need to process the user if they are ELIGIBLE (to add) OR ASSIGNED (to remove).
            $iseligible = local_mcpconnector_user_is_eligible_for_service($user->id, $servicefilter);
            $isassigned = isset($assignedidsmap[$user->id]);

            if (!$iseligible && !$isassigned) {
                continue;
            }
        }

        $res = local_mcpconnector_sync_user_auto($user, $servicefilter, false, $enabledservices);
        $ratelimited = false;
        if ($res && isset($res['ok']) && $res['ok'] === true) {
            $totaladded += $res['added'];
            $totalremoved += $res['removed'];
        } else if ($res && isset($res['ok']) && $res['ok'] === false) {
            if ($firsterror === null) {
                $firsterror = $res['error'] ?? 'unknown_error';
                if (isset($res['data'])) {
                    $firsterror .= ' ' . json_encode($res['data']);
                }
            }
            // Back off instead of hammering: the scheduled task resumes next run.
            if (($res['error'] ?? '') === 'rate_limited') {
                $ratelimited = true;
            }
        }
        $synced++;
        if ($ratelimited) {
            mtrace('MoodleMCP: panel rate limit hit; stopping bulk sync early, will resume next run.');
            break;
        }
    }
    $rs->close();

    $return = ['ok' => true, 'synced' => $synced, 'added' => $totaladded, 'removed' => $totalremoved, 'revoked' => $revoked];
    if ($firsterror !== null) {
        $return['first_error'] = $firsterror;
    }
    return $return;
}

/**
 * Validates the license key + panel secret pair against the panel (signed).
 *
 * @param string $license
 * @return array{status:string,message:string}
 */
function local_mcpconnector_validate_license(string $license): array {
    global $CFG;

    $license = trim($license);
    if ($license === '') {
        return [
            'status' => 'error',
            'message' => get_string('license_empty', 'local_mcpconnector'),
        ];
    }

    if (local_mcpconnector_get_panel_secret() === '') {
        return [
            'status' => 'error',
            'message' => get_string('panel_secret_missing', 'local_mcpconnector'),
        ];
    }

    // The licenseKey is passed explicitly: at validation time the submitted value
    // may not be stored in config yet.
    $result = local_mcpconnector_call_panel_api('/api/moodle/verify', [
        'licenseKey' => $license,
        'moodleUrl' => rtrim($CFG->wwwroot, '/'),
    ]);

    if ($result['ok'] && !empty($result['data']['valid'])) {
        return [
            'status' => 'ok',
            'message' => '',
        ];
    }

    $code = (string) ($result['error'] ?? '');
    if (!empty($result['data']['error']) && is_string($result['data']['error'])) {
        $code = (string) $result['data']['error'];
    }

    return [
        'status' => 'error',
        'message' => local_mcpconnector_panel_error_message($code),
    ];
}
/**
 * Prints the module navigation tabs.
 *
 * @param string $current The current tab ID.
 * @return void
 */
function local_mcpconnector_print_tabs(string $current): void {
    $tabs = [
        new tabobject(
            'license',
            new moodle_url('/local/mcpconnector/index.php'),
            get_string('tab_license', 'local_mcpconnector')
        ),
        new tabobject(
            'services',
            new moodle_url('/local/mcpconnector/services.php'),
            get_string('tab_services', 'local_mcpconnector')
        ),
        new tabobject(
            'users',
            new moodle_url('/local/mcpconnector/users.php'),
            get_string('tab_users', 'local_mcpconnector')
        ),
        new tabobject(
            'keys',
            new moodle_url('/local/mcpconnector/keys.php'),
            get_string('tab_keys', 'local_mcpconnector')
        ),
        new tabobject(
            'health',
            new moodle_url('/local/mcpconnector/health.php'),
            get_string('tab_health', 'local_mcpconnector')
        ),
        new tabobject(
            'settings',
            new moodle_url('/local/mcpconnector/settings_page.php'),
            get_string('tab_settings', 'local_mcpconnector')
        ),
    ];
    print_tabs([$tabs], $current);
}

/**
 * Sends the opt-in telemetry snapshot to the panel (support gold: versions
 * and key COUNTS only — never personal data). Throttled to ~daily unless
 * forced from the health page. No-op while the setting is off.
 *
 * @param bool $force Skip the daily throttle (health page "send now").
 * @return array {ok, error?, skipped?}
 */
function local_mcpconnector_send_telemetry(bool $force = false): array {
    global $CFG, $DB;

    if (!(int) get_config('local_mcpconnector', 'telemetry_enabled')) {
        return ['ok' => false, 'error' => 'disabled'];
    }
    $sentat = (int) get_config('local_mcpconnector', 'telemetry_sent_at');
    if (!$force && $sentat && (time() - $sentat) < 20 * HOURSECS) {
        return ['ok' => true, 'error' => null, 'skipped' => true];
    }

    $info = \core_plugin_manager::instance()->get_plugin_info('local_mcpconnector');
    $keys = ['active' => 0, 'suspended' => 0, 'revoked' => 0];
    $counts = $DB->get_records_sql(
        "SELECT status, COUNT(*) AS c FROM {local_mcpconnector_keys} GROUP BY status"
    );
    foreach ($counts as $row) {
        $keys[$row->status] = (int) $row->c;
    }
    $keys['expired'] = (int) $DB->count_records_select(
        'local_mcpconnector_keys',
        "status = 'active' AND expiresat > 0 AND expiresat < ?",
        [time()]
    );

    $autosync = false;
    foreach (['admin', 'manager', 'editingteacher', 'teacher', 'student', 'user'] as $service) {
        if ((int) get_config('local_mcpconnector', 'auto_sync_' . $service)) {
            $autosync = true;
            break;
        }
    }
    $task = $DB->get_record('task_scheduled', [
        'component' => 'local_mcpconnector',
        'classname' => '\local_mcpconnector\task\sync_users',
    ]);

    $result = local_mcpconnector_call_panel_api('/api/moodle/telemetry', [
        'pluginRelease' => (string) ($info->release ?? ''),
        'pluginVersion' => (int) ($info->versiondb ?? 0),
        'moodleRelease' => (string) $CFG->release,
        'phpVersion' => PHP_VERSION,
        'keys' => $keys,
        'autoSync' => $autosync,
        'lastSyncAt' => (int) ($task->lastruntime ?? 0),
    ]);
    if ($result['ok']) {
        set_config('telemetry_sent_at', time(), 'local_mcpconnector');
    }
    return $result;
}

/**
 * Renders a single POST action button for a key row.
 *
 * @param string $action
 * @param string $label
 * @param string $keyid Panel key id (uuid).
 * @return string
 */
function local_mcpconnector_render_key_action(string $action, string $label, string $keyid): string {
    $form = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/mcpconnector/keys.php'))->out(false),
    ]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'keyid', 'value' => $keyid]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary', 'value' => $label]);
    $form .= html_writer::end_tag('form');
    return $form;
}
