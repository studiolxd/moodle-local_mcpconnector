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

use local_mcpconnector\external\backup_course;
use local_mcpconnector\external\restore_course;

/**
 * Backup → restore ROUND-TRIP through the plugin's own code paths (the
 * network legs — presigned PUT and HTTPS fetch — are covered by url_file and
 * excluded here on purpose): a course with content must come back as a new
 * course with the same content, and a corrupt archive must fail BEFORE any
 * course is created.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\backup_course
 * @covers     \local_mcpconnector\external\restore_course
 */
final class backup_restore_test extends \advanced_testcase {
    public function test_roundtrip_restores_content_into_a_new_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $source = $generator->create_course([
            'fullname' => 'FPE Original',
            'shortname' => 'fpe-orig',
        ]);
        $generator->create_module('page', [
            'course' => $source->id,
            'name' => 'Unidad 1 (contenido)',
            'content' => '<p>hola</p>',
        ]);
        $generator->create_module('forum', ['course' => $source->id, 'name' => 'Foro de dudas']);
        $category = $generator->create_category(['name' => 'Restauraciones']);

        [$mbzpath, $filename] = backup_course::create_mbz((int) $source->id, false);
        try {
            $this->assertFileExists($mbzpath);
            $this->assertGreaterThan(0, filesize($mbzpath));
            $this->assertStringEndsWith('.mbz', $filename);

            $newid = restore_course::restore_mbz($mbzpath, (int) $category->id, 'FPE Copia', 'fpe-copia');
        } finally {
            @unlink($mbzpath);
        }

        $new = $DB->get_record('course', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame('FPE Copia', $new->fullname);
        $this->assertSame('fpe-copia', $new->shortname);
        $this->assertEquals($category->id, $new->category);
        $this->assertNotEquals($source->id, $newid);

        // The content travelled: same module names in the new course.
        $modinfo = get_fast_modinfo($newid);
        $names = array_map(static fn($cm) => $cm->name, array_values($modinfo->get_cms()));
        $this->assertContains('Unidad 1 (contenido)', $names);
        $this->assertContains('Foro de dudas', $names);
    }

    public function test_default_names_come_from_the_backup(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $source = $generator->create_course(['fullname' => 'Nombre Original', 'shortname' => 'orig-sn']);
        $category = $generator->create_category();

        [$mbzpath] = backup_course::create_mbz((int) $source->id, false);
        try {
            $newid = restore_course::restore_mbz($mbzpath, (int) $category->id);
        } finally {
            @unlink($mbzpath);
        }

        $new = $DB->get_record('course', ['id' => $newid], 'fullname, shortname', MUST_EXIST);
        // The restore engine carries the backup's names (deduplicating the
        // shortname against the still-existing source course).
        $this->assertStringContainsString('Nombre Original', $new->fullname);
        $this->assertNotSame('orig-sn', $new->shortname);
    }

    public function test_corrupt_archive_fails_before_creating_any_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $before = $DB->count_records('course');

        $garbage = tempnam(make_temp_directory('mcpconnector'), 'bad');
        file_put_contents($garbage, 'this is not an mbz');
        try {
            $this->expectException(\moodle_exception::class);
            restore_course::restore_mbz($garbage, (int) $category->id);
        } finally {
            @unlink($garbage);
            // Unpacking the garbage makes core report 'Not a zip archive.' through
            // debugging() before we reject it — expected here, so assert it rather
            // than let it surface as an unexpected call.
            $this->assertDebuggingCalled();
            $this->assertSame($before, $DB->count_records('course'));
        }
    }

    public function test_backup_execute_rejects_non_https_uploadurl(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/https/');
        backup_course::execute((int) $course->id, 'http://insecure.example.com/put');
    }

    public function test_backup_requires_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        backup_course::execute((int) $course->id, 'https://transfer.example.com/put');
    }
}
