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

use local_mcpconnector\external\update_grade_item;

/**
 * External function test (template for the rest of the plugin's externals):
 * update_grade_item on manual items and its guard rails.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\update_grade_item
 */
final class external_update_grade_item_test extends \advanced_testcase {
    /**
     * Creates a course with a manual grade item.
     *
     * @return array{course:\stdClass,item:\grade_item}
     */
    private function make_manual_item(): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $course = $this->getDataGenerator()->create_course();
        $item = new \grade_item([
            'courseid' => $course->id,
            'itemtype' => 'manual',
            'itemname' => 'Trabajo final',
            'grademax' => 100,
        ], false);
        $item->insert();
        return ['course' => $course, 'item' => $item];
    }

    public function test_sets_grademax_and_gradepass_on_a_manual_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        ['course' => $course, 'item' => $item] = $this->make_manual_item();

        $result = update_grade_item::execute((int) $course->id, (int) $item->id, 10.0, 5.0);
        $result = update_grade_item::clean_returnvalue(update_grade_item::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['warning']);
        $fresh = \grade_item::fetch(['id' => $item->id]);
        $this->assertEqualsWithDelta(10.0, (float) $fresh->grademax, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $fresh->gradepass, 0.001);
    }

    public function test_warns_when_gradepass_ends_above_grademax(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        ['course' => $course, 'item' => $item] = $this->make_manual_item();

        update_grade_item::execute((int) $course->id, (int) $item->id, null, 50.0);
        $result = update_grade_item::execute((int) $course->id, (int) $item->id, 10.0);
        $result = update_grade_item::clean_returnvalue(update_grade_item::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('gradepass', $result['warning']);
    }

    public function test_rejects_nonpositive_grademax(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        ['course' => $course, 'item' => $item] = $this->make_manual_item();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/greater than 0/');
        update_grade_item::execute((int) $course->id, (int) $item->id, 0.0);
    }

    public function test_rejects_grademax_on_non_quiz_module_items(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $item = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
        ]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/quiz module items only/');
        update_grade_item::execute((int) $course->id, (int) $item->id, 10.0);
    }

    public function test_requires_grade_manage_capability(): void {
        $this->resetAfterTest();
        ['course' => $course, 'item' => $item] = $this->make_manual_item();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        update_grade_item::execute((int) $course->id, (int) $item->id, 10.0);
    }
}
