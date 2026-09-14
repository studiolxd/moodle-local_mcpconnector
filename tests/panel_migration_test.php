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
 * Tests for the panel-change detection that drives the key migration notice.
 *
 * A key only works on the panel that minted it, so re-pairing the site with
 * another panel (or another license) silently orphans every key issued so far.
 * These tests cover the detection, the selection of the orphaned keys and the
 * bulk regeneration's tolerance of per-user failures. The panel itself is
 * never reachable in tests (no panel secret is configured), which is exactly
 * how the failure paths below are exercised.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_panel_fingerprint
 * @covers     \local_mcpconnector_note_panel_fingerprint
 * @covers     \local_mcpconnector_count_foreign_keys
 * @covers     \local_mcpconnector_get_foreign_key_userids
 * @covers     \local_mcpconnector_key_is_foreign
 * @covers     \local_mcpconnector_regenerate_keys_for_users
 */
final class panel_migration_test extends \advanced_testcase {
    /**
     * Points the plugin at a panel and marks the license validated.
     *
     * @param string $panelurl
     * @param string $license
     * @return string The resulting panel fingerprint.
     */
    private function pair_with_panel(string $panelurl, string $license = 'lic-1'): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        set_config('panel_url', $panelurl, 'local_mcpconnector');
        set_config('license_key', $license, 'local_mcpconnector');
        set_config('license_status', 'ok', 'local_mcpconnector');

        return local_mcpconnector_panel_fingerprint();
    }

    /**
     * Inserts a local key row for a user, as if minted by the given panel.
     *
     * @param int $userid
     * @param string $fingerprint
     * @param string $status
     * @return int Row id.
     */
    private function add_key(int $userid, string $fingerprint, string $status = 'active'): int {
        global $DB;

        return (int) $DB->insert_record('local_mcpconnector_keys', (object) [
            'userid' => $userid,
            'panelkeyid' => 'key-' . $userid . '-' . substr(md5($fingerprint . $status), 0, 8),
            'keylast4' => 'abcd',
            'roles' => 'teacher',
            'status' => $status,
            'panelfingerprint' => $fingerprint,
            'sentat' => null,
            'expiresat' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_fingerprint_identifies_the_panel_and_license_pair(): void {
        $this->resetAfterTest();

        $first = $this->pair_with_panel('https://panel-a.example.com');
        $this->assertSame(64, strlen($first));

        // A trailing slash is the same panel.
        $this->assertSame($first, $this->pair_with_panel('https://panel-a.example.com/'));

        // Another panel, or another license on the same panel, is not.
        $this->assertNotSame($first, $this->pair_with_panel('https://panel-b.example.com'));
        $this->assertNotSame($first, $this->pair_with_panel('https://panel-a.example.com', 'lic-2'));

        // Unconfigured means unknown, never a bogus digest.
        set_config('license_key', '', 'local_mcpconnector');
        $this->assertSame('', local_mcpconnector_panel_fingerprint());
    }

    public function test_panel_change_is_flagged_when_keys_are_live(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $old = $this->pair_with_panel('https://panel-a.example.com');
        // First validation ever: nothing to compare against, nothing to flag.
        $this->assertFalse(local_mcpconnector_note_panel_fingerprint());
        $this->add_key((int) $user->id, $old);

        $this->pair_with_panel('https://panel-b.example.com');
        $this->assertTrue(local_mcpconnector_note_panel_fingerprint());
        $this->assertNotEmpty(get_config('local_mcpconnector', 'panel_changed_at'));

        // Re-validating against the same panel is not another change.
        $this->assertFalse(local_mcpconnector_note_panel_fingerprint());
    }

    public function test_panel_change_without_live_keys_is_not_flagged(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $old = $this->pair_with_panel('https://panel-a.example.com');
        local_mcpconnector_note_panel_fingerprint();
        // A revoked key is dead on every panel: nothing to migrate.
        $this->add_key((int) $user->id, $old, 'revoked');

        $this->pair_with_panel('https://panel-b.example.com');
        $this->assertFalse(local_mcpconnector_note_panel_fingerprint());
        $this->assertEmpty(get_config('local_mcpconnector', 'panel_changed_at'));
    }

    public function test_foreign_keys_are_the_ones_from_a_previous_panel(): void {
        global $DB;
        $this->resetAfterTest();
        $olduser = $this->getDataGenerator()->create_user();
        $newuser = $this->getDataGenerator()->create_user();
        $revokeduser = $this->getDataGenerator()->create_user();

        $old = $this->pair_with_panel('https://panel-a.example.com');
        $new = $this->pair_with_panel('https://panel-b.example.com');

        $this->add_key((int) $olduser->id, $old);
        $this->add_key((int) $newuser->id, $new);
        $this->add_key((int) $revokeduser->id, $old, 'revoked');
        // A pre-1.2.0 row the upgrade could not attribute to this panel.
        $legacyid = $this->add_key((int) $olduser->id, $new, 'suspended');
        $DB->set_field('local_mcpconnector_keys', 'panelfingerprint', null, ['id' => $legacyid]);

        $this->assertSame(2, local_mcpconnector_count_foreign_keys());
        $this->assertSame([(int) $olduser->id], local_mcpconnector_get_foreign_key_userids());

        $foreign = $DB->get_record('local_mcpconnector_keys', ['id' => $legacyid]);
        $this->assertTrue(local_mcpconnector_key_is_foreign($foreign));
        $current = $DB->get_record('local_mcpconnector_keys', ['userid' => $newuser->id]);
        $this->assertFalse(local_mcpconnector_key_is_foreign($current));
    }

    public function test_bulk_regeneration_survives_per_user_failures(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();

        $old = $this->pair_with_panel('https://panel-a.example.com');
        // Validated against panel A first: without that the move below is a
        // first pairing, not a change, and nothing would be flagged.
        local_mcpconnector_note_panel_fingerprint();
        $this->add_key((int) $first->id, $old);
        $this->add_key((int) $second->id, $old);
        $this->pair_with_panel('https://panel-b.example.com');
        $this->assertTrue(local_mcpconnector_note_panel_fingerprint());

        // No panel secret is configured, so every panel call fails: the point
        // is that the loop reports both failures instead of dying on the first.
        $summary = local_mcpconnector_regenerate_keys_for_users(
            local_mcpconnector_get_foreign_key_userids()
        );

        $this->assertSame(0, $summary['done']);
        $this->assertSame(2, $summary['failed']);
        $this->assertNotEmpty($summary['errors']);
        // Each failed panel call reports itself to the developer log.
        $this->resetDebugging();
        // Still broken, so the warning must stay up.
        $this->assertNotEmpty(get_config('local_mcpconnector', 'panel_changed_at'));
    }

    public function test_warning_clears_once_no_key_is_foreign(): void {
        $this->resetAfterTest();

        $this->pair_with_panel('https://panel-a.example.com');
        set_config('panel_changed_at', time(), 'local_mcpconnector');

        // Nothing left to migrate: the flag is housekeeping, not a monument.
        local_mcpconnector_regenerate_keys_for_users([]);

        $this->assertEmpty(get_config('local_mcpconnector', 'panel_changed_at'));
    }

    public function test_deleted_user_cannot_be_regenerated(): void {
        $this->resetAfterTest();
        $this->pair_with_panel('https://panel-a.example.com');

        $result = local_mcpconnector_regenerate_user_key(-1);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_user', $result['error']);
    }
}
