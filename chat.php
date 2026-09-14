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
 * Chat identity page for Studio LXD: the service account the assistant uses.
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

admin_externalpage_setup('local_mcpconnector_chat');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$check = optional_param('check', 0, PARAM_BOOL);

$roleoptions = local_mcpconnector_chat_role_options();
$licenseok = local_mcpconnector_license_is_valid();

if ($action === 'provision' && confirm_sesskey()) {
    $role = required_param('role', PARAM_ALPHANUMEXT);

    if (!$licenseok) {
        redirect(
            $PAGE->url,
            get_string('chat_missing_license', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if (!isset($roleoptions[$role])) {
        redirect(
            $PAGE->url,
            get_string('chat_error_role_not_available', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    // Both creating and regenerating hand a site-wide identity to everyone in
    // the organisation, and regenerating cuts the previous one off: say so once
    // more, with the chosen role spelled out, before anything happens.
    if (!$confirm) {
        $existing = local_mcpconnector_chat_userid() > 0;
        $continueurl = new moodle_url('/local/mcpconnector/chat.php', [
            'action' => 'provision',
            'role' => $role,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->header();
        local_mcpconnector_print_tabs('chat');
        echo $OUTPUT->confirm(
            get_string(
                $existing ? 'chat_regenerate_confirm' : 'chat_create_confirm',
                'local_mcpconnector',
                $roleoptions[$role]
            ),
            $continueurl,
            $PAGE->url
        );
        echo $OUTPUT->footer();
        die();
    }

    $result = local_mcpconnector_chat_provision($role);
    if ($result['ok']) {
        // A fresh identity has never been verified: the next page load asks the
        // panel itself rather than claiming success on our own word.
        redirect(
            new moodle_url('/local/mcpconnector/chat.php', ['check' => 1]),
            get_string('chat_provision_success', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    redirect(
        $PAGE->url,
        get_string(
            'chat_provision_failed',
            'local_mcpconnector',
            local_mcpconnector_chat_error_message((string) $result['error'])
        ),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Neither the panel nor the token is tried on every load: this page is not
// worth two HTTP round trips per refresh (the License tab learnt that the hard
// way). "Check now" is what asks for the real thing. The token probe does not
// need the license — it is a call to this very site.
$status = local_mcpconnector_chat_status((bool) $check && $licenseok, (bool) $check);
if ($check) {
    set_config('chat_checked_at', time(), 'local_mcpconnector');
    set_config(
        'chat_token_seen',
        $status['tokenworks'] === null ? -1 : (int) $status['tokenworks'],
        'local_mcpconnector'
    );
    set_config('chat_token_error', (string) ($status['tokenerror'] ?? ''), 'local_mcpconnector');
    if ($licenseok) {
        set_config(
            'chat_panel_seen',
            $status['panelknown'] === null ? -1 : (int) $status['panelknown'],
            'local_mcpconnector'
        );
    }
} else {
    // Show what the last real check found, rather than nothing at all.
    if ($status['registered']) {
        $seen = get_config('local_mcpconnector', 'chat_panel_seen');
        if ($seen !== false && (int) $seen !== -1) {
            $status['panelknown'] = (bool) (int) $seen;
        }
    }
    if ($status['tokenok']) {
        $tokenseen = get_config('local_mcpconnector', 'chat_token_seen');
        if ($tokenseen !== false && (int) $tokenseen !== -1) {
            $status['tokenworks'] = (bool) (int) $tokenseen;
            $status['tokenerror'] = (string) get_config('local_mcpconnector', 'chat_token_error') ?: null;
        }
    }
}
$checkedat = (int) get_config('local_mcpconnector', 'chat_checked_at');

echo $OUTPUT->header();

local_mcpconnector_print_tabs('chat');

echo html_writer::tag('h3', get_string('chat_heading', 'local_mcpconnector'));
echo html_writer::tag('p', get_string('chat_intro', 'local_mcpconnector'));

if (!$licenseok) {
    echo $OUTPUT->notification(get_string('chat_missing_license', 'local_mcpconnector'), 'warning');
}

echo local_mcpconnector_render_panel_changed_notice();

// The status block.
echo html_writer::tag('h4', get_string('chat_status_heading', 'local_mcpconnector'));

if (local_mcpconnector_chat_userid() === 0 && !$status['registered']) {
    echo $OUTPUT->notification(get_string('chat_status_none', 'local_mcpconnector'), 'info');
} else if ($status['ready']) {
    echo $OUTPUT->notification(get_string('chat_status_ready', 'local_mcpconnector'), 'success');
} else {
    echo $OUTPUT->notification(get_string('chat_status_incomplete', 'local_mcpconnector'), 'warning');
}

$lines = [];

if ($status['user']) {
    $lines[] = get_string('chat_user_ok', 'local_mcpconnector', (object) [
        'name' => fullname($status['user']),
        'username' => $status['user']->username,
        'email' => $status['user']->email,
    ]);
    // 1.3.0 left this account on `nologin`, which Moodle reads as "disabled":
    // say so plainly instead of showing the account as correct.
    $lines[] = $status['authok']
        ? get_string('chat_auth_ok', 'local_mcpconnector', s($status['user']->auth))
        : get_string('chat_auth_broken', 'local_mcpconnector', s($status['user']->auth));
} else {
    $lines[] = get_string('chat_user_missing', 'local_mcpconnector');
}

if ($status['role'] === '') {
    $lines[] = get_string('chat_role_none', 'local_mcpconnector');
} else {
    $rolename = $roleoptions[$status['role']] ?? $status['role'];
    $lines[] = $status['roleok']
        ? get_string('chat_role_ok', 'local_mcpconnector', $rolename)
        : get_string('chat_role_missing', 'local_mcpconnector', $rolename);
}

$lines[] = $status['authorized']
    ? get_string('chat_service_ok', 'local_mcpconnector')
    : get_string('chat_service_missing', 'local_mcpconnector');

$lines[] = $status['tokenok']
    ? get_string('chat_token_ok', 'local_mcpconnector')
    : get_string('chat_token_missing', 'local_mcpconnector');

// Having a token is not having a working token: the call itself is the check.
if ($status['tokenok']) {
    if ($status['tokenworks'] === true) {
        $lines[] = get_string('chat_token_works', 'local_mcpconnector');
    } else if ($status['tokenworks'] === false) {
        $lines[] = get_string(
            'chat_token_broken',
            'local_mcpconnector',
            s((string) ($status['tokenerror'] ?? ''))
        );
    } else {
        $lines[] = get_string('chat_token_untested', 'local_mcpconnector');
    }
}

if (!$status['registered']) {
    $lines[] = get_string('chat_panel_missing', 'local_mcpconnector');
} else if ($status['foreign']) {
    $lines[] = get_string('chat_panel_foreign', 'local_mcpconnector');
} else if ($status['panelknown'] === false) {
    $lines[] = get_string('chat_panel_gone', 'local_mcpconnector');
} else if ($status['panelknown'] === true) {
    $lines[] = get_string('chat_panel_ok', 'local_mcpconnector', $status['keylast4']);
} else {
    $lines[] = get_string('chat_panel_unknown', 'local_mcpconnector', $status['keylast4']);
}

echo html_writer::alist($lines);

if (!empty($status['panelerror'])) {
    echo $OUTPUT->notification(
        local_mcpconnector_panel_error_message((string) $status['panelerror']),
        'notifyproblem'
    );
}

if ($status['registeredat'] > 0) {
    echo html_writer::tag(
        'p',
        get_string('chat_registered_at', 'local_mcpconnector', userdate($status['registeredat']))
    );
}
if ($checkedat > 0) {
    echo html_writer::tag(
        'p',
        get_string('chat_panel_checked_at', 'local_mcpconnector', userdate($checkedat))
    );
}

echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/local/mcpconnector/chat.php', ['check' => 1]),
        get_string('chat_check', 'local_mcpconnector'),
        'get'
    ),
    'mb-3'
);

// The role choice and the button that does the work.
echo html_writer::tag('h4', get_string('chat_role_select', 'local_mcpconnector'));

if (empty($roleoptions)) {
    echo $OUTPUT->notification(get_string('chat_no_roles', 'local_mcpconnector'), 'notifyproblem');
    echo $OUTPUT->footer();
    die();
}

echo html_writer::tag('p', get_string('chat_role_select_help', 'local_mcpconnector'));

// The scope of what is being granted, before the button and in plain words —
// this is a site-wide identity shared by every member of the organisation.
echo $OUTPUT->notification(
    html_writer::div(get_string('chat_scope_warning', 'local_mcpconnector'))
        . html_writer::div(get_string('chat_scope_narrow', 'local_mcpconnector'), 'mt-2'),
    'warning',
    false
);

$selectedrole = $status['role'] !== '' && isset($roleoptions[$status['role']])
    ? $status['role']
    : (isset($roleoptions['manager']) ? 'manager' : array_key_first($roleoptions));

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'provision']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('p');
echo html_writer::tag('label', get_string('chat_role_label', 'local_mcpconnector'), [
    'for' => 'local_mcpconnector_chat_role',
    'class' => 'mr-2',
]);
echo html_writer::select($roleoptions, 'role', $selectedrole, false, [
    'id' => 'local_mcpconnector_chat_role',
]);
echo html_writer::end_tag('p');

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => local_mcpconnector_chat_userid() > 0
        ? get_string('chat_regenerate', 'local_mcpconnector')
        : get_string('chat_create', 'local_mcpconnector'),
    'disabled' => $licenseok ? null : 'disabled',
]);
echo html_writer::end_tag('form');

echo html_writer::tag('p', get_string('chat_key_never_shown', 'local_mcpconnector'), ['class' => 'mt-3']);

echo $OUTPUT->footer();
