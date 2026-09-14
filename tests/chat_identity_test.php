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
 * Tests for the chat identity's Moodle side: the service account, its role and
 * the status the Chat tab reads back.
 *
 * Everything here stops short of the panel — registering the key is a signed
 * HTTP call — so what is pinned down is the part an administrator can break by
 * hand: deleting the account, taking its role away, dropping its token.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_chat_ensure_user
 * @covers     \local_mcpconnector_chat_assign_role
 * @covers     \local_mcpconnector_chat_status
 * @covers     \local_mcpconnector_chat_role_options
 */
final class chat_identity_test extends \advanced_testcase {
    /**
     * Loads the plugin library and seeds its services.
     */
    private function require_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');
        local_mcpconnector_ensure_services();
    }

    public function test_service_account_has_no_interactive_access(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $user = local_mcpconnector_chat_ensure_user();

        $this->assertSame(LOCAL_MCPCONNECTOR_CHAT_USERNAME, $user->username);
        $this->assertSame('nologin', $user->auth);
        // The sentinel core uses for "there is no usable password here".
        $this->assertSame(AUTH_PASSWORD_NOT_CACHED, $user->password);
        $this->assertSame(LOCAL_MCPCONNECTOR_CHAT_EMAIL, $user->email);
        // A reserved TLD: the address can never be delivered to a real person.
        $this->assertStringEndsWith('.invalid', $user->email);
        $this->assertEquals(1, $user->emailstop);
    }

    public function test_ensure_user_reuses_the_existing_account(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $first = local_mcpconnector_chat_ensure_user();
        set_config('chat_userid', $first->id, 'local_mcpconnector');

        $second = local_mcpconnector_chat_ensure_user();

        $this->assertEquals($first->id, $second->id);
    }

    public function test_ensure_user_adopts_the_account_when_the_id_was_lost(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $first = local_mcpconnector_chat_ensure_user();
        // A restored database or a wiped config: the account is there, the id is not.
        unset_config('chat_userid', 'local_mcpconnector');

        $second = local_mcpconnector_chat_ensure_user();

        $this->assertEquals($first->id, $second->id);
    }

    public function test_role_is_assigned_at_system_level_and_replaced(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $user = local_mcpconnector_chat_ensure_user();

        $this->assertTrue(local_mcpconnector_chat_assign_role((int) $user->id, 'manager'));
        $this->assertTrue(local_mcpconnector_user_has_system_role((int) $user->id, ['manager']));

        $this->assertTrue(local_mcpconnector_chat_assign_role((int) $user->id, 'student', 'manager'));
        $this->assertTrue(local_mcpconnector_user_has_system_role((int) $user->id, ['student']));
        // The previous role is withdrawn: one identity, one scope.
        $this->assertFalse(local_mcpconnector_user_has_system_role((int) $user->id, ['manager']));
    }

    public function test_role_options_only_offer_roles_the_site_has(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_lib();

        $options = local_mcpconnector_chat_role_options();

        $this->assertArrayHasKey('manager', $options);
        $this->assertArrayHasKey('student', $options);
        // No stock Moodle has a role with shortname 'admin' — site administrators
        // are a config list, so the option must not appear out of thin air.
        $this->assertSame(
            $DB->record_exists('role', ['shortname' => 'admin']),
            array_key_exists('admin', $options)
        );
        foreach (array_keys($options) as $shortname) {
            $this->assertTrue($DB->record_exists('role', ['shortname' => $shortname]));
        }
    }

    public function test_status_reports_a_complete_identity_as_incomplete_without_a_key(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $user = local_mcpconnector_chat_ensure_user();
        set_config('chat_userid', $user->id, 'local_mcpconnector');
        set_config('chat_role', 'manager', 'local_mcpconnector');
        local_mcpconnector_chat_assign_role((int) $user->id, 'manager');
        $serviceid = local_mcpconnector_get_service_id('mcpconnector_manager');
        local_mcpconnector_authorize_user_for_service((int) $user->id, (int) $serviceid);
        local_mcpconnector_rotate_user_token((int) $user->id, (int) $serviceid, 0);

        $status = local_mcpconnector_chat_status();

        $this->assertNotNull($status['user']);
        $this->assertTrue($status['roleok']);
        $this->assertTrue($status['authorized']);
        $this->assertTrue($status['tokenok']);
        // Nothing registered in the panel yet, so the chat cannot work.
        $this->assertFalse($status['registered']);
        $this->assertFalse($status['ready']);
    }

    public function test_status_notices_the_role_was_taken_away(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $user = local_mcpconnector_chat_ensure_user();
        set_config('chat_userid', $user->id, 'local_mcpconnector');
        set_config('chat_role', 'manager', 'local_mcpconnector');
        local_mcpconnector_chat_assign_role((int) $user->id, 'manager');

        // Somebody removes the role by hand.
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_unassign($roleid, (int) $user->id, \context_system::instance()->id);

        $status = local_mcpconnector_chat_status();

        $this->assertFalse($status['roleok']);
        $this->assertFalse($status['ready']);
    }

    public function test_status_notices_the_account_was_deleted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $user = local_mcpconnector_chat_ensure_user();
        set_config('chat_userid', $user->id, 'local_mcpconnector');
        set_config('chat_role', 'manager', 'local_mcpconnector');

        delete_user($user);

        $status = local_mcpconnector_chat_status();

        $this->assertNull($status['user']);
        $this->assertFalse($status['ready']);
    }

    public function test_provision_refuses_to_create_anything_without_a_valid_license(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->require_lib();

        $result = local_mcpconnector_chat_provision('manager');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_license', $result['error']);
        $this->assertFalse($DB->record_exists('user', ['username' => LOCAL_MCPCONNECTOR_CHAT_USERNAME]));
    }
}
