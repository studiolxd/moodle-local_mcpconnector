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

use local_mcpconnector\external\create_book;

/**
 * Authoring tools (2.22): a book must come out with its chapters ordered and
 * nested as requested, and the guards must hold.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\create_book
 */
final class authoring_test extends \advanced_testcase {
    public function test_create_book_with_nested_chapters(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $result = create_book::execute((int) $course->id, 0, 'Manual FPE', [
            ['title' => 'Introducción', 'content' => '<p>Bienvenida.</p>'],
            ['title' => 'Detalle', 'content' => '<p>Cuerpo.</p>', 'subchapter' => 1],
            ['title' => 'Cierre', 'content' => '<p>Fin.</p>'],
        ]);
        $result = create_book::clean_returnvalue(create_book::execute_returns(), $result);

        $this->assertCount(3, $result['chapterids']);
        $rows = array_values($DB->get_records(
            'book_chapters',
            ['bookid' => $result['instanceid']],
            'pagenum ASC'
        ));
        $this->assertSame(
            ['Introducción', 'Detalle', 'Cierre'],
            array_map(static fn($r) => $r->title, $rows)
        );
        $this->assertSame([0, 1, 0], array_map(static fn($r) => (int) $r->subchapter, $rows));
        $this->assertSame([1, 2, 3], array_map(static fn($r) => (int) $r->pagenum, $rows));

        // The module is on the course page.
        $modinfo = get_fast_modinfo($course->id);
        $cm = $modinfo->get_cm($result['cmid']);
        $this->assertSame('book', $cm->modname);
        $this->assertSame('Manual FPE', $cm->name);
    }

    public function test_create_book_rejects_bad_shapes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        try {
            create_book::execute((int) $course->id, 0, 'Vacío', []);
            $this->fail('empty chapters accepted');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('at least one chapter', $e->getMessage());
        }

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/first chapter/');
        create_book::execute((int) $course->id, 0, 'Mal anidado', [
            ['title' => 'Sub', 'content' => '<p>x</p>', 'subchapter' => 1],
        ]);
    }

    public function test_create_book_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        create_book::execute((int) $course->id, 0, 'No', [
            ['title' => 'x', 'content' => '<p>x</p>'],
        ]);
    }
}
