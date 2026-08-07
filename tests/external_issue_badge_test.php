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

use local_mcpconnector\external\issue_badge;

/**
 * Tests for issue_badge — awarding must go through the core path (row in
 * badge_issued, criteria locking) and be idempotent and guarded.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\issue_badge
 */
final class external_issue_badge_test extends \advanced_testcase {
    /**
     * Creates an ACTIVE site badge.
     *
     * @return int Badge id.
     */
    private function make_active_badge(): int {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/badgeslib.php');
        $CFG->enablebadges = 1;

        $now = time();
        $badgeid = $DB->insert_record('badge', (object) [
            'name' => 'Curso FPE superado',
            'description' => 'repro',
            'timecreated' => $now,
            'timemodified' => $now,
            'usercreated' => $USER->id,
            'usermodified' => $USER->id,
            'issuername' => 'Studio LXD',
            'issuerurl' => 'https://learn.example.test',
            'issuercontact' => 'hello@example.test',
            'expiredate' => null,
            'expireperiod' => null,
            'type' => BADGE_TYPE_SITE,
            'courseid' => null,
            'message' => 'You won!',
            'messagesubject' => 'Badge',
            'attachment' => 1,
            'notification' => 0,
            'status' => BADGE_STATUS_ACTIVE,
            'version' => '1',
            'language' => 'en',
            'imageauthorname' => '',
            'imageauthoremail' => '',
            'imageauthorurl' => '',
            'imagecaption' => '',
        ]);
        return (int) $badgeid;
    }

    public function test_awards_an_active_badge_and_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $badgeid = $this->make_active_badge();
        $user = $this->getDataGenerator()->create_user();

        $result = issue_badge::execute($badgeid, (int) $user->id);
        $result = issue_badge::clean_returnvalue(issue_badge::execute_returns(), $result);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['alreadyissued']);
        $this->assertTrue($DB->record_exists('badge_issued', [
            'badgeid' => $badgeid,
            'userid' => $user->id,
        ]));

        // Second award: no duplicate row, flagged as already issued.
        $again = issue_badge::execute($badgeid, (int) $user->id);
        $again = issue_badge::clean_returnvalue(issue_badge::execute_returns(), $again);
        $this->assertTrue($again['success']);
        $this->assertTrue($again['alreadyissued']);
        $this->assertSame(1, $DB->count_records('badge_issued', [
            'badgeid' => $badgeid,
            'userid' => $user->id,
        ]));
    }

    public function test_rejects_an_inactive_badge(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $badgeid = $this->make_active_badge();
        $DB->set_field('badge', 'status', BADGE_STATUS_INACTIVE, ['id' => $badgeid]);
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/not active/');
        issue_badge::execute($badgeid, (int) $user->id);
    }

    public function test_rejects_unknown_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $badgeid = $this->make_active_badge();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/user .* not found/');
        issue_badge::execute($badgeid, 999999);
    }

    public function test_requires_award_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $badgeid = $this->make_active_badge();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        issue_badge::execute($badgeid, (int) $student->id);
    }
}
