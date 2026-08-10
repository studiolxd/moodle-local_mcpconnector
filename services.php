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
 * Services management page for Moodle MCP.
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

admin_externalpage_setup('local_mcpconnector_services');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$serviceshortname = optional_param('service', '', PARAM_ALPHANUMEXT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Handle restore service action (irreversible: it overwrites the function whitelist).
if ($action === 'restore_service' && confirm_sesskey()) {
    if (!$confirm) {
        $continueurl = new moodle_url($PAGE->url, [
            'action' => 'restore_service',
            'service' => $serviceshortname,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        $cancelurl = new moodle_url('/local/mcpconnector/services.php');
        echo $OUTPUT->header();
        local_mcpconnector_print_tabs('services');
        echo $OUTPUT->confirm(
            get_string('service_restore_confirm', 'local_mcpconnector', $serviceshortname),
            $continueurl,
            $cancelurl
        );
        echo $OUTPUT->footer();
        return;
    }

    if ($serviceshortname !== '' && local_mcpconnector_restore_service_baseline($serviceshortname)) {
        redirect(
            $PAGE->url,
            get_string('service_restored', 'local_mcpconnector', $serviceshortname),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            $PAGE->url,
            get_string('service_restore_failed', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Handle deprovision action (irreversible: revokes every panel key and wipes
// every service/token this plugin has created, but keeps the plugin's
// license/panel connection intact so it can re-provision on its own).
if ($action === 'deprovision' && confirm_sesskey()) {
    if (!$confirm) {
        $continueurl = new moodle_url($PAGE->url, [
            'action' => 'deprovision',
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        $cancelurl = new moodle_url('/local/mcpconnector/services.php');
        echo $OUTPUT->header();
        local_mcpconnector_print_tabs('services');
        echo $OUTPUT->confirm(
            get_string('deprovision_confirm', 'local_mcpconnector'),
            $continueurl,
            $cancelurl
        );
        echo $OUTPUT->footer();
        return;
    }

    $result = local_mcpconnector_deprovision_resources('manual_deprovision');

    if (!$result['panel']['ok']) {
        redirect(
            $PAGE->url,
            get_string(
                'deprovision_panel_warning',
                'local_mcpconnector',
                local_mcpconnector_panel_error_message((string) ($result['panel']['error'] ?? ''))
            ),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    } else {
        redirect(
            $PAGE->url,
            get_string('deprovision_success', 'local_mcpconnector', $result['servicesremoved']),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();

local_mcpconnector_print_tabs('services');

echo html_writer::tag('h3', get_string('services_heading', 'local_mcpconnector'));

$services = local_mcpconnector_get_service_definitions();
$table = new html_table();
$table->head = [
    get_string('services_table_service', 'local_mcpconnector'),
    get_string('services_table_status', 'local_mcpconnector'),
    get_string('services_table_actions', 'local_mcpconnector'),
];
$table->data = [];
foreach ($services as $service) {
    $record = $DB->get_record('external_services', ['shortname' => $service['shortname']], 'id', IGNORE_MISSING);
    $label = local_mcpconnector_get_service_display_name($service['shortname']);
    if ($record) {
        $restoreurl = new moodle_url('/local/mcpconnector/services.php', [
            'action' => 'restore_service',
            'service' => $service['shortname'],
            'sesskey' => sesskey(),
        ]);
        $editfunctions = new moodle_url('/local/mcpconnector/service.php', [
            'service' => $service['shortname'],
        ]);
        $actions = implode(' ', [
            html_writer::tag('a', get_string('editfunctions', 'local_mcpconnector'), [
                'href' => $editfunctions,
                'class' => 'btn btn-secondary',
            ]),
            $OUTPUT->single_button($restoreurl, get_string('service_restore', 'local_mcpconnector'), 'post'),
        ]);
        $table->data[] = [
            $label,
            get_string('ok', 'local_mcpconnector'),
            $actions,
        ];
    } else {
        $table->data[] = [
            $label,
            get_string('missing', 'local_mcpconnector'),
            '-',
        ];
    }
}
echo html_writer::table($table);

$deprovisionurl = new moodle_url('/local/mcpconnector/services.php', [
    'action' => 'deprovision',
    'sesskey' => sesskey(),
]);
echo html_writer::div(
    $OUTPUT->single_button($deprovisionurl, get_string('deprovision', 'local_mcpconnector'), 'post'),
    '',
    ['style' => 'margin-top: 1.5rem;']
);
echo html_writer::tag('p', get_string('deprovision_help', 'local_mcpconnector'));

echo $OUTPUT->footer();
