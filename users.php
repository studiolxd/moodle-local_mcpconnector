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
 * User management for MoodleMCP services.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
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
require_once($CFG->dirroot . '/user/selector/lib.php');

local_mcpconnector_ensure_services();

$definitions = local_mcpconnector_get_service_definitions();
$shortnames = array_column($definitions, 'shortname');
$service = optional_param('service', $shortnames[0] ?? '', PARAM_ALPHANUMEXT);
if (!in_array($service, $shortnames, true) && !empty($shortnames)) {
    $service = $shortnames[0];
}

admin_externalpage_setup('local_mcpconnector_users', '', ['service' => $service]);

$serviceid = local_mcpconnector_get_service_id($service);
if (!$serviceid) {
    throw new moodle_exception('missingservice', 'local_mcpconnector');
}

$context = context_system::instance();

$selectoroptions = [
    'service' => $service,
    'context' => $context,
    'preserveselected' => true,
    'autoselectunique' => true,
    'searchanywhere' => true,
    'perpage' => 100,
];
$potentialselector = new \local_mcpconnector\selector\potential_users('addselect', $selectoroptions + [
    'file' => 'local/mcpconnector/classes/selector/potential_users.php',
]);
$existingselector = new \local_mcpconnector\selector\existing_users('removeselect', $selectoroptions + [
    'file' => 'local/mcpconnector/classes/selector/existing_users.php',
]);

$license = local_mcpconnector_get_license_key();

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    if ($license === '' || !local_mcpconnector_license_is_valid()) {
        redirect(
            $PAGE->url,
            get_string('keys_missing_license', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $selected = $potentialselector->get_selected_users();
    $added = 0;
    $failed = 0;
    $errorcodes = [];
    if ($selected) {
        foreach ($selected as $user) {
            try {
                $result = local_mcpconnector_assign_user_to_service((int) $user->id, $service);
                if ($result['ok']) {
                    $added++;
                } else {
                    $failed++;
                    if (!empty($result['error'])) {
                        $errorcodes[] = (string) $result['error'];
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                debugging('local_mcpconnector: assign_user_to_service threw: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
    $parts = [];
    $type = \core\output\notification::NOTIFY_SUCCESS;
    if ($added > 0) {
        $parts[] = $added === 1
            ? get_string('users_added_singular', 'local_mcpconnector')
            : get_string('users_added_plural', 'local_mcpconnector', $added);
    }
    if ($failed > 0) {
        $parts[] = $failed === 1
            ? get_string('users_add_failed_singular', 'local_mcpconnector')
            : get_string('users_add_failed_plural', 'local_mcpconnector', $failed);
        if (!empty($errorcodes)) {
            // Show one translated reason; keep raw panel codes to developer debugging only.
            $parts[] = local_mcpconnector_panel_error_message((string) reset($errorcodes));
            debugging('local_mcpconnector: assign errors: ' . implode(', ', array_unique($errorcodes)), DEBUG_DEVELOPER);
        }
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($PAGE->url, implode(' ', $parts), null, $type);
}

if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $selected = $existingselector->get_selected_users();
    $removed = 0;
    $errorcodes = [];
    if ($selected) {
        foreach ($selected as $user) {
            $DB->delete_records('external_services_users', [
                'externalserviceid' => $serviceid,
                'userid' => $user->id,
            ]);
            $recalc = local_mcpconnector_recalculate_user_key((int) $user->id);
            // An ok=true result covers both a downgraded key and a clean deletion ('key_deleted').
            // A real failure (e.g. the panel revoke errored) must be surfaced, not swallowed.
            if (!empty($recalc['ok'])) {
                $removed++;
            } else {
                $errorcodes[] = (string) ($recalc['error'] ?? '');
            }
        }
    }
    $parts = [];
    $type = \core\output\notification::NOTIFY_SUCCESS;
    if ($removed > 0) {
        $parts[] = $removed === 1
            ? get_string('users_removed_singular', 'local_mcpconnector')
            : get_string('users_removed_plural', 'local_mcpconnector', $removed);
    }
    if (!empty($errorcodes)) {
        $parts[] = local_mcpconnector_panel_error_message((string) reset($errorcodes));
        debugging('local_mcpconnector: remove recalc errors: ' . implode(', ', array_unique($errorcodes)), DEBUG_DEVELOPER);
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($PAGE->url, implode(' ', $parts), null, $type);
}

if (optional_param('syncall', false, PARAM_BOOL) && confirm_sesskey()) {
    if ($license === '' || !local_mcpconnector_license_is_valid()) {
        redirect(
            $PAGE->url,
            get_string('keys_missing_license', 'local_mcpconnector'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $task = new \local_mcpconnector\task\sync_all_users_adhoc();
    $task->set_custom_data(['servicefilter' => $service]);
    $task->set_component('local_mcpconnector');
    \core\task\manager::queue_adhoc_task($task, true);

    redirect(
        $PAGE->url,
        get_string('users_sync_queued', 'local_mcpconnector'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

local_mcpconnector_print_tabs('users');


$options = [];
foreach ($definitions as $definition) {
    $options[$definition['shortname']] = local_mcpconnector_get_service_display_name($definition['shortname']);
}

$select = new single_select(new moodle_url('/local/mcpconnector/users.php'), 'service', $options, $service);
$select->set_label(get_string('tab_services', 'local_mcpconnector'));
echo $OUTPUT->render($select);

echo html_writer::start_tag('div', [
    'id' => 'addadmisform',
]);

echo html_writer::tag('h3', get_string('users_manage', 'local_mcpconnector'), ['class' => 'main']);

echo html_writer::start_tag('form', ['id' => 'assignform', 'method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('table', [
    'class' => 'table generaltable groupmanagementtable table-hover',
    'summary' => '',
]);
echo html_writer::start_tag('tbody');
echo html_writer::start_tag('tr');

// Existing users cell.
echo html_writer::start_tag('td', ['id' => 'existingcell']);
echo html_writer::tag('p', html_writer::tag(
    'label',
    get_string('users_assigned', 'local_mcpconnector'),
    ['for' => 'removeselect']
));
$existingselector->display();
echo html_writer::end_tag('td');

// Buttons cell.
echo html_writer::start_tag('td', ['id' => 'buttonscell']);
echo html_writer::start_tag('p', ['class' => 'arrow_button']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'add',
    'id' => 'add',
    'value' => get_string('users_add', 'local_mcpconnector'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'remove',
    'id' => 'remove',
    'value' => get_string('users_remove', 'local_mcpconnector'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::empty_tag('br');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'syncall',
    'id' => 'syncall',
    'value' => get_string('users_sync_all', 'local_mcpconnector'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('p');
echo html_writer::end_tag('td');

// Potential users cell.
echo html_writer::start_tag('td', ['id' => 'potentialcell']);
echo html_writer::tag('p', html_writer::tag('label', get_string('users_available', 'local_mcpconnector'), ['for' => 'addselect']));
$potentialselector->display();
echo html_writer::end_tag('td');

echo html_writer::end_tag('tr');
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::end_tag('form');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
