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
 * Version information for the MCP Connector for Moodle.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'local_mcpconnector';
// Public launch renumbering: the 2.x series was internal; 1.0.0 succeeds
// 2.24. Moodle upgrades key on $plugin->version (the date integer), never on
// this label, so updating over any installed 2.x works normally.
$plugin->release      = '1.0.1';
$plugin->version      = 2026081000;
$plugin->requires     = 2023042400; // Moodle 4.2.
$plugin->supported    = [402, 502]; // Moodle 4.2 to 5.2.
$plugin->maturity     = MATURITY_STABLE;
