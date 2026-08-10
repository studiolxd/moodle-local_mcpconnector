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
 * Tests for local_mcpconnector_deprovision_resources() — the cleanup shared by
 * the uninstall hook and the manual "Deprovision" button on services.php. Must
 * wipe every service/token/authorization it provisioned and mark local key
 * metadata as revoked, and must never abort local cleanup just because the
 * panel call failed (no panel secret is configured in tests, so every panel
 * call fails loudly — that's the "panel unreachable" case for free).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_deprovision_resources
 * @covers     \xmldb_local_mcpconnector_uninstall
 */
final class deprovision_resources_test extends \advanced_testcase {
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
        $serviceid = (int) $DB->get_field(
            'external_services',
            'id',
            ['shortname' => 'mcpconnector_teacher'],
            MUST_EXIST
        );

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

    public function test_removes_every_provisioned_resource(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->resetDebugging(); // Discard any unrelated debugging() noise from create_user().
        $serviceid = $this->seed_teacher_key($user);

        // No panel_secret is configured, so the revoke-all call fails at the
        // signing step (that's fine — this test only cares about local cleanup).
        $result = local_mcpconnector_deprovision_resources('test');
        $this->assertDebuggingCalled();

        $shortnames = array_column(local_mcpconnector_get_service_definitions(), 'shortname');
        $this->assertSame(count($shortnames), $result['servicesremoved']);
        [$insql, $params] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $this->assertSame(0, $DB->count_records_select('external_services', "shortname {$insql}", $params));
        $this->assertSame(0, $DB->count_records('external_services_users', ['externalserviceid' => $serviceid]));
        $this->assertSame(0, $DB->count_records('external_tokens', ['externalserviceid' => $serviceid]));
        $this->assertSame(0, $DB->count_records('local_mcpconnector_keys', [
            'userid' => $user->id,
            'status' => 'active',
        ]));
        $this->assertSame(1, $DB->count_records('local_mcpconnector_keys', [
            'userid' => $user->id,
            'status' => 'revoked',
        ]));
    }

    public function test_unreachable_panel_does_not_block_local_cleanup(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->resetDebugging(); // Discard any unrelated debugging() noise from create_user().
        $serviceid = $this->seed_teacher_key($user);

        // No panel_secret is configured, so the revoke-all call fails at the
        // signing step, before any HTTP request is attempted.
        $result = local_mcpconnector_deprovision_resources('test');
        $this->assertDebuggingCalled();

        $this->assertFalse($result['panel']['ok']);
        $this->assertSame('missing_panel_secret', $result['panel']['error']);
        $this->assertSame(0, $DB->count_records('external_services', ['id' => $serviceid]));
    }

    public function test_uninstall_hook_delegates_to_shared_cleanup(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/uninstall.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->resetDebugging(); // Discard any unrelated debugging() noise from create_user().
        $serviceid = $this->seed_teacher_key($user);
        set_config('license_key', 'test-license', 'local_mcpconnector');

        $this->assertTrue(xmldb_local_mcpconnector_uninstall());
        $this->assertDebuggingCalled();

        $this->assertSame(0, $DB->count_records('external_services', ['id' => $serviceid]));
        $this->assertSame('', (string) get_config('local_mcpconnector', 'license_key'));
    }
}
