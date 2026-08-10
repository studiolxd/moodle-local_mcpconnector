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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mcpconnector/tests/fixtures/wakeup_probe.php');

/**
 * update_module's mod_page branch, which re-seeds the display options that
 * page_update_instance reads unconditionally.
 *
 * Those options live in page.displayoptions as a serialized array, and the
 * column is not trustworthy: mod_page's restore step inserts the record
 * verbatim (restore_page_stepslib.php), so a restored .mbz decides its bytes.
 * Reading it must never instantiate objects.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\external\update_module
 */
final class update_module_page_test extends \advanced_testcase {
    /**
     * Creates a page whose display options are stored as the given raw string.
     *
     * @param string $displayoptions Raw column value, written as-is.
     * @return \cm_info
     */
    private function page_with_raw_displayoptions(string $displayoptions): \cm_info {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Unit 1',
        ]);
        $DB->set_field('page', 'displayoptions', $displayoptions, ['id' => $page->id]);

        return get_fast_modinfo($course->id)->get_cm($page->cmid);
    }

    public function test_renames_a_page_and_leaves_its_display_options_readable(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $cm = $this->page_with_raw_displayoptions(serialize([
            'printintro' => 1,
            'printlastmodified' => 0,
            'popupwidth' => 800,
            'popupheight' => 600,
        ]));

        // An intro is what forces the full update_moduleinfo path; a name-only
        // call takes the set_coursemodule_name() fast path and never reads
        // displayoptions at all.
        $result = update_module::execute((int) $cm->id, 'Unit 1 (revised)', '<p>Revised.</p>', FORMAT_HTML);

        $this->assertTrue($result['success']);
        $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->assertSame('Unit 1 (revised)', $page->name);
        $opts = (array) unserialize_array($page->displayoptions);
        $this->assertSame(1, (int) $opts['printintro']);
        $this->assertSame(0, (int) $opts['printlastmodified']);
        // The popup sizes are not asserted: page_update_instance only writes
        // them back when display is RESOURCELIB_DISPLAY_POPUP, and this page
        // displays inline.
    }

    public function test_an_object_in_displayoptions_is_never_woken(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // What a malicious .mbz leaves behind, where core only ever writes an
        // array of scalars. Plain unserialize() builds the object and runs its
        // __wakeup() — the whole point of an object-injection gadget.
        wakeup_probe::$woken = false;
        $cm = $this->page_with_raw_displayoptions(serialize([
            'printintro' => new wakeup_probe(),
        ]));

        // An intro is what forces the full update_moduleinfo path; a name-only
        // call takes the set_coursemodule_name() fast path and never reads
        // displayoptions at all.
        $result = update_module::execute((int) $cm->id, 'Unit 1 (revised)', '<p>Revised.</p>', FORMAT_HTML);

        $this->assertTrue($result['success']);
        $this->assertFalse(
            wakeup_probe::$woken,
            'displayoptions was deserialized in a way that instantiates objects'
        );
    }
}
