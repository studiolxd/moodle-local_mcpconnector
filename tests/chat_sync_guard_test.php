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
 * The chat service account must be invisible to the automatic provisioning.
 *
 * It holds a role at system level, which is exactly what the role-driven sync
 * looks for: left unguarded it would mint the chat account a second, personal
 * key, email it to a mailbox that cannot exist, and churn it on every run.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_is_chat_user
 * @covers     \local_mcpconnector_recalculate_user_key
 * @covers     \local_mcpconnector_sync_user_auto
 * @covers     \local_mcpconnector_delete_user_keys
 */
final class chat_sync_guard_test extends \advanced_testcase {
    /**
     * Loads the plugin library, seeds its services and the chat account.
     *
     * @return \stdClass The chat service account, with a manager role and a token.
     */
    private function seed_chat_identity(): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');

        local_mcpconnector_ensure_services();

        $user = local_mcpconnector_chat_ensure_user();
        set_config('chat_userid', $user->id, 'local_mcpconnector');
        set_config('chat_role', 'manager', 'local_mcpconnector');
        local_mcpconnector_chat_assign_role((int) $user->id, 'manager');

        $serviceid = (int) local_mcpconnector_get_service_id('mcpconnector_manager');
        local_mcpconnector_authorize_user_for_service((int) $user->id, $serviceid);
        local_mcpconnector_rotate_user_token((int) $user->id, $serviceid, 0);

        return $user;
    }

    public function test_chat_user_is_recognised_and_others_are_not(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $chat = $this->seed_chat_identity();
        $other = $this->getDataGenerator()->create_user();

        $this->assertTrue(local_mcpconnector_is_chat_user((int) $chat->id));
        $this->assertFalse(local_mcpconnector_is_chat_user((int) $other->id));
        $this->assertFalse(local_mcpconnector_is_chat_user(0));
    }

    public function test_recalculate_leaves_the_chat_identity_alone(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $chat = $this->seed_chat_identity();
        $serviceid = (int) local_mcpconnector_get_service_id('mcpconnector_manager');
        $token = local_mcpconnector_get_user_service_token((int) $chat->id, $serviceid);

        $result = local_mcpconnector_recalculate_user_key((int) $chat->id);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        // No personal key row, and the chat's token is untouched.
        $this->assertFalse($DB->record_exists('local_mcpconnector_keys', ['userid' => $chat->id]));
        $this->assertSame($token, local_mcpconnector_get_user_service_token((int) $chat->id, $serviceid));
    }

    public function test_auto_sync_leaves_the_chat_identity_alone(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $chat = $this->seed_chat_identity();
        // A validated license and every auto-sync flag on: the worst case for
        // the guard, since nothing else would stop the sync here.
        set_config('license_status', 'ok', 'local_mcpconnector');
        set_config('auto_sync_manager', 1, 'local_mcpconnector');

        $result = local_mcpconnector_sync_user_auto($chat);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['added']);
        $this->assertSame(0, $result['removed']);
        $this->assertFalse($DB->record_exists('local_mcpconnector_keys', ['userid' => $chat->id]));
    }

    public function test_assign_to_service_refuses_the_chat_identity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $chat = $this->seed_chat_identity();
        set_config('license_status', 'ok', 'local_mcpconnector');

        $result = local_mcpconnector_assign_user_to_service((int) $chat->id, 'mcpconnector_manager');

        $this->assertFalse($result['ok']);
        $this->assertSame('chat_user', $result['error']);
    }

    public function test_deleting_the_chat_account_forgets_its_panel_key(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $chat = $this->seed_chat_identity();
        set_config('chat_panelkeyid', '11111111-2222-3333-4444-555555555555', 'local_mcpconnector');
        set_config('chat_keylast4', 'wxyz', 'local_mcpconnector');

        // The panel is unreachable here, but the local bookkeeping must not keep
        // pointing at a key whose token no longer exists.
        local_mcpconnector_delete_user_keys((int) $chat->id);

        $this->assertEmpty(get_config('local_mcpconnector', 'chat_panelkeyid'));
        $this->assertEmpty(get_config('local_mcpconnector', 'chat_keylast4'));
        $this->assertNull(
            local_mcpconnector_get_user_service_token(
                (int) $chat->id,
                (int) local_mcpconnector_get_service_id('mcpconnector_manager')
            )
        );
    }
}
