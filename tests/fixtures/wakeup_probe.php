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

namespace local_mcpconnector;

/**
 * Stand-in for a gadget: records whether PHP ever brought it to life.
 *
 * Deserializing this class is what an object-injection payload relies on —
 * unserialize() runs __wakeup() on whatever it builds. Anything reading
 * untrusted serialized data must leave $woken false.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wakeup_probe {
    /** @var bool Set by __wakeup(), so a test can tell whether it ran. */
    public static $woken = false;

    /**
     * Marks the class as instantiated through deserialization.
     *
     * @return void
     */
    public function __wakeup(): void {
        self::$woken = true;
    }
}
