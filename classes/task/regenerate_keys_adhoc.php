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

/**
 * Ad-hoc task that regenerates MCP keys in bulk after a panel change.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\task;

use core\task\adhoc_task;

/**
 * Regenerates (and re-emails) the keys of a list of users.
 *
 * Used when a site is re-paired with another panel and more users than
 * LOCAL_MCPCONNECTOR_REGENERATE_SYNC_MAX hold keys the new panel never minted:
 * two panel round trips plus an email per user is far too much for a request.
 *
 * Per-user failures are logged, never thrown: a retry would re-mint and
 * re-email a key for everyone who already succeeded.
 */
class regenerate_keys_adhoc extends adhoc_task {
    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        if (!local_mcpconnector_license_is_valid()) {
            // Transient by nature (the panel may be down): let Moodle retry.
            throw new \moodle_exception('taskfailed', 'local_mcpconnector', '', null, 'invalid_license');
        }

        $data = $this->get_custom_data();
        $userids = [];
        if (isset($data->userids) && is_array($data->userids)) {
            $userids = array_map('intval', $data->userids);
        }
        if (empty($userids)) {
            // Nothing was passed, or the task was queued before the ids were
            // resolved: fall back to whatever still belongs to another panel.
            $userids = local_mcpconnector_get_foreign_key_userids();
        }

        $summary = local_mcpconnector_regenerate_keys_for_users($userids);

        mtrace('local_mcpconnector: regenerated ' . $summary['done'] . ' key(s), '
            . $summary['failed'] . ' failed'
            . (!empty($summary['errors']) ? ' (' . implode(', ', $summary['errors']) . ')' : ''));
    }
}
