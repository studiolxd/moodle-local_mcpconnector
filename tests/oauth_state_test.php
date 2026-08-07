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
 * Unit tests for local_mcpconnector_verify_oauth_state — the trust anchor of
 * the OAuth handshake: a forged or expired state must never start a token
 * delivery, and a valid one signed by the panel must always pass.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_verify_oauth_state
 */
final class oauth_state_test extends \advanced_testcase {
    /** @var string The per-install panel secret used to sign. */
    private const SECRET = 'mcpk_test_secret_0123456789abcdef';

    /**
     * Builds a state exactly like the panel does (signOAuthState in
     * src/lib/moodle/oauth-state.ts): base64url(json).hex_hmac(payload).
     *
     * @param array $payload The state payload.
     * @param string $secret The signing secret.
     * @return string
     */
    private function make_state(array $payload, string $secret = self::SECRET): string {
        $body = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        return $body . '.' . hash_hmac('sha256', $body, $secret);
    }

    public function test_valid_state_passes(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $state = $this->make_state(['sid' => 'abc', 'exp' => time() + 300]);
        $this->assertTrue(local_mcpconnector_verify_oauth_state($state, self::SECRET));
    }

    public function test_wrong_secret_fails(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $state = $this->make_state(['exp' => time() + 300], 'other_secret');
        $this->assertFalse(local_mcpconnector_verify_oauth_state($state, self::SECRET));
    }

    public function test_tampered_payload_fails(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $state = $this->make_state(['exp' => time() + 300]);
        [$body, $sig] = explode('.', $state);
        $forged = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 999999])), '+/', '-_'), '=');
        $this->assertFalse(local_mcpconnector_verify_oauth_state($forged . '.' . $sig, self::SECRET));
    }

    public function test_expired_state_fails(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $state = $this->make_state(['exp' => time() - 1]);
        $this->assertFalse(local_mcpconnector_verify_oauth_state($state, self::SECRET));
    }

    public function test_malformed_states_fail(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        $this->assertFalse(local_mcpconnector_verify_oauth_state('', self::SECRET));
        $this->assertFalse(local_mcpconnector_verify_oauth_state('nodothere', self::SECRET));
        $this->assertFalse(local_mcpconnector_verify_oauth_state('.onlysig', self::SECRET));
        // Valid signature over garbage (not base64url json).
        $garbage = '!!notbase64!!';
        $sig = hash_hmac('sha256', $garbage, self::SECRET);
        $this->assertFalse(local_mcpconnector_verify_oauth_state($garbage . '.' . $sig, self::SECRET));
        // Valid signature, json but no exp.
        $noexp = $this->make_state(['sid' => 'x']);
        $this->assertFalse(local_mcpconnector_verify_oauth_state($noexp, self::SECRET));
    }
}
