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
 * Form to edit service functions.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Service functions form.
 */
class service_functions_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $shortname = $this->_customdata['shortname'] ?? '';
        $choices = $this->_customdata['choices'] ?? [];

        $mform->addElement('hidden', 'service', $shortname);
        $mform->setType('service', PARAM_ALPHANUMEXT);

        $mform->addElement('autocomplete', 'functions', get_string('service_functions', 'local_mcpconnector'), $choices, [
            'multiple' => true,
        ]);
        $mform->setType('functions', PARAM_RAW_TRIMMED);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
