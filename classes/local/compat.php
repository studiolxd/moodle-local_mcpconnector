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
 * Version guards for external functions that depend on newer Moodle APIs.
 *
 * The plugin supports Moodle 4.2+, but the question-bank subsystem uses
 * APIs that only exist from 5.0 (question banks as mod_qbank modules,
 * question_bank_helper) or 4.5 (core_question\category_manager). Without
 * these guards a 4.x site gets a PHP class-not-found FATAL; with them the
 * caller gets a clear, visible message (via reject::because).
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compat {
    /**
     * Question-bank tooling (import_questions, list_question_banks,
     * add_random_questions): requires the Moodle 5.0 qbank model.
     *
     * @param string $tool Caller-facing tool name for the error message.
     */
    public static function require_question_bank(string $tool): void {
        if (!class_exists('\core_question\local\bank\question_bank_helper')) {
            reject::because(
                "{$tool} requires Moodle 5.0 or later (question banks are course "
                . 'modules from 5.0; this site runs an older release)'
            );
        }
    }

    /**
     * Grade-category deletion path: \core_question\category_manager is 4.5+.
     *
     * @param string $tool Caller-facing tool name for the error message.
     */
    public static function require_category_manager(string $tool): void {
        if (!class_exists('\core_question\category_manager')) {
            reject::because("{$tool} requires Moodle 4.5 or later (this site runs an older release)");
        }
    }
}
