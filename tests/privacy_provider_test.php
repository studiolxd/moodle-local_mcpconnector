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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_mcpconnector\privacy\provider;

/**
 * Privacy API tests: the plugin stores key METADATA per user at system
 * context — GDPR requests must find, export and delete it.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Inserts a key metadata row for a user.
     *
     * @param int $userid
     * @return int Row id.
     */
    private function make_key_row(int $userid): int {
        global $DB;
        return (int) $DB->insert_record('local_mcpconnector_keys', (object) [
            'userid' => $userid,
            // A well-formed 36-char uuid, unique per user (12-hex last segment).
            'panelkeyid' => '6f9619ff-8b86-4d01-b42d-' . str_pad(dechex($userid), 12, '0', STR_PAD_LEFT),
            'keylast4' => 'wxyz',
            'roles' => 'teacher',
            'status' => 'active',
            'sentat' => 0,
            'expiresat' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_metadata_declares_table_and_external_location(): void {
        $collection = new \core_privacy\local\metadata\collection('local_mcpconnector');
        $items = provider::get_metadata($collection)->get_collection();
        $this->assertNotEmpty($items);
        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains('local_mcpconnector_keys', $names);
        $this->assertContains('moodlemcp.com', $names);
    }

    public function test_contexts_for_userid_only_when_rows_exist(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($user->id));

        $this->make_key_row($user->id);
        $contexts = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contexts);
        $this->assertInstanceOf(\context_system::class, $contexts->current());
    }

    public function test_export_contains_key_metadata_never_values(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->make_key_row($user->id);

        $context = \context_system::instance();
        provider::export_user_data(new approved_contextlist($user, 'local_mcpconnector', [$context->id]));

        $data = writer::with_context($context)->get_data(
            [get_string('pluginname', 'local_mcpconnector')]
        );
        $this->assertNotEmpty($data->keys);
        $this->assertSame('wxyz', $data->keys[0]->keylast4);
        $this->assertSame('teacher', $data->keys[0]->roles);
        // Metadata only — no property could carry a key value or token.
        // property_exists() rather than assertObjectNotHasProperty(): the latter
        // arrived in PHPUnit 10, and Moodle 4.2 still ships PHPUnit 9.
        $this->assertFalse(property_exists($data->keys[0], 'token'));
        $this->assertFalse(property_exists($data->keys[0], 'key'));
    }

    public function test_delete_data_for_user_removes_only_their_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->make_key_row($user->id);
        $this->make_key_row($other->id);

        $context = \context_system::instance();
        provider::delete_data_for_user(
            new approved_contextlist($user, 'local_mcpconnector', [$context->id])
        );

        $this->assertSame(0, $DB->count_records('local_mcpconnector_keys', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_mcpconnector_keys', ['userid' => $other->id]));
    }

    public function test_delete_all_users_in_context_wipes_the_table(): void {
        global $DB;
        $this->resetAfterTest();
        $this->make_key_row($this->getDataGenerator()->create_user()->id);
        $this->make_key_row($this->getDataGenerator()->create_user()->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records('local_mcpconnector_keys'));
    }
}
