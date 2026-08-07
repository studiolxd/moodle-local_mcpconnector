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

/**
 * Guard rejections whose detail stays VISIBLE to API callers.
 *
 * invalid_parameter_exception($detail) buries the detail in debuginfo, which
 * Moodle only sends with debugging on — production callers just see the
 * generic "Invalid parameter value detected". Guards whose message matters
 * to the caller (e.g. "grade category 23 not found in course 21") must put
 * it in the exception MESSAGE, which is what this throws.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reject {
    /**
     * Throws a moodle_exception carrying $detail as the visible message.
     *
     * @param string $detail What was wrong, caller-facing.
     * @return never
     */
    public static function because(string $detail): void {
        throw new \moodle_exception('errordetail', 'local_mcpconnector', '', $detail);
    }
}
