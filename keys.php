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
 * Keys management page for Moodle MCP.
 *
 * Lists keys from the LOCAL metadata table (panel API v2 never returns key
 * values or tokens); a "Refresh from panel" action reconciles statuses.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Load Moodle config. __DIR__ resolves symlinks, so the standard
// '../../config.php' breaks when this plugin dir is symlinked into Moodle for
// development. Resolve config.php via the web document root (the Moodle web root
// in both the legacy and 5.x `public/` layouts); fall back to the relative path
// for CLI / non-symlinked installs.
require_once(
    (!empty($_SERVER['DOCUMENT_ROOT'])
        && is_file(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php'))
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/config.php'
        : __DIR__ . '/../../config.php'
);
require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_mcpconnector_keys');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$keyid = optional_param('keyid', '', PARAM_ALPHANUMEXT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if ($action !== '' && confirm_sesskey()) {
    if ($action === 'refresh') {
        $list = local_mcpconnector_panel_list_keys();
        if ($list['ok'] && isset($list['data']['keys']) && is_array($list['data']['keys'])) {
            $panelstatus = [];
            foreach ($list['data']['keys'] as $key) {
                if (!empty($key['id'])) {
                    $panelstatus[(string) $key['id']] = (string) ($key['status'] ?? '');
                }
            }
            // The panel caps the listing (truncated=true beyond the cap): a
            // locally-known key missing from a PARTIAL page must not be
            // assumed revoked.
            $truncated = !empty($list['data']['truncated']);
            $rows = $DB->get_records('local_mcpconnector_keys');
            foreach ($rows as $row) {
                if (isset($panelstatus[$row->panelkeyid])) {
                    $newstatus = $panelstatus[$row->panelkeyid];
                    if ($newstatus !== '' && $newstatus !== $row->status) {
                        local_mcpconnector_set_local_key_status((string) $row->panelkeyid, $newstatus);
                    }
                } else if (!$truncated && $row->status !== 'revoked') {
                    // The key no longer exists on the panel: treat it as revoked.
                    local_mcpconnector_set_local_key_status((string) $row->panelkeyid, 'revoked');
                }
            }
            redirect(
                $PAGE->url,
                get_string('keys_refreshed', 'local_mcpconnector'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(
                $PAGE->url,
                get_string(
                    'keys_refresh_failed',
                    'local_mcpconnector',
                    local_mcpconnector_panel_error_message((string) ($list['error'] ?? ''))
                ),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    } else if ($keyid !== '') {
        $row = $DB->get_record('local_mcpconnector_keys', ['panelkeyid' => $keyid], '*', IGNORE_MISSING);
        if (!$row) {
            redirect(
                $PAGE->url,
                get_string('panel_error_not_found', 'local_mcpconnector'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        // Revoke and regenerate are irreversible: gate them behind a confirmation page.
        if (($action === 'revoke' || $action === 'regenerate') && !$confirm) {
            $confirmuser = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0], '*', IGNORE_MISSING);
            $username = $confirmuser ? fullname($confirmuser) : '-';
            $confirmstr = $action === 'revoke' ? 'key_revoke_confirm' : 'key_regenerate_confirm';
            $continueurl = new moodle_url($PAGE->url, [
                'action' => $action,
                'keyid' => $keyid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            $cancelurl = new moodle_url('/local/mcpconnector/keys.php');
            echo $OUTPUT->header();
            local_mcpconnector_print_tabs('keys');
            echo $OUTPUT->confirm(get_string($confirmstr, 'local_mcpconnector', $username), $continueurl, $cancelurl);
            echo $OUTPUT->footer();
            return;
        }

        if ($action === 'revoke') {
            $result = local_mcpconnector_panel_revoke_key($keyid);
            if ($result['ok'] || ($result['error'] ?? '') === 'not_found') {
                // When revoked, remove the user's tokens and MoodleMCP service assignments.
                if ($DB->record_exists('user', ['id' => $row->userid, 'deleted' => 0])) {
                    local_mcpconnector_revoke_service_tokens((int) $row->userid);
                    $serviceids = local_mcpconnector_get_service_ids();
                    if (!empty($serviceids)) {
                        [$insql, $params] = $DB->get_in_or_equal($serviceids, SQL_PARAMS_NAMED);
                        $params['userid'] = $row->userid;
                        $DB->delete_records_select(
                            'external_services_users',
                            "userid = :userid AND externalserviceid {$insql}",
                            $params
                        );
                    }
                }
                redirect(
                    $PAGE->url,
                    get_string('key_revoked', 'local_mcpconnector'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                redirect(
                    $PAGE->url,
                    get_string('key_revoke_failed', 'local_mcpconnector') . ' '
                    . local_mcpconnector_panel_error_message((string) ($result['error'] ?? '')),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
        } else if ($action === 'suspend') {
            $result = local_mcpconnector_panel_suspend_key($keyid, true);
            if ($result['ok']) {
                redirect(
                    $PAGE->url,
                    get_string('key_suspended', 'local_mcpconnector'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                redirect(
                    $PAGE->url,
                    get_string('key_suspend_failed', 'local_mcpconnector') . ' '
                    . local_mcpconnector_panel_error_message((string) ($result['error'] ?? '')),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
        } else if ($action === 'activate') {
            $result = local_mcpconnector_panel_suspend_key($keyid, false);
            if ($result['ok']) {
                redirect(
                    $PAGE->url,
                    get_string('key_activated', 'local_mcpconnector'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                redirect(
                    $PAGE->url,
                    get_string('key_activate_failed', 'local_mcpconnector') . ' '
                    . local_mcpconnector_panel_error_message((string) ($result['error'] ?? '')),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
        } else if ($action === 'regenerate') {
            // The key value is gone forever after creation, so "send" is now
            // regenerate + email: revoke the old key, mint a new one via the
            // same flow used on assignment, and email the fresh value.
            $user = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0], '*', IGNORE_MISSING);
            if (!$user) {
                redirect(
                    $PAGE->url,
                    get_string('key_regen_failed', 'local_mcpconnector'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            // Revoke the old key first. If that fails (and it wasn't already gone),
            // abort: recalc would otherwise report "regen failed" while the panel key
            // is actually still live — leave a recoverable state and show the real error.
            if ($row->status !== 'revoked') {
                $revokeresult = local_mcpconnector_panel_revoke_key($keyid);
                if (!$revokeresult['ok'] && ($revokeresult['error'] ?? '') !== 'not_found') {
                    redirect(
                        $PAGE->url,
                        get_string('key_revoke_failed', 'local_mcpconnector') . ' '
                        . local_mcpconnector_panel_error_message((string) ($revokeresult['error'] ?? '')),
                        null,
                        \core\output\notification::NOTIFY_ERROR
                    );
                }
            }
            // A successful revoke (or a pre-revoked row) marks the local row revoked,
            // so recalc cannot take the keep-as-is branch and is forced to mint a fresh key.
            $result = local_mcpconnector_recalculate_user_key((int) $user->id);
            if ($result['ok'] && !empty($result['data']['mcpKey'])) {
                $newkey = (string) $result['data']['mcpKey'];
                $panelkeyid = (string) ($result['data']['id'] ?? '');
                if (local_mcpconnector_send_key_email($user, $newkey, local_mcpconnector_get_mcp_url())) {
                    local_mcpconnector_mark_local_key_sent($panelkeyid);
                    redirect(
                        $PAGE->url,
                        get_string('key_sent', 'local_mcpconnector'),
                        null,
                        \core\output\notification::NOTIFY_SUCCESS
                    );
                } else {
                    redirect(
                        $PAGE->url,
                        get_string('key_send_failed', 'local_mcpconnector'),
                        null,
                        \core\output\notification::NOTIFY_ERROR
                    );
                }
            } else {
                redirect(
                    $PAGE->url,
                    get_string('key_regen_failed', 'local_mcpconnector') . ' '
                    . local_mcpconnector_panel_error_message((string) ($result['error'] ?? '')),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
        }
    }
}

echo $OUTPUT->header();

local_mcpconnector_print_tabs('keys');

$licensekey = local_mcpconnector_get_license_key();
if ($licensekey === '' || !local_mcpconnector_license_is_valid()) {
    echo $OUTPUT->notification(get_string('keys_missing_license', 'local_mcpconnector'), 'notifyproblem');
    echo $OUTPUT->footer();
    return;
}

// Refresh from panel action.
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'style' => 'margin-bottom: 1rem;',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'refresh']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('keys_refresh', 'local_mcpconnector'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

$totalkeys = $DB->count_records('local_mcpconnector_keys');
if ($totalkeys === 0) {
    echo $OUTPUT->notification(get_string('keys_empty', 'local_mcpconnector'), 'notifyinfo');
    echo $OUTPUT->footer();
    return;
}

// Pagination.
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;
$rows = $DB->get_records('local_mcpconnector_keys', null, 'timecreated DESC', '*', $page * $perpage, $perpage);

$table = new html_table();
$table->head = [
    get_string('keys_user', 'local_mcpconnector'),
    get_string('keys_key', 'local_mcpconnector'),
    get_string('keys_role', 'local_mcpconnector'),
    get_string('keys_status', 'local_mcpconnector'),
    get_string('keys_sent', 'local_mcpconnector'),
    get_string('keys_created', 'local_mcpconnector'),
    get_string('keys_actions', 'local_mcpconnector'),
];
$table->data = [];

foreach ($rows as $row) {
    $user = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0], '*', IGNORE_MISSING);
    $name = $user ? fullname($user) : '-';

    // Translate roles to human-friendly names.
    $roles = $row->roles !== '' && $row->roles !== null ? explode(',', (string) $row->roles) : [];
    $translatedroles = [];
    foreach ($roles as $r) {
        $translatedroles[] = local_mcpconnector_get_service_display_name(trim($r));
    }
    $role = implode(', ', $translatedroles);

    $status = (string) $row->status;
    $statuskey = 'key_status_' . $status;
    if (get_string_manager()->string_exists($statuskey, 'local_mcpconnector')) {
        $statusdisplay = get_string($statuskey, 'local_mcpconnector');
    } else {
        $statusdisplay = $status;
    }

    $keylast4 = (string) $row->keylast4;
    $keydisplay = $keylast4 !== '' ? '…' . $keylast4 : '-';

    $sent = !empty($row->sentat)
        ? userdate((int) $row->sentat, get_string('strftimedatefullshort', 'langconfig'))
        : '-';
    $created = userdate((int) $row->timecreated, get_string('strftimedatefullshort', 'langconfig'));

    $actions = [];
    $panelkeyid = (string) $row->panelkeyid;

    // Revoked is terminal: no actions remain (re-issue keys from the Users tab).
    if ($status !== 'revoked') {
        if ($status === 'suspended') {
            $actions[] = local_mcpconnector_render_key_action(
                'activate',
                get_string('key_activate', 'local_mcpconnector'),
                $panelkeyid
            );
        } else {
            $actions[] = local_mcpconnector_render_key_action(
                'suspend',
                get_string('key_suspend', 'local_mcpconnector'),
                $panelkeyid
            );
        }
        $actions[] = local_mcpconnector_render_key_action('revoke', get_string('key_revoke', 'local_mcpconnector'), $panelkeyid);
        if ($user) {
            $actions[] = local_mcpconnector_render_key_action(
                'regenerate',
                get_string('key_regenerate_email', 'local_mcpconnector'),
                $panelkeyid
            );
        }
    }

    $table->data[] = [
        s($name),
        s($keydisplay),
        s($role),
        s($statusdisplay),
        s($sent),
        s($created),
        !empty($actions) ? implode(' ', $actions) : '-',
    ];
}

echo html_writer::table($table);

// Pagination bar.
echo $OUTPUT->paging_bar($totalkeys, $page, $perpage, new moodle_url('/local/mcpconnector/keys.php'));

echo $OUTPUT->footer();
