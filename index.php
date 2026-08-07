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
 * License management page for Moodle MCP.
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

admin_externalpage_setup('local_mcpconnector');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$serviceshortname = optional_param('service', '', PARAM_ALPHANUMEXT);
$notifications = [];

$created = local_mcpconnector_ensure_services();
if ($created > 0) {
    $notifications[] = [
        'message' => get_string('services_created', 'local_mcpconnector', $created),
        'type' => 'notifysuccess',
    ];
}

if ($action === 'save_license' && confirm_sesskey()) {
    $license = optional_param('license_key', '', PARAM_RAW_TRIMMED);
    $panelurl = optional_param('panel_url', '', PARAM_URL);
    $panelsecret = optional_param('panel_secret', '', PARAM_RAW_TRIMMED);
    $mcpurl = optional_param('mcp_url', '', PARAM_URL);

    // Store the non-secret connection settings first: validation signs the request with them.
    set_config('panel_url', rtrim($panelurl, '/'), 'local_mcpconnector');
    set_config('mcp_url', rtrim($mcpurl, '/'), 'local_mcpconnector');

    // Secrets are write-only: only overwrite when a new value is submitted, so a blank
    // field keeps the stored value instead of wiping it.
    if ($panelsecret !== '') {
        set_config('panel_secret', $panelsecret, 'local_mcpconnector');
    }
    if ($license !== '') {
        set_config('license_key', $license, 'local_mcpconnector');
    }

    // Validate the effective license (the freshly submitted one, or the stored one if blank).
    $effectivelicense = $license !== '' ? $license : local_mcpconnector_get_license_key();
    $result = local_mcpconnector_validate_license($effectivelicense);

    set_config('license_status', $result['status'], 'local_mcpconnector');
    set_config('license_checked_at', time(), 'local_mcpconnector');

    if ($result['message'] !== '') {
        set_config('license_last_error', $result['message'], 'local_mcpconnector');
    } else {
        unset_config('license_last_error', 'local_mcpconnector');
    }

    if ($result['status'] === 'ok') {
        redirect(
            $PAGE->url,
            get_string('license_ok', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            $PAGE->url,
            get_string('license_error', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();

if (!empty($notifications)) {
    foreach ($notifications as $notification) {
        echo $OUTPUT->notification($notification['message'], $notification['type']);
    }
}

local_mcpconnector_print_tabs('license');

$licensekey = (string) get_config('local_mcpconnector', 'license_key');
$panelurl = (string) get_config('local_mcpconnector', 'panel_url');
$panelsecret = (string) get_config('local_mcpconnector', 'panel_secret');
$mcpurl = (string) get_config('local_mcpconnector', 'mcp_url');
$licensestatus = (string) get_config('local_mcpconnector', 'license_status');
$licenseerror = (string) get_config('local_mcpconnector', 'license_last_error');
$checkedat = (int) get_config('local_mcpconnector', 'license_checked_at');

// Re-validate against the panel only when the cached status is stale or on
// explicit request. The synchronous signed HTTP call (up to 15s) used to run
// on EVERY page load — a slow/down panel froze the whole admin tab.
$recheck = optional_param('recheck', 0, PARAM_BOOL);
$licensecachettl = 600; // 10 minutes.
$licensestale = $checkedat === 0 || (time() - $checkedat) > $licensecachettl;

if ($licensekey !== '' && ($licensestale || $recheck)) {
    $result = local_mcpconnector_validate_license($licensekey);
    set_config('license_status', $result['status'], 'local_mcpconnector');
    set_config('license_checked_at', time(), 'local_mcpconnector');
    if ($result['message'] !== '') {
        set_config('license_last_error', $result['message'], 'local_mcpconnector');
    } else {
        unset_config('license_last_error', 'local_mcpconnector');
    }
    $licensestatus = $result['status'];
    $licenseerror = $result['message'];
    $checkedat = (int) get_config('local_mcpconnector', 'license_checked_at');
} else if ($licensekey === '' && $licensestatus !== 'missing') {
    set_config('license_status', 'missing', 'local_mcpconnector');
    unset_config('license_last_error', 'local_mcpconnector');
    $licensestatus = 'missing';
    $licenseerror = '';
}

if ($licensestatus !== 'ok') {
    echo $OUTPUT->notification(get_string('license_required', 'local_mcpconnector'), 'warning');
}

echo html_writer::tag('h3', get_string('license_heading', 'local_mcpconnector'));

$statuslabel = get_string('license_status_missing', 'local_mcpconnector');
if ($licensestatus === 'ok') {
    $statuslabel = get_string('license_status_ok', 'local_mcpconnector');
} else if ($licensestatus === 'error') {
    $statuslabel = get_string('license_status_error', 'local_mcpconnector');
}

echo html_writer::tag('p', get_string('license_status_label', 'local_mcpconnector', $statuslabel));

if ($checkedat > 0) {
    echo html_writer::tag('p', get_string('license_checked_at', 'local_mcpconnector', userdate($checkedat)));
}
if ($licensekey !== '') {
    echo html_writer::div(
        $OUTPUT->single_button(
            new moodle_url('/local/mcpconnector/index.php', ['recheck' => 1]),
            get_string('license_recheck', 'local_mcpconnector'),
            'get'
        )
    );
}

if (!empty($licenseerror) && $licensestatus !== 'ok') {
    echo $OUTPUT->notification(s($licenseerror), 'notifyproblem');
}

echo html_writer::tag('p', get_string('panel_pair_notice', 'local_mcpconnector'));

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url,
    'style' => 'margin-bottom: 1.5rem;',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save_license']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('p');
echo html_writer::tag('label', get_string('panel_url', 'local_mcpconnector'), ['for' => 'local_mcpconnector_panel_url']);
echo html_writer::empty_tag('input', [
    'type' => 'url',
    'name' => 'panel_url',
    'id' => 'local_mcpconnector_panel_url',
    'value' => $panelurl !== '' ? $panelurl : local_mcpconnector_api_base_url(),
    'size' => 40,
]);
echo html_writer::end_tag('p');
echo html_writer::tag('p', get_string('panel_url_help', 'local_mcpconnector'));

// Secrets are never echoed back: the field is rendered empty and only overwrites
// the stored value when the admin types a new one.
echo html_writer::start_tag('p');
echo html_writer::tag('label', get_string('license_label', 'local_mcpconnector'), ['for' => 'local_mcpconnector_license']);
echo html_writer::empty_tag('input', [
    'type' => 'password',
    'name' => 'license_key',
    'id' => 'local_mcpconnector_license',
    'value' => '',
    'size' => 40,
    'autocomplete' => 'off',
]);
echo html_writer::end_tag('p');
echo html_writer::tag('p', get_string('license_help', 'local_mcpconnector'));
if ($licensekey !== '') {
    echo html_writer::tag('p', get_string('secret_keep_blank', 'local_mcpconnector'));
}

echo html_writer::start_tag('p');
echo html_writer::tag('label', get_string('panel_secret', 'local_mcpconnector'), ['for' => 'local_mcpconnector_panel_secret']);
echo html_writer::empty_tag('input', [
    'type' => 'password',
    'name' => 'panel_secret',
    'id' => 'local_mcpconnector_panel_secret',
    'value' => '',
    'size' => 40,
    'autocomplete' => 'off',
]);
echo html_writer::end_tag('p');
echo html_writer::tag('p', get_string('panel_secret_help', 'local_mcpconnector'));
if ($panelsecret !== '') {
    echo html_writer::tag('p', get_string('secret_keep_blank', 'local_mcpconnector'));
}

echo html_writer::start_tag('p');
echo html_writer::tag('label', get_string('mcp_url', 'local_mcpconnector'), ['for' => 'local_mcpconnector_mcp_url']);
echo html_writer::empty_tag('input', [
    'type' => 'url',
    'name' => 'mcp_url',
    'id' => 'local_mcpconnector_mcp_url',
    'value' => $mcpurl,
    'size' => 40,
]);
echo html_writer::end_tag('p');
echo html_writer::tag('p', get_string('mcp_url_help', 'local_mcpconnector'));

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('license_save', 'local_mcpconnector'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
