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
 * Reconciliation tests for local_mcpconnector_recalculate_user_key — the
 * paths that must never touch the panel: an unchanged live key is kept as-is
 * (its value can't be re-read, churning it would cut the user off) and a user
 * with no service assignments is cleaned up locally.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_recalculate_user_key
 */
final class recalculate_user_key_test extends \advanced_testcase {
    /**
     * Seeds the plugin services and assigns the user to the teacher one,
     * with a live token and a matching local key row.
     *
     * @param \stdClass $user
     * @return int Teacher service id.
     */
    private function seed_teacher_key(\stdClass $user): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');

        local_mcpconnector_ensure_services();
        $serviceid = (int) $DB->get_field('external_services', 'id',
            ['shortname' => 'mcpconnector_teacher'], MUST_EXIST);

        $DB->insert_record('external_services_users', (object) [
            'externalserviceid' => $serviceid,
            'userid' => $user->id,
            'timecreated' => time(),
        ]);
        local_mcpconnector_rotate_user_token($user->id, $serviceid, 0);
        $DB->insert_record('local_mcpconnector_keys', (object) [
            'userid' => $user->id,
            'panelkeyid' => '6f9619ff-8b86-4d01-b42d-' . str_pad(dechex($user->id), 12, '0', STR_PAD_LEFT),
            'keylast4' => 'wxyz',
            'roles' => 'teacher',
            'status' => 'active',
            'sentat' => 0,
            'expiresat' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return $serviceid;
    }

    public function test_unchanged_live_key_is_kept_without_panel_calls(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->seed_teacher_key($user);

        $result = local_mcpconnector_recalculate_user_key((int) $user->id);

        // ok with no error = the fast path: nothing changed, key preserved.
        // (Any panel path would fail loudly here — no panel secret configured.)
        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);
        $this->assertSame(1, $DB->count_records('local_mcpconnector_keys', [
            'userid' => $user->id,
            'status' => 'active',
        ]));
    }

    public function test_user_without_assignments_is_cleaned_up(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $serviceid = $this->seed_teacher_key($user);

        // Remove the service assignment and the local key row (already
        // revoked upstream); only the token remains to be cleaned.
        $DB->delete_records('external_services_users',
            ['userid' => $user->id, 'externalserviceid' => $serviceid]);
        $DB->delete_records('local_mcpconnector_keys', ['userid' => $user->id]);

        $result = local_mcpconnector_recalculate_user_key((int) $user->id);

        $this->assertTrue($result['ok']);
        $this->assertSame('key_deleted', $result['error']);
        $this->assertSame(0, $DB->count_records('external_tokens', [
            'userid' => $user->id,
            'externalserviceid' => $serviceid,
        ]));
    }

    public function test_unprovisioned_user_resolves_to_cleanup(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // The plugin's install seeds the services, so a user who was never
        // provisioned lands on the no-assignments cleanup path — a no-op
        // "key_deleted", never an error.
        $result = local_mcpconnector_recalculate_user_key((int) $user->id);
        $this->assertTrue($result['ok']);
        $this->assertSame('key_deleted', $result['error']);
    }
}
