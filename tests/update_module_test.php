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

use local_mcpconnector\external\update_module;

/**
 * update_module: the guards, the two update paths, and the per-module seeding.
 *
 * The mod_page branch has its own file (update_module_page_test).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\update_module
 */
final class update_module_test extends \advanced_testcase {
    /**
     * Creates a course with completion enabled and one module in it.
     *
     * @param string $modname Activity module to create.
     * @param array $options Extra generator options for the module.
     * @return array{0: \stdClass, 1: \stdClass} The course and the module record.
     */
    private function course_with(string $modname, array $options = []): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $module = $this->getDataGenerator()->create_module(
            $modname,
            ['course' => $course->id] + $options
        );

        return [$course, $module];
    }

    public function test_the_fast_path_renames_and_hides_without_touching_the_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum');

        // Name and visibility alone deliberately skip update_moduleinfo.
        $result = update_module::execute(cmid: (int) $forum->cmid, name: 'Q&A forum', visible: 0);

        $this->assertTrue($result['success']);
        $this->assertSame('Q&A forum', $DB->get_field('forum', 'name', ['id' => $forum->id]));
        $this->assertSame(0, (int) $DB->get_field('course_modules', 'visible', ['id' => $forum->cmid]));
    }

    public function test_the_full_path_updates_intro_and_idnumber(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum');

        $result = update_module::execute(
            cmid: (int) $forum->cmid,
            intro: '<p>Ask anything here.</p>',
            introformat: FORMAT_HTML,
            idnumber: 'FORUM-01'
        );

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Ask anything', $DB->get_field('forum', 'intro', ['id' => $forum->id]));
        $this->assertSame('FORUM-01', $DB->get_field('course_modules', 'idnumber', ['id' => $forum->cmid]));
    }

    public function test_availability_is_stored_and_can_be_removed(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableavailability = 1;
        [, $forum] = $this->course_with('forum');
        $tree = '{"op":"&","c":[{"type":"date","d":">=","t":1893456000}],"showc":[true]}';

        update_module::execute(cmid: (int) $forum->cmid, availability: $tree);
        $stored = $DB->get_field('course_modules', 'availability', ['id' => $forum->cmid]);
        $this->assertStringContainsString('"type":"date"', $stored);

        // The empty string is the documented way to clear every restriction.
        update_module::execute(cmid: (int) $forum->cmid, availability: '');
        $this->assertNull($DB->get_field('course_modules', 'availability', ['id' => $forum->cmid]));
    }

    public function test_the_json_literal_null_also_removes_availability(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableavailability = 1;
        [, $forum] = $this->course_with('forum');
        update_module::execute(
            cmid: (int) $forum->cmid,
            availability: '{"op":"&","c":[{"type":"date","d":">=","t":1893456000}],"showc":[true]}'
        );

        update_module::execute(cmid: (int) $forum->cmid, availability: 'null');

        $this->assertNull($DB->get_field('course_modules', 'availability', ['id' => $forum->cmid]));
    }

    public function test_a_malformed_availability_tree_is_rejected_before_it_is_stored(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableavailability = 1;
        [, $forum] = $this->course_with('forum');

        try {
            // Valid JSON, but not an availability tree — core would only fail
            // later with an opaque coding_exception.
            update_module::execute(cmid: (int) $forum->cmid, availability: '{"nonsense":true}');
            $this->fail('expected the invalid tree to be rejected');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('availability', $e->getMessage());
        }
        $this->assertNull($DB->get_field('course_modules', 'availability', ['id' => $forum->cmid]));
    }

    public function test_completion_rules_need_completion_enabled_on_the_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/completion tracking disabled/');
        update_module::execute(cmid: (int) $forum->cmid, completionview: 1);
    }

    public function test_enabling_the_view_rule_switches_completion_to_automatic(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum', ['completion' => COMPLETION_TRACKING_MANUAL]);

        update_module::execute(cmid: (int) $forum->cmid, completionview: 1);

        $cm = $DB->get_record('course_modules', ['id' => $forum->cmid], '*', MUST_EXIST);
        $this->assertSame(COMPLETION_TRACKING_AUTOMATIC, (int) $cm->completion);
        $this->assertSame(1, (int) $cm->completionview);
    }

    public function test_turning_a_rule_off_does_not_flip_the_module_to_automatic(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum', ['completion' => COMPLETION_TRACKING_MANUAL]);

        update_module::execute(cmid: (int) $forum->cmid, completionview: 0);

        $this->assertSame(
            COMPLETION_TRACKING_MANUAL,
            (int) $DB->get_field('course_modules', 'completion', ['id' => $forum->cmid])
        );
    }

    public function test_content_is_refused_for_modules_other_than_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/only supported for mod_page/');
        update_module::execute(cmid: (int) $forum->cmid, content: '<p>body</p>');
    }

    public function test_scorm_only_rules_are_refused_on_other_modules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/only for mod_scorm/');
        update_module::execute(cmid: (int) $forum->cmid, completionscormstatus: 'passed');
    }

    public function test_quiz_only_rules_are_refused_on_other_modules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $forum] = $this->course_with('forum');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/only for mod_quiz/');
        update_module::execute(cmid: (int) $forum->cmid, completionminattempts: 2);
    }

    public function test_an_unknown_scorm_status_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $scorm] = $this->course_with('scorm');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches("/must be 'passed', 'completed'/");
        update_module::execute(cmid: (int) $scorm->cmid, completionscormstatus: 'finished');
    }

    public function test_updating_a_quiz_preserves_its_password_and_feedback(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $quiz] = $this->course_with('quiz', ['quizpassword' => 's3cret']);
        $DB->insert_record('quiz_feedback', (object) [
            'quizid' => $quiz->id,
            'feedbacktext' => 'Well done',
            'feedbacktextformat' => FORMAT_HTML,
            'mingrade' => 0,
            'maxgrade' => 10,
        ]);

        // A completion-only change must not disturb unrelated quiz settings:
        // quiz_update_instance rebuilds them from fields the mod_form seeds, so
        // update_module has to re-seed them from the stored record.
        update_module::execute(cmid: (int) $quiz->cmid, completionview: 1);

        $this->assertSame('s3cret', $DB->get_field('quiz', 'password', ['id' => $quiz->id]));
        $feedback = $DB->get_records('quiz_feedback', ['quizid' => $quiz->id]);
        $this->assertCount(1, $feedback);
        $this->assertStringContainsString('Well done', reset($feedback)->feedbacktext);
    }

    public function test_updating_a_quiz_preserves_its_review_options(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $quiz] = $this->course_with('quiz');
        $before = (int) $DB->get_field('quiz', 'reviewmarks', ['id' => $quiz->id]);

        update_module::execute(cmid: (int) $quiz->cmid, completionview: 1);

        // Absent review checkboxes would zero the bitmask outright.
        $this->assertSame($before, (int) $DB->get_field('quiz', 'reviewmarks', ['id' => $quiz->id]));
    }

    public function test_a_scorm_status_rule_lands_as_its_bitmask(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $scorm] = $this->course_with('scorm');

        update_module::execute(cmid: (int) $scorm->cmid, completionscormstatus: 'passedorcompleted');

        // 2 (passed) | 4 (completed) — the values of scorm_status_options().
        $this->assertSame(6, (int) $DB->get_field('scorm', 'completionstatusrequired', ['id' => $scorm->id]));
    }

    public function test_a_student_cannot_update_a_module(): void {
        $this->resetAfterTest();
        [$course, $forum] = $this->course_with('forum');
        // Enrolled, so validate_context() lets them through and the capability
        // check is what actually refuses them.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        update_module::execute(cmid: (int) $forum->cmid, name: 'Renamed');
    }
}
