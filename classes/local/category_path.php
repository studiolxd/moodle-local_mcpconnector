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

namespace local_mcpconnector\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/format.php');

/**
 * Exposes qformat_default's protected create_category_path() so webservices
 * can resolve/create a nested category path ("top/Padre/Hijo", '/' separator,
 * '//' escapes a literal slash) exactly like a $CATEGORY: line in a GIFT file
 * would. The path is always created in the TARGET context (create_category_path
 * uses $this->category->contextid when contextfromfile is off), so per-course
 * isolation holds.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class category_path extends \qformat_default {
    /**
     * Finds or creates the nested category path in the target context.
     *
     * @param \stdClass $course The course (qformat needs it for legacy-context re-targeting).
     * @param \core_question\local\bank\question_edit_contexts $contexts Target edit contexts.
     * @param \stdClass $defaultcategory The context's default category (anchors the context).
     * @param string $path Category path, e.g. "top/INAD0011/Módulo 1/UD 2".
     * @return \stdClass The question_categories row at the end of the path.
     */
    public static function ensure(
        \stdClass $course,
        \core_question\local\bank\question_edit_contexts $contexts,
        \stdClass $defaultcategory,
        string $path
    ): \stdClass {
        $format = new self();
        $format->setCourse($course);
        $format->setContexts($contexts->having_one_edit_tab_cap('import'));
        $format->setCategory($defaultcategory);
        $format->setContextfromfile(false);
        $category = $format->create_category_path($path);
        if (empty($category)) {
            throw new \moodle_exception('cannotcreatecategory', 'question', '', null, $path);
        }
        return $category;
    }
}
