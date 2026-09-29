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
 * Tests for the MCP endpoint URL the panel returns on `/api/moodle/verify`.
 *
 * There is no admin field for `mcp_url` any more (1.3.5): it arrives from the
 * panel's verify response instead, and is trusted only when it looks like a
 * real https:// URL, so a malformed or tampered response can never plant a
 * bogus endpoint in the key emails.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_mcpconnector_validate_license
 * @covers     \local_mcpconnector_is_https_url
 */
final class license_verify_mcp_url_test extends \advanced_testcase {
    /**
     * Loads the plugin library and configures a signable panel connection.
     */
    private function setup_panel(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        set_config('panel_url', 'https://panel.example.com', 'local_mcpconnector');
        set_config('panel_secret', 'secret-1', 'local_mcpconnector');
    }

    public function test_valid_https_mcp_url_is_stored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_panel();

        \curl::mock_response(json_encode([
            'valid' => true,
            'mcpUrl' => 'https://acme.slxd.app/mcp/lmsmcp/conn-1',
        ]));

        $result = local_mcpconnector_validate_license('lic-1');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('https://acme.slxd.app/mcp/lmsmcp/conn-1', local_mcpconnector_get_mcp_url());
    }

    public function test_non_https_mcp_url_is_not_stored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_panel();
        set_config('mcp_url', '', 'local_mcpconnector');

        \curl::mock_response(json_encode([
            'valid' => true,
            'mcpUrl' => 'http://insecure.example.com/mcp',
        ]));

        $result = local_mcpconnector_validate_license('lic-1');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('', local_mcpconnector_get_mcp_url());
    }

    public function test_missing_mcp_url_keeps_the_previous_value(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_panel();
        set_config('mcp_url', 'https://acme.slxd.app/mcp/lmsmcp/old', 'local_mcpconnector');

        // An older panel that predates the mcpUrl contract: valid, but silent on it.
        \curl::mock_response(json_encode([
            'valid' => true,
        ]));

        $result = local_mcpconnector_validate_license('lic-1');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('https://acme.slxd.app/mcp/lmsmcp/old', local_mcpconnector_get_mcp_url());
    }

    public function test_is_https_url_rejects_non_https_and_malformed_values(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        $this->assertTrue(local_mcpconnector_is_https_url('https://acme.slxd.app/mcp/x'));
        $this->assertFalse(local_mcpconnector_is_https_url('http://acme.slxd.app/mcp/x'));
        $this->assertFalse(local_mcpconnector_is_https_url('javascript:alert(1)'));
        $this->assertFalse(local_mcpconnector_is_https_url(''));
    }
}
