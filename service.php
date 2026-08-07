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
 * Edit functions for a MoodleMCP external service.
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

$shortname = required_param('service', PARAM_ALPHANUMEXT);

admin_externalpage_setup(
    'local_mcpconnector_services',
    '',
    null,
    new moodle_url('/local/mcpconnector/service.php', ['service' => $shortname])
);

$definitions = local_mcpconnector_get_service_definitions();
$validshortnames = array_column($definitions, 'shortname');
if (!in_array($shortname, $validshortnames, true)) {
    throw new moodle_exception('invalidservice', 'local_mcpconnector');
}

local_mcpconnector_ensure_services();

$service = $DB->get_record('external_services', ['shortname' => $shortname], '*', IGNORE_MISSING);
if (!$service) {
    throw new moodle_exception('missingservice', 'local_mcpconnector');
}

$current = $DB->get_records_menu(
    'external_services_functions',
    ['externalserviceid' => $service->id],
    '',
    'functionname,functionname'
);
$choices = local_mcpconnector_get_external_function_choices();

$PAGE->set_title(get_string('editfunctions', 'local_mcpconnector'));
$PAGE->set_heading(get_string('editfunctions', 'local_mcpconnector'));

$form = new \local_mcpconnector\form\service_functions_form(null, [
    'shortname' => $shortname,
    'choices' => $choices,
]);
$form->set_data([
    'service' => $shortname,
    'functions' => array_keys($current),
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/mcpconnector/index.php'));
}

if ($data = $form->get_data()) {
    $functions = $data->functions ?? [];
    if (!is_array($functions)) {
        $functions = [$functions];
    }
    local_mcpconnector_set_service_functions((int) $service->id, $functions);
    redirect(
        new moodle_url('/local/mcpconnector/index.php'),
        get_string('service_updated', 'local_mcpconnector', $shortname),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}


echo $OUTPUT->header();

local_mcpconnector_print_tabs('services');



echo $OUTPUT->heading(get_string('service_edit_heading', 'local_mcpconnector', $shortname), 3);

$form->display();

echo $OUTPUT->footer();
