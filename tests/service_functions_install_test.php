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
 * Tests that the plugin services get their local_mcpconnector_* functions on a
 * clean install, and that installs made before 1.3.4 — whose services were
 * created before Moodle registered those functions — are repaired without
 * touching the administrator's own changes.
 *
 * A clean install is reproduced by deleting the plugin services and the
 * plugin's rows in external_functions: that is exactly the state Moodle leaves
 * when it runs xmldb_local_mcpconnector_install(), since it registers
 * db/services.php only afterwards.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \xmldb_local_mcpconnector_install
 * @covers     \local_mcpconnector_add_missing_plugin_functions
 */
final class service_functions_install_test extends \advanced_testcase {
    /**
     * Loads the plugin library and install hook.
     */
    private function load(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/service_functions.php');
        require_once($CFG->dirroot . '/local/mcpconnector/db/install.php');
        require_once($CFG->libdir . '/upgradelib.php');
    }

    /**
     * Puts the site in the state Moodle has when the install hook runs: no
     * plugin services and none of the plugin's functions registered.
     */
    private function wipe_to_preinstall(): void {
        global $DB;
        foreach (local_mcpconnector_get_service_definitions() as $definition) {
            $serviceid = $DB->get_field('external_services', 'id', ['shortname' => $definition['shortname']]);
            if ($serviceid) {
                $DB->delete_records('external_services_functions', ['externalserviceid' => $serviceid]);
                $DB->delete_records('external_services', ['id' => $serviceid]);
            }
        }
        $DB->delete_records('external_functions', ['component' => 'local_mcpconnector']);
    }

    /**
     * The functions the given service has, by name.
     *
     * @param string $shortname
     * @return string[]
     */
    private function service_functions(string $shortname): array {
        global $DB;
        $serviceid = $DB->get_field('external_services', 'id', ['shortname' => $shortname], MUST_EXIST);
        $names = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$serviceid]
        );
        sort($names);
        return $names;
    }

    /**
     * The plugin's own functions a service definition lists.
     *
     * @param string $shortname
     * @return string[]
     */
    private function plugin_functions_of(string $shortname): array {
        foreach (local_mcpconnector_get_service_definitions() as $definition) {
            if ($definition['shortname'] === $shortname) {
                $names = array_values(array_filter(
                    $definition['functions'],
                    fn(string $name): bool => strpos($name, 'local_mcpconnector_') === 0
                ));
                sort($names);
                return $names;
            }
        }
        $this->fail("No definition for {$shortname}");
    }

    /**
     * The plugin functions of $shortname that the service lacks.
     *
     * @param string $shortname
     * @return string[]
     */
    private function missing_plugin_functions(string $shortname): array {
        return array_values(array_diff($this->plugin_functions_of($shortname), $this->service_functions($shortname)));
    }

    public function test_clean_install_gives_services_their_plugin_functions(): void {
        $this->load();
        $this->resetAfterTest();
        $this->assertNotEmpty($this->plugin_functions_of('mcpconnector_admin'));

        // The bug, as it was: services created before the functions exist.
        $this->wipe_to_preinstall();
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();
        $this->assertSame(
            $this->plugin_functions_of('mcpconnector_admin'),
            $this->missing_plugin_functions('mcpconnector_admin')
        );

        // The install hook, from that same pre-install state.
        $this->wipe_to_preinstall();
        xmldb_local_mcpconnector_install();
        foreach (local_mcpconnector_get_service_definitions() as $definition) {
            $this->assertSame([], $this->missing_plugin_functions($definition['shortname']), $definition['shortname']);
        }
    }

    public function test_repair_adds_missing_plugin_functions_and_keeps_admin_changes(): void {
        global $DB;
        $this->load();
        $this->resetAfterTest();

        // An install made before 1.3.4: services without plugin functions,
        // then Moodle registers them.
        $this->wipe_to_preinstall();
        local_mcpconnector_ensure_services();
        external_update_descriptions('local_mcpconnector');
        $this->assertNotEmpty($this->missing_plugin_functions('mcpconnector_admin'));

        $serviceid = (int) $DB->get_field('external_services', 'id', ['shortname' => 'mcpconnector_admin'], MUST_EXIST);
        $current = $this->service_functions('mcpconnector_admin');

        // The administrator removed a baseline core function...
        $removed = null;
        foreach ($current as $name) {
            if (strpos($name, 'local_mcpconnector_') !== 0) {
                $removed = $name;
                break;
            }
        }
        $this->assertNotNull($removed);
        $DB->delete_records('external_services_functions', ['externalserviceid' => $serviceid, 'functionname' => $removed]);

        // ...and added one the baseline does not have.
        [$notin, $params] = $DB->get_in_or_equal($current, SQL_PARAMS_QM, 'param', false);
        $extra = $DB->get_field_select(
            'external_functions',
            'name',
            "component <> 'local_mcpconnector' AND name {$notin}",
            $params,
            IGNORE_MULTIPLE
        );
        $this->assertNotEmpty($extra);
        $DB->insert_record('external_services_functions', (object) [
            'externalserviceid' => $serviceid,
            'functionname' => $extra,
        ]);

        $this->assertGreaterThan(0, local_mcpconnector_add_missing_plugin_functions());

        $after = $this->service_functions('mcpconnector_admin');
        $this->assertSame([], $this->missing_plugin_functions('mcpconnector_admin'));
        $this->assertNotContains($removed, $after);
        $this->assertContains($extra, $after);

        // Idempotent: a second run adds nothing.
        $this->assertSame(0, local_mcpconnector_add_missing_plugin_functions());
        $this->assertSame($after, $this->service_functions('mcpconnector_admin'));
    }
}
