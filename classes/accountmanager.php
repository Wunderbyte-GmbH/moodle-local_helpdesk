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
 * accountmanager
 *
 * @package     local_helpdesk
 * @author      Thomas Winkler
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

/**
 * Class accountmanager
 *
 * @author      Thomas Winkler
 * @copyright   2022 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accountmanager {
    /** @var int Id of the user this account manager belongs to. */
    public $userid;

    /**
     * empty constructor accountmanager
     *
     */
    public function __construct() {
    }

    /**
     * Store the account managers and the capabilities that make somebody one in the plugin settings.
     *
     * @param array $accountmanagers ids of the users who can be picked as account manager.
     * @param array $capstocheck capabilities that make a user an account manager.
     */
    public function form_to_config_helpdesk_accountmanager(array $accountmanagers, array $capstocheck) {
        $accountmanagerslist = implode(',', $accountmanagers);
        $capstocheck = implode(',', $capstocheck);
        set_config('accountmanagers', $accountmanagerslist, 'local_helpdesk');
        set_config('capstocheck', $capstocheck, 'local_helpdesk');
    }

    /**
     * returns all managers from site
     *
     * @return array
     */
    public static function get_capabiltities_to_check() {
        return [
            'moodle/course:manageactivities' => 'moodle/course:manageactivities',
            'moodle/course:viewhiddenactivities' => 'moodle/course:viewhiddenactivities',
            'moodle/category:manage' => 'moodle/category:manage',
            'enrol/category:config' => 'enrol/category:config',
        ];
    }

    /**
     * Everybody who may create courses somewhere, and the site administrators.
     *
     * @return string[] user id => name, sorted by username.
     */
    public static function get_all_category_managers_from_site() {
        global $DB;
        $users = [];
        $roles = get_roles_with_capability('moodle/course:create', CAP_ALLOW);
        if ($roles) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($roles));
            $sql = "SELECT DISTINCT u.id, u.username, u.firstname, u.lastname
                      FROM {role_assignments} ra
                      JOIN {user} u ON u.id = ra.userid
                     WHERE u.deleted = 0 AND ra.roleid $insql";
            $users = $DB->get_records_sql($sql, $params);
        }
        foreach (get_admins() as $admin) {
            $users[$admin->id] = $admin;
        }
        \core_collator::asort_objects_by_property($users, 'username');

        $possibleusers = [];
        foreach ($users as $user) {
            $possibleusers[$user->id] = $user->firstname . ' ' . $user->lastname;
        }
        return $possibleusers;
    }

    /**
     * Checks if a user can choose an accountmanager
     *
     * @return bool
     */
    public function can_choose_accountmanager(): bool {
        global $DB, $USER;
        $capability = get_config('local_helpdesk', 'capstocheck');
        if (empty($capability)) {
            return false;
        }
        $capability = explode(',', $capability);
        $sql = "SELECT DISTINCT c.id AS cid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE ra.userid = ?
                       AND c.contextlevel IN (" . CONTEXT_SYSTEM . ", " . CONTEXT_COURSECAT . ", " . CONTEXT_COURSE . ")";
        $records = $DB->get_records_sql($sql, [$USER->id]);
        if (!empty($records)) {
            foreach ($records as $record) {
                $context = \context::instance_by_id($record->cid);
                if (has_any_capability($capability, $context)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Prepares the accountmanagers for the issue create form
     *
     * @param \MoodleQuickForm $mform the form to add the account manager field to.
     * @return void
     */
    public function prepare_accountmanager_for_form(&$mform): void {
        global $CFG;
        $accountmanagers = get_config('local_helpdesk', 'accountmanagers');
        if (empty($accountmanagers) || isguestuser()) {
            return;
        }
        require_once($CFG->dirroot . '/user/lib.php');
        $users = \user_get_users_by_id(explode(',', $accountmanagers));
        if (empty($users) || !$this->can_choose_accountmanager()) {
            return;
        }
        $options = ['0' => get_string('none', 'local_helpdesk')];

        foreach ($users as $user) {
            $options[$user->id] = $user->firstname . ' ' . $user->lastname;
        }

        $mform->addElement('select', 'accountmanager', get_string('accountmanager', 'local_helpdesk'), $options);
        $mform->setDefault('accountmanager', 0);
    }

    /**
     * Deletes account manager from setting
     *
     * @param int $userid
     * @return void
     */
    public static function delete_account_manager(int $userid) {
        if (!get_config('local_helpdesk', 'accountmanagers')) {
            return;
        }
        $accountmanagers = explode(',', get_config('local_helpdesk', 'accountmanagers'));
        if (in_array($userid, $accountmanagers)) {
            unset($accountmanagers[array_search($userid, $accountmanagers)]);
            $accountmanagerslist = implode(',', $accountmanagers);
            set_config('accountmanagers', $accountmanagerslist, 'local_helpdesk');
        }
    }
}
