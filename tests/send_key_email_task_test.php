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

/**
 * Tests for the background delivery of a freshly minted MCP key.
 *
 * Provisioning a user used to wait for the SMTP server before redirecting.
 * The mail now rides an ad-hoc task, and because the key value exists only in
 * the panel's create response it has to travel with it — encrypted, never in
 * the clear, which is what these tests pin down.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector\task\send_key_email
 * @covers     \local_mcpconnector_queue_key_email
 */
final class send_key_email_task_test extends \advanced_testcase {
    /** @var string A key value shaped like the panel's. */
    private const KEY = 'mcpk_0123456789abcdef0123456789abcdef';

    /**
     * Seeds the email templates and a local key row for the user.
     *
     * @param \stdClass $user
     * @return string The panel key id of the row.
     */
    private function seed_key(\stdClass $user): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        set_config('email_subject', get_string('email_subject_default', 'local_mcpconnector'), 'local_mcpconnector');
        set_config('email_body', get_string('email_body_default', 'local_mcpconnector'), 'local_mcpconnector');

        $panelkeyid = '11111111-2222-3333-4444-555555555555';
        $DB->insert_record('local_mcpconnector_keys', (object) [
            'userid' => $user->id,
            'panelkeyid' => $panelkeyid,
            'keylast4' => 'cdef',
            'roles' => 'teacher',
            'status' => 'active',
            'panelfingerprint' => 'fingerprint',
            'sentat' => null,
            'expiresat' => null,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return $panelkeyid;
    }

    /**
     * Runs every queued key-email task, swallowing its mtrace output.
     *
     * @return int Tasks executed.
     */
    private function run_queued_tasks(): int {
        $count = 0;
        $tasks = \core\task\manager::get_adhoc_tasks('\local_mcpconnector\task\send_key_email');
        foreach ($tasks as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
            $count++;
        }
        return $count;
    }

    public function test_queued_key_never_sits_in_the_clear(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $panelkeyid = $this->seed_key($user);

        $this->assertTrue(local_mcpconnector_queue_key_email($user, self::KEY, 'https://mcp.example.com', $panelkeyid));

        $records = $DB->get_records('task_adhoc', ['classname' => '\local_mcpconnector\task\send_key_email']);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertStringNotContainsString(self::KEY, $record->customdata);
        // Only the site key can turn it back into a key.
        $data = json_decode($record->customdata);
        $this->assertSame(self::KEY, \core\encryption::decrypt($data->mcpkeyenc));
    }

    public function test_task_emails_the_key_and_records_the_delivery(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $panelkeyid = $this->seed_key($user);

        local_mcpconnector_queue_key_email($user, self::KEY, 'https://mcp.example.com', $panelkeyid);

        $sink = $this->redirectEmails();
        $this->assertSame(1, $this->run_queued_tasks());
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame($user->email, $messages[0]->to);
        $this->assertStringContainsString(self::KEY, quoted_printable_decode($messages[0]->body));

        // The delivery stamp only lands once the mail is actually away.
        $this->assertNotEmpty($DB->get_field('local_mcpconnector_keys', 'sentat', ['panelkeyid' => $panelkeyid]));
    }

    public function test_task_for_a_deleted_user_is_a_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $panelkeyid = $this->seed_key($user);

        local_mcpconnector_queue_key_email($user, self::KEY, 'https://mcp.example.com', $panelkeyid);
        // Flagged directly rather than through delete_user(), which would fire
        // the plugin's own observers — a different story, tested elsewhere.
        $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);

        $sink = $this->redirectEmails();
        $this->run_queued_tasks();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages);
        $this->assertEmpty($DB->get_field('local_mcpconnector_keys', 'sentat', ['panelkeyid' => $panelkeyid]));
    }

    public function test_undeliverable_key_is_retried_not_dropped(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $panelkeyid = $this->seed_key($user);

        local_mcpconnector_queue_key_email($user, self::KEY, 'https://mcp.example.com', $panelkeyid);

        // A user Moodle cannot mail: the task must fail loudly so Moodle
        // retries — it holds the only copy of the key value there will be.
        $DB->set_field('user', 'email', '', ['id' => $user->id]);
        $tasks = \core\task\manager::get_adhoc_tasks('\local_mcpconnector\task\send_key_email');
        $task = reset($tasks);

        $thrown = false;
        ob_start();
        try {
            $task->execute();
        } catch (\moodle_exception $e) {
            $thrown = true;
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($thrown);
        $this->assertEmpty($DB->get_field('local_mcpconnector_keys', 'sentat', ['panelkeyid' => $panelkeyid]));
        // Core reports the missing address to the developer log.
        $this->resetDebugging();
    }
}
