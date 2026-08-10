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
 * Health tab: connection state, keys by status, sync and versions at a
 * glance — everything support asks for, on one page.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

admin_externalpage_setup('local_mcpconnector_health');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'sendtelemetry' && confirm_sesskey()) {
    $result = local_mcpconnector_send_telemetry(true);
    redirect(
        $PAGE->url,
        $result['ok']
            ? get_string('health_telemetry_sent', 'local_mcpconnector')
            : get_string('health_telemetry_failed', 'local_mcpconnector', s((string) $result['error'])),
        null,
        $result['ok']
            ? \core\output\notification::NOTIFY_SUCCESS
            : \core\output\notification::NOTIFY_ERROR
    );
}

echo $OUTPUT->header();
local_mcpconnector_print_tabs('health');
echo $OUTPUT->heading(get_string('health_heading', 'local_mcpconnector'));

$stryes = get_string('yes');
$strno = get_string('no');
$strnever = get_string('never');

// Panel connectivity (cached — the license tab owns the live re-check).
$licensestatus = (string) get_config('local_mcpconnector', 'license_status');
$checkedat = (int) get_config('local_mcpconnector', 'license_checked_at');
$lasterror = (string) get_config('local_mcpconnector', 'license_last_error');

$rows = [];
$rows[] = [
    get_string('health_panel_status', 'local_mcpconnector'),
    $licensestatus === 'ok'
        ? html_writer::span(get_string('license_status_ok', 'local_mcpconnector'), 'badge badge-success')
        : html_writer::span(s($licensestatus), 'badge badge-danger')
        . ($lasterror !== '' ? ' — ' . s($lasterror) : ''),
];
$rows[] = [
    get_string('health_panel_checked', 'local_mcpconnector'),
    $checkedat ? userdate($checkedat) : $strnever,
];
$rows[] = [get_string('panel_url', 'local_mcpconnector'), s(local_mcpconnector_api_base_url())];

// Keys by status (local metadata table).
global $DB;
$keycounts = ['active' => 0, 'suspended' => 0, 'revoked' => 0];
foreach (
    $DB->get_records_sql(
        "SELECT status, COUNT(*) AS c FROM {local_mcpconnector_keys} GROUP BY status"
    ) as $row
) {
    $keycounts[$row->status] = (int) $row->c;
}
$expired = (int) $DB->count_records_select(
    'local_mcpconnector_keys',
    "status = 'active' AND expiresat > 0 AND expiresat < ?",
    [time()]
);
$rows[] = [
    get_string('health_keys', 'local_mcpconnector'),
    get_string('health_keys_detail', 'local_mcpconnector', (object) [
        'active' => $keycounts['active'],
        'suspended' => $keycounts['suspended'],
        'revoked' => $keycounts['revoked'],
        'expired' => $expired,
    ]),
];

// Sync.
$task = $DB->get_record('task_scheduled', [
    'component' => 'local_mcpconnector',
    'classname' => '\local_mcpconnector\task\sync_users',
]);
$rows[] = [
    get_string('health_last_sync', 'local_mcpconnector'),
    !empty($task->lastruntime) ? userdate((int) $task->lastruntime) : $strnever,
];
$autoservices = [];
foreach (['admin', 'manager', 'editingteacher', 'teacher', 'student', 'user'] as $service) {
    if ((int) get_config('local_mcpconnector', 'auto_sync_' . $service)) {
        $autoservices[] = $service;
    }
}
$rows[] = [
    get_string('health_auto_sync', 'local_mcpconnector'),
    $autoservices === [] ? $strno : s(implode(', ', $autoservices)),
];

// Versions.
$info = \core_plugin_manager::instance()->get_plugin_info('local_mcpconnector');
$rows[] = [
    get_string('health_versions', 'local_mcpconnector'),
    'plugin ' . s((string) ($info->release ?? '?'))
        . ' · Moodle ' . s((string) $CFG->release)
        . ' · PHP ' . s(PHP_VERSION),
];

// Telemetry.
$telemetryon = (int) get_config('local_mcpconnector', 'telemetry_enabled');
$sentat = (int) get_config('local_mcpconnector', 'telemetry_sent_at');
$rows[] = [
    get_string('health_telemetry', 'local_mcpconnector'),
    ($telemetryon ? $stryes : $strno)
        . ' — ' . get_string('health_telemetry_last', 'local_mcpconnector')
        . ' ' . ($sentat ? userdate($sentat) : $strnever),
];

$table = new html_table();
$table->data = $rows;
echo html_writer::table($table);

if ($telemetryon) {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'sendtelemetry', 'sesskey' => sesskey()]),
        get_string('health_telemetry_send', 'local_mcpconnector'),
        'post'
    );
} else {
    echo html_writer::tag('p', get_string('health_telemetry_hint', 'local_mcpconnector'));
}

echo $OUTPUT->footer();
