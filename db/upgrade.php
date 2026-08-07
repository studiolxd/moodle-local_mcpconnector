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
 * Upgrade steps for Moodle MCP
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    local_mcpconnector
 * @category   upgrade
 * @copyright  2026 Studio LXD <hello@studiolxd.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_mcpconnector_upgrade($oldversion) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

    if ($oldversion < 2026012001) {
        local_mcpconnector_ensure_services();

        if (get_config('local_mcpconnector', 'license_status') === false) {
            set_config('license_status', 'missing', 'local_mcpconnector');
        }

        if (get_config('local_mcpconnector', 'auto_sync') === false) {
            set_config('auto_sync', 0, 'local_mcpconnector');
        }
        if (get_config('local_mcpconnector', 'auto_email') === false) {
            set_config('auto_email', 0, 'local_mcpconnector');
        }
        if (get_config('local_mcpconnector', 'email_subject') === false) {
            set_config('email_subject', get_string('email_subject_default', 'local_mcpconnector'), 'local_mcpconnector');
        }
        if (get_config('local_mcpconnector', 'email_body') === false) {
            set_config('email_body', get_string('email_body_default', 'local_mcpconnector'), 'local_mcpconnector');
        }

        upgrade_plugin_savepoint(true, 2026012001, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026012002) {
        upgrade_plugin_savepoint(true, 2026012002, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026012003) {
        upgrade_plugin_savepoint(true, 2026012003, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026020500) {
        // Sync all service functions from generated definitions.
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026020500, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071000) {
        // Panel API v2: local key metadata table (the panel no longer returns
        // key values or tokens, so the plugin tracks its own keys).
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_mcpconnector_keys');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('panelkeyid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null);
        $table->add_field('keylast4', XMLDB_TYPE_CHAR, '8', null, null, null, null);
        $table->add_field('roles', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('sentat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('panelkeyid', XMLDB_INDEX_UNIQUE, ['panelkeyid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The v2 API authenticates with a license key + panel secret pair:
        // force re-verification with the new credentials.
        set_config('license_status', 'missing', 'local_mcpconnector');

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071005) {
        // Optional key expiry (key_lifetime_days setting).
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_mcpconnector_keys');
        $field = new xmldb_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sentat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026071005, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071400) {
        // New plugin webservice functions (update_module, create_module,
        // create_scorm): re-sync the role services so their function
        // whitelists pick them up.
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026071400, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071401) {
        // 2.3 REGRESSION FIX. Adding db/services.php made Moodle's
        // post-upgrade sync treat our component-owned services as
        // file-managed: services absent from the file are DELETED together
        // with every token (lib/upgradelib.php, external_update_descriptions)
        // — which globally invalidated all MCP keys after installing 2.3.
        //
        // 1. Detach any surviving services from the component so the sync
        //    never touches them again (custom services are ignored).
        $DB->set_field('external_services', 'component', null,
            ['component' => 'local_mcpconnector']);

        // 2. Recreate the services the 2.3 upgrade deleted (now detached).
        local_mcpconnector_ensure_services();

        // 3. Register this plugin's external functions NOW (upgrade.php runs
        //    BEFORE Moodle's own external_update_descriptions, so on the 2.3
        //    upgrade the new functions were skipped by the whitelist sync).
        //    Safe to call here: with no component-owned services left, its
        //    deletion branch is a no-op.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');

        // 4. Whitelists complete, including the three 2.3 functions.
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026071401, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071600) {
        // 2.5: new create_resource function. Register it now (upgrade.php runs
        // BEFORE Moodle's own external_update_descriptions — the 2.3 lesson)
        // and re-sync the role-service whitelists. Our services are custom
        // (component null), so the registration's deletion branch never
        // touches them.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026071600, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071700) {
        // 2.6: create_quiz + import_questions. Same pattern as 2.5: register
        // the new functions inside the upgrade, then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026071700, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071800) {
        // 2.7: add_random_questions + list_question_banks. Same pattern:
        // register the new functions inside the upgrade, then re-sync.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026071800, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026071900) {
        // 2.8: create_scale, update_assign, update_grade_item,
        // set_course_completion. Same pattern: register inside the upgrade,
        // then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026071900, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026072000) {
        // 2.9: purge_questions. Same pattern: register inside the upgrade,
        // then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026072000, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026072200) {
        // 2.11: update_grade_category + delete_grade_category. Same pattern:
        // register inside the upgrade, then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026072200, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026072300) {
        // 2.12: get_grade_categories. Same pattern: register inside the
        // upgrade, then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026072300, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026072500) {
        // 2.14: core calendar write functions (create/delete_calendar_events)
        // added to the role whitelists. Core functions are already registered;
        // only the service whitelists need a re-sync.
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026072500, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026073100) {
        // 2.20: issue_badge. Same pattern: register the new function inside
        // the upgrade, then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026073100, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026073200) {
        // 2.21: backup_course + restore_course. Same pattern: register the
        // new functions inside the upgrade, then re-sync the whitelists.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026073200, 'local', 'mcpconnector');
    }

    if ($oldversion < 2026073300) {
        // 2.22: create_h5p + create_book + add_to_content_bank. Same pattern:
        // register the new functions inside the upgrade, then re-sync.
        require_once($CFG->libdir . '/upgradelib.php');
        external_update_descriptions('local_mcpconnector');
        local_mcpconnector_ensure_services();
        local_mcpconnector_sync_all_service_functions();

        upgrade_plugin_savepoint(true, 2026073300, 'local', 'mcpconnector');
    }

    return true;
}
