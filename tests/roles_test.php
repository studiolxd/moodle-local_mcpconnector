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
 * Unit tests for the pure role-mapping helpers.
 *
 * These guard the service-shortname ↔ role round-trip: a mismatch there
 * silently corrupts the roles the plugin sends to the panel (rejected by the
 * strict role enum) and the primary-role/token binding, breaking all key
 * provisioning. Renaming the plugin's frankenstyle changes the service
 * prefix, so this must round-trip for every role.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_role_from_service
 */
final class roles_test extends \advanced_testcase {
    /** @var string[] The six roles the panel's enum accepts. */
    private const ROLES = ['admin', 'manager', 'editingteacher', 'teacher', 'student', 'user'];

    /**
     * service_for_role() then role_from_service() must return the original role.
     */
    public function test_service_role_round_trip(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        foreach (self::ROLES as $role) {
            $shortname = local_mcpconnector_service_for_role($role);
            $this->assertSame(
                $role,
                local_mcpconnector_role_from_service($shortname),
                "round-trip failed for role '$role' (service '$shortname')"
            );
        }
    }

    /**
     * The extracted role must be exactly one of the panel-accepted values —
     * an off-by-N prefix strip would leak "or_admin"-style garbage.
     */
    public function test_role_from_service_yields_valid_roles(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        foreach (self::ROLES as $role) {
            $extracted = local_mcpconnector_role_from_service('mcpconnector_' . $role);
            $this->assertContains($extracted, self::ROLES);
        }
    }

    /**
     * A non-plugin shortname is returned unchanged.
     */
    public function test_role_from_service_passthrough(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $this->assertSame('other_thing', local_mcpconnector_role_from_service('other_thing'));
    }

    /**
     * Primary role follows the documented priority order.
     */
    public function test_primary_role_priority(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $this->assertSame('admin', local_mcpconnector_primary_role_for_roles(['student', 'admin', 'teacher']));
        $this->assertSame('teacher', local_mcpconnector_primary_role_for_roles(['student', 'teacher']));
        $this->assertSame('user', local_mcpconnector_primary_role_for_roles([]));
    }
}
