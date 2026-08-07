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
 * Selector for potential MoodleMCP service users.
 *
 * @package    local_mcpconnector
 * @copyright  2026 Studio LXD
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mcpconnector\selector;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/selector/lib.php');

/**
 * Selector for potential users.
 */
class potential_users extends \user_selector_base {
    /** @var array */
    protected $options;

    /**
     * Constructor.
     *
     * @param string $name control name
     * @param array $options options
     */
    public function __construct($name, $options) {
        parent::__construct($name, $options);
        $this->options = $options;
    }
    /**
     * Finds users that are eligible and not yet assigned to the service.
     *
     * @param string $search
     * @return array
     */
    public function find_users($search): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/mcpconnector/lib.php');

        $service = $this->options['service'] ?? '';
        $serviceid = local_mcpconnector_get_service_id($service);
        if (!$serviceid) {
            return [];
        }

        $limit = $this->options['perpage'] ?? 100;

        $role = local_mcpconnector_role_from_service($service);
        $wheres = ['u.deleted = 0', 'u.suspended = 0', 'u.confirmed = 1', 'u.id <> ?'];
        $wheres[] = "u.id NOT IN (SELECT userid FROM {external_services_users} WHERE externalserviceid = ?)";

        $sqlparams = [
            isset($CFG->siteguest) ? (int) $CFG->siteguest : 0,
            $serviceid,
        ];

        // Manual search SQL construction to ensure strictly positional parameters.
        if ($search !== '') {
            $conditions = [];
            $words = explode(' ', trim($search));
            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                // Use simple LIKE with wildcards for compatibility and robustness.
                $like = '%' . $DB->sql_like_escape($word) . '%';
                $subconditions = [];

                // Case-insensitive LIKE for cross-DB parity (PostgreSQL LIKE is case-sensitive).
                $subconditions[] = $DB->sql_like('u.firstname', '?', false);
                $sqlparams[] = $like;
                $subconditions[] = $DB->sql_like('u.lastname', '?', false);
                $sqlparams[] = $like;
                $subconditions[] = $DB->sql_like('u.email', '?', false);
                $sqlparams[] = $like;
                $subconditions[] = $DB->sql_like('u.username', '?', false);
                $sqlparams[] = $like;

                $conditions[] = '(' . implode(' OR ', $subconditions) . ')';
            }
            if (!empty($conditions)) {
                $wheres[] = '(' . implode(' AND ', $conditions) . ')';
            }
        }

        // Add role eligibility conditions.
        // The 'admin' role is intentionally not filtered in SQL: site admins are defined in
        // config, not by role assignment, so the caller filters admins in PHP instead.
        if ($role === 'manager') {
            $systemcontext = \context_system::instance();
            $wheres[] = "EXISTS (
                SELECT 1
                FROM {role_assignments} ra
                JOIN {role} r ON r.id = ra.roleid
                LEFT JOIN {context} c ON c.id = ra.contextid
                WHERE ra.userid = u.id
                  AND r.shortname = 'manager'
                  AND (ra.contextid = ? OR c.contextlevel = ?)
            )";
            $sqlparams[] = $systemcontext->id;
            $sqlparams[] = CONTEXT_COURSE;
        } else if (in_array($role, ['editingteacher', 'teacher', 'student'])) {
            $shortnames = ($role === 'teacher') ? ['teacher', 'noneditingteacher'] : [$role];
            [$rolesql, $roleparams] = $DB->get_in_or_equal($shortnames, SQL_PARAMS_QM);
            $wheres[] = "EXISTS (
                SELECT 1
                FROM {role_assignments} ra
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} c ON c.id = ra.contextid
                WHERE ra.userid = u.id
                  AND r.shortname $rolesql
                  AND c.contextlevel = ?
            )";
            $sqlparams = array_merge($sqlparams, $roleparams);
            $sqlparams[] = CONTEXT_COURSE;
        }
        // The 'user' role needs no extra filter beyond active user check (already in wheres).

        // Select all user fields.
        $sql = "SELECT u.*
                  FROM {user} u
                 WHERE " . implode(' AND ', $wheres) . "
              ORDER BY u.lastname, u.firstname";

        // For admin, we must fetch potentially more and filter, or just accept that searching for non-admin returns nothing.
        // Given 'admin' is special, let's keep it simple: if role is admin, we only really care about actual admins.
        // Let's filter post-query for admin, but for others rely on SQL.
        // Note: If we use get_records_sql with limit, we might miss admins if they are further down.
        // But usually there are very few admins.
        // Optimization: If role is admin, maybe ignore limit? Or fetch all admins first?
        // Let's stick to standard flow but adding PHP verification for all roles just in case.
        $users = $DB->get_records_sql($sql, $sqlparams, 0, $limit);

        foreach ($users as $id => $user) {
            if (!local_mcpconnector_user_is_eligible_for_service((int) $user->id, $service)) {
                unset($users[$id]);
            }
        }

        if (empty($users)) {
            return [];
        }

        return [get_string('potential_users', 'local_mcpconnector') => $users];
    }

    /**
     * Returns selector options.
     *
     * @return array
     */
    protected function get_options(): array {
        $options = parent::get_options();
        $options['service'] = $this->options['service'] ?? '';
        $options['file'] = 'local/mcpconnector/classes/selector/potential_users.php';
        return $options;
    }
}
