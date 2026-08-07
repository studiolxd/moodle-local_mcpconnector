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
 * Settings page for Moodle MCP.
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

admin_externalpage_setup('local_mcpconnector_settings');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

// Handle form submission manually.
if ($action === 'save' && confirm_sesskey()) {
    // Auto-sync settings for each service.
    $services = ['admin', 'manager', 'editingteacher', 'teacher', 'student', 'user'];
    foreach ($services as $service) {
        $value = optional_param('auto_sync_' . $service, 0, PARAM_INT);
        set_config('auto_sync_' . $service, $value, 'local_mcpconnector');
    }

    $autoemail = optional_param('auto_email', 0, PARAM_INT);
    $emailsubject = optional_param('email_subject', '', PARAM_TEXT);
    $emailbody = optional_param('email_body', '', PARAM_RAW); // Allow raw for placeholders.
    $keylifetime = max(0, optional_param('key_lifetime_days', 0, PARAM_INT));
    $telemetry = optional_param('telemetry_enabled', 0, PARAM_INT);

    set_config('auto_email', $autoemail, 'local_mcpconnector');
    set_config('email_subject', $emailsubject, 'local_mcpconnector');
    set_config('email_body', $emailbody, 'local_mcpconnector');
    set_config('key_lifetime_days', $keylifetime, 'local_mcpconnector');
    set_config('telemetry_enabled', $telemetry ? 1 : 0, 'local_mcpconnector');

    redirect(
        $PAGE->url,
        get_string('changes_saved', 'local_mcpconnector'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

local_mcpconnector_print_tabs('settings');

// Load current values.
$services = ['admin', 'manager', 'editingteacher', 'teacher', 'student', 'user'];
$autosyncvalues = [];
foreach ($services as $service) {
    $autosyncvalues[$service] = (int) get_config('local_mcpconnector', 'auto_sync_' . $service);
}

$autoemail = (int) get_config('local_mcpconnector', 'auto_email');
$emailsubject = get_config('local_mcpconnector', 'email_subject');
if ($emailsubject === false) {
    // Default fallback if not set (though install/upgrade usually sets it).
    $emailsubject = get_string('email_subject_default', 'local_mcpconnector');
}
$emailbody = get_config('local_mcpconnector', 'email_body');
if ($emailbody === false) {
    $emailbody = get_string('email_body_default', 'local_mcpconnector');
}
$keylifetime = (int) get_config('local_mcpconnector', 'key_lifetime_days');
$telemetryenabled = (int) get_config('local_mcpconnector', 'telemetry_enabled');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);


// Auto sync - granular per service.
echo html_writer::tag('h4', get_string('auto_sync_section', 'local_mcpconnector'), ['class' => 'mt-4 mb-3']);

foreach ($services as $service) {
    echo html_writer::start_div('form-item row mb-3');
    echo html_writer::start_div('col-md-9 offset-md-3');
    echo html_writer::start_div('form-check');
    $fieldname = 'auto_sync_' . $service;
    echo html_writer::checkbox(
        $fieldname,
        1,
        $autosyncvalues[$service] == 1,
        '',
        ['class' => 'form-check-input', 'id' => 'id_' . $fieldname]
    );
    echo html_writer::tag(
        'label',
        get_string($fieldname, 'local_mcpconnector'),
        ['class' => 'form-check-label', 'for' => 'id_' . $fieldname]
    );
    echo html_writer::end_div(); // End form-check.
    echo html_writer::div(get_string($fieldname . '_desc', 'local_mcpconnector'), 'form-text text-muted');
    echo html_writer::end_div(); // End col.
    echo html_writer::end_div(); // End form-item.
}

// Key lifetime.
echo html_writer::tag('h4', get_string('keys_section', 'local_mcpconnector'), ['class' => 'mt-4 mb-3']);
echo html_writer::start_div('form-item row mb-3');
echo html_writer::tag(
    'label',
    get_string('key_lifetime_days', 'local_mcpconnector'),
    ['class' => 'col-md-3 col-form-label', 'for' => 'id_key_lifetime_days']
);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'min' => '0',
    'name' => 'key_lifetime_days',
    'id' => 'id_key_lifetime_days',
    'value' => $keylifetime,
    'class' => 'form-control',
]);
echo html_writer::div(get_string('key_lifetime_days_desc', 'local_mcpconnector'), 'form-text text-muted');
echo html_writer::end_div();
echo html_writer::end_div();

// Email section.
echo html_writer::tag('h4', get_string('email_section', 'local_mcpconnector'), ['class' => 'mt-4 mb-3']);

// Auto email.
echo html_writer::start_div('form-item row mb-3');
echo html_writer::start_div('col-md-9 offset-md-3');
echo html_writer::start_div('form-check');
echo html_writer::checkbox('auto_email', 1, $autoemail == 1, '', ['class' => 'form-check-input', 'id' => 'id_auto_email']);
echo html_writer::tag(
    'label',
    get_string('auto_email', 'local_mcpconnector'),
    ['class' => 'form-check-label', 'for' => 'id_auto_email']
);
echo html_writer::end_div(); // End form-check.
echo html_writer::div(get_string('auto_email_desc', 'local_mcpconnector'), 'form-text text-muted');
echo html_writer::end_div();
echo html_writer::end_div();

// Email subject.
echo html_writer::start_div('form-item row mb-3');
echo html_writer::tag(
    'label',
    get_string('email_subject', 'local_mcpconnector'),
    ['class' => 'col-md-3 col-form-label', 'for' => 'id_email_subject']
);
echo html_writer::start_div('col-md-9');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'email_subject',
    'value' => $emailsubject,
    'class' => 'form-control',
    'id' => 'id_email_subject',
]);
echo html_writer::div(get_string('email_subject_desc', 'local_mcpconnector'), 'form-text text-muted');
echo html_writer::end_div();
echo html_writer::end_div();

// Email body.
echo html_writer::start_div('form-item row mb-3');
echo html_writer::tag(
    'label',
    get_string('email_body', 'local_mcpconnector'),
    ['class' => 'col-md-3 col-form-label', 'for' => 'id_email_body']
);
echo html_writer::start_div('col-md-9');
echo html_writer::tag('textarea', s($emailbody), [
    'name' => 'email_body',
    'class' => 'form-control',
    'id' => 'id_email_body',
    'rows' => 10,
]);
echo html_writer::div(get_string('email_body_desc', 'local_mcpconnector'), 'form-text text-muted');
echo html_writer::end_div();
echo html_writer::end_div();

// Telemetry (opt-in).
echo html_writer::tag('h4', get_string('telemetry_section', 'local_mcpconnector'), ['class' => 'mt-4 mb-3']);
echo html_writer::start_div('form-item row mb-3');
echo html_writer::start_div('col-md-9 offset-md-3');
echo html_writer::start_div('form-check');
echo html_writer::checkbox('telemetry_enabled', 1, $telemetryenabled == 1, '',
    ['class' => 'form-check-input', 'id' => 'id_telemetry_enabled']);
echo html_writer::tag(
    'label',
    get_string('telemetry_enabled', 'local_mcpconnector'),
    ['class' => 'form-check-label', 'for' => 'id_telemetry_enabled']
);
echo html_writer::end_div(); // End form-check.
echo html_writer::div(get_string('telemetry_enabled_desc', 'local_mcpconnector'), 'form-text text-muted');
echo html_writer::end_div();
echo html_writer::end_div();

// Submit.
echo html_writer::start_div('form-item row');
echo html_writer::start_div('col-md-9 offset-md-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
