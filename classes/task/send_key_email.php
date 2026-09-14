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
 * Ad-hoc task that emails a freshly minted MCP key to its user.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\task;

use core\task\adhoc_task;

/**
 * Sends the key email outside the request that provisioned the user.
 *
 * Provisioning used to mint the key, call the panel AND wait for the SMTP
 * server before redirecting, which on a slow relay looks exactly like a hung
 * page. The mail now rides here instead.
 *
 * The key VALUE is only ever known in the panel's create response, so it has
 * to travel with the task: it is stored ENCRYPTED with the site key
 * (\core\encryption, Sodium) and only decrypted here, seconds or minutes
 * later, to be sent. A failed send throws so Moodle retries — the value is
 * unrecoverable from anywhere else.
 */
class send_key_email extends adhoc_task {
    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        $data = $this->get_custom_data();
        $userid = (int) ($data->userid ?? 0);
        $panelkeyid = (string) ($data->panelkeyid ?? '');
        $mcpurl = (string) ($data->mcpurl ?? '');
        $encrypted = (string) ($data->mcpkeyenc ?? '');

        if ($userid <= 0 || $encrypted === '') {
            mtrace('local_mcpconnector: key email task has nothing to send, skipping.');
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
        if (!$user) {
            mtrace('local_mcpconnector: user ' . $userid . ' is gone, the key email is moot.');
            return;
        }

        try {
            $mcpkey = \core\encryption::decrypt($encrypted);
        } catch (\Throwable $e) {
            // Retrying cannot fix a key this site can no longer decrypt (the
            // site key changed, or the data is corrupt): fail for good and
            // leave sentat null so an admin can regenerate from the Keys tab.
            mtrace('local_mcpconnector: could not decrypt the key for user ' . $userid
                . ' — regenerate it from the Keys tab. (' . $e->getMessage() . ')');
            return;
        }

        if (!local_mcpconnector_send_key_email($user, $mcpkey, $mcpurl)) {
            // Throw so Moodle retries with backoff: this task holds the only
            // copy of the key value that will ever exist.
            throw new \moodle_exception('taskfailed', 'local_mcpconnector', '', null, 'email_failed');
        }

        if ($panelkeyid !== '') {
            local_mcpconnector_mark_local_key_sent($panelkeyid);
        }

        mtrace('local_mcpconnector: MCP key emailed to user ' . $userid . '.');
    }
}
