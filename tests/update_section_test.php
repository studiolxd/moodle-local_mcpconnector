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

use local_mcpconnector\external\update_section;

/**
 * update_section (2.23): the only one of a course's three text surfaces
 * (label body, course summary, section summary) with no prior webservice.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\update_section
 */
final class update_section_test extends \advanced_testcase {
    public function test_updates_name_summary_and_visible(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);

        $result = update_section::execute(
            (int) $course->id,
            1,
            'Unidad 1',
            '<p>En esta unidad veremos los fundamentos.</p>',
            FORMAT_HTML,
            0
        );

        $this->assertTrue($result['success']);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $this->assertSame('Unidad 1', $section->name);
        $this->assertStringContainsString('fundamentos', $section->summary);
        $this->assertSame(0, (int) $section->visible);
    }

    public function test_hiding_a_section_cascades_to_its_modules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);

        update_section::execute((int) $course->id, 1, null, null, null, 0);

        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($page->cmid);
        $this->assertSame(0, (int) $cm->visible);
    }

    public function test_only_touches_fields_passed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        update_section::execute((int) $course->id, 1, 'Original', '<p>Original.</p>');

        update_section::execute((int) $course->id, 1, 'Renombrada');

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $this->assertSame('Renombrada', $section->name);
        $this->assertStringContainsString('Original', $section->summary);
    }

    public function test_rejects_when_nothing_to_update(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/nothing to update/');
        update_section::execute((int) $course->id, 1);
    }

    public function test_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        update_section::execute((int) $course->id, 1, 'No debería poder');
    }

    public function test_unknown_section_number_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);

        $this->expectException(\dml_missing_record_exception::class);
        update_section::execute((int) $course->id, 99, 'x');
    }
}
