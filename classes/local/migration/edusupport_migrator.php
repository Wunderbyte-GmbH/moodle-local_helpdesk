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
 * Take over the data of a local_edusupport installation.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\migration;

use moodle_exception;

/**
 * Copies everything local_edusupport stored into local_helpdesk and puts local_edusupport to rest.
 *
 * The record ids are kept, because the subscriptions point at the issues by id. For that reason
 * the migration only runs into empty helpdesk tables. The issues, supporters and subscriptions
 * of local_edusupport are left alone as a backup; only its list of support forums is emptied
 * (and kept as JSON in our config), so that the two plugins never handle the same forum.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edusupport_migrator {
    /** @var string component we migrate from. */
    const SOURCE = 'local_edusupport';

    /** @var string component we migrate to. */
    const TARGET = 'local_helpdesk';

    /** @var int the local_edusupport version whose schema equals ours (2.8.0). */
    const REQUIRED_SOURCE_VERSION = 2026091001;

    /** @var string[] source table => target table. The forum registry comes first. */
    const TABLES = [
        'local_edusupport' => 'local_helpdesk',
        'local_edusupport_issues' => 'local_helpdesk_issues',
        'local_edusupport_supporters' => 'local_helpdesk_supporters',
        'local_edusupport_subscr' => 'local_helpdesk_subscr',
    ];

    /** @var string[] settings that are not copied, because they are handled on their own. */
    const CONFIG_SKIP = ['version', 'supportteamrole', 'guestuserid'];

    /** @var string[] adhoc task classes, old => new. */
    const ADHOC_TASKS = [
        '\\local_edusupport\\task\\reminder' => '\\local_helpdesk\\task\\reminder',
        '\\local_edusupport\\task\\send_mail' => '\\local_helpdesk\\task\\send_mail',
    ];

    /** @var string prefix of the message preferences of local_edusupport. */
    const PREF_OLD = 'message_provider_local_edusupport_edusupport_issue_';

    /** @var string prefix of our message preferences. */
    const PREF_NEW = 'message_provider_local_helpdesk_helpdesk_issue_';

    /**
     * Whether there are tables of local_edusupport on this site at all.
     *
     * @return bool
     */
    public static function source_exists(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (array_keys(self::TABLES) as $table) {
            if (!$dbman->table_exists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The reason why the migration can not run, as a string identifier of local_helpdesk.
     *
     * @return string|null null when the migration can run.
     */
    public static function get_blocker(): ?string {
        global $DB;
        if (!self::source_exists()) {
            return 'migrate:nosource';
        }
        if ((int) get_config(self::SOURCE, 'version') < self::REQUIRED_SOURCE_VERSION) {
            return 'migrate:sourcetooold';
        }
        foreach (self::TABLES as $target) {
            if ($DB->record_exists($target, [])) {
                return 'migrate:notempty';
            }
        }
        return null;
    }

    /**
     * Count what a migration would take over. Changes nothing.
     *
     * @return int[] string identifier of local_helpdesk => number of records.
     */
    public static function get_summary(): array {
        global $DB;
        if (!self::source_exists()) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys(self::ADHOC_TASKS));
        [$skipsql, $skipparams] = $DB->get_in_or_equal(self::CONFIG_SKIP, SQL_PARAMS_QM, 'param', false);
        return [
            'migrate:count:supportforums' => $DB->count_records('local_edusupport'),
            'migrate:count:issues' => $DB->count_records('local_edusupport_issues'),
            'migrate:count:supporters' => $DB->count_records('local_edusupport_supporters'),
            'migrate:count:subscriptions' => $DB->count_records('local_edusupport_subscr'),
            'migrate:count:settings' => $DB->count_records_select(
                'config_plugins',
                "plugin = ? AND name $skipsql",
                array_merge([self::SOURCE], $skipparams)
            ),
            'migrate:count:adhoctasks' => $DB->count_records_select('task_adhoc', "classname $insql", $inparams),
            'migrate:count:preferences' => $DB->count_records_select(
                'user_preferences',
                $DB->sql_like('name', '?'),
                [$DB->sql_like_escape(self::PREF_OLD) . '%']
            ),
        ];
    }

    /**
     * Run the migration.
     *
     * @return int[] what was taken over, in the format of get_summary().
     * @throws moodle_exception when the migration can not run.
     */
    public static function migrate(): array {
        global $DB;

        if ($blocker = self::get_blocker()) {
            throw new moodle_exception($blocker, self::TARGET);
        }
        $summary = self::get_summary();

        $transaction = $DB->start_delegated_transaction();
        self::copy_tables();
        self::copy_config();
        self::take_over_role();
        self::take_over_guestuser();
        self::take_over_adhoc_tasks();
        self::copy_message_preferences();
        self::retire_source();
        set_config('migratedfromedusupport', time(), self::TARGET);
        $transaction->allow_commit();

        // Some databases end the transaction when a sequence is touched, so this comes last.
        $dbman = $DB->get_manager();
        foreach (self::TABLES as $target) {
            $dbman->reset_sequence($target);
        }

        \cache_helper::purge_by_event('local_helpdesk_setbacksupportmenu');
        \cache_helper::purge_by_definition(self::TARGET, 'supportmenu');

        return $summary;
    }

    /**
     * Copy the four tables record by record and keep the ids.
     *
     * @return void
     */
    protected static function copy_tables(): void {
        global $DB;
        foreach (self::TABLES as $source => $target) {
            $columns = array_keys($DB->get_columns($target));
            $records = $DB->get_recordset($source, null, 'id ASC');
            foreach ($records as $record) {
                $copy = new \stdClass();
                foreach ($columns as $column) {
                    if (property_exists($record, $column)) {
                        $copy->$column = $record->$column;
                    }
                }
                $DB->insert_record_raw($target, $copy, false, false, true);
            }
            $records->close();
        }
    }

    /**
     * Copy the settings, including the ones that are only written by code (centralforum, accountmanagers, ...).
     *
     * @return void
     */
    protected static function copy_config(): void {
        foreach ((array) get_config(self::SOURCE) as $name => $value) {
            if (!in_array($name, self::CONFIG_SKIP)) {
                set_config($name, $value, self::TARGET);
            }
        }
    }

    /**
     * Make the support team role of local_edusupport ours, so that every assignment in the support forums stays.
     *
     * @return void
     */
    protected static function take_over_role(): void {
        global $DB;
        $oldrole = $DB->get_record('role', ['id' => (int) get_config(self::SOURCE, 'supportteamrole')]);
        if (!$oldrole) {
            $oldrole = $DB->get_record('role', ['shortname' => self::SOURCE]);
        }
        if (!$oldrole) {
            return;
        }
        $newrole = $DB->get_record('role', ['shortname' => self::TARGET]);
        if ($newrole && $newrole->id != $oldrole->id) {
            if ($DB->record_exists('role_assignments', ['roleid' => $newrole->id])) {
                // Somebody uses our own role already: keep it and repeat the assignments of the old one.
                $assignments = $DB->get_recordset('role_assignments', ['roleid' => $oldrole->id]);
                foreach ($assignments as $assignment) {
                    role_assign($newrole->id, $assignment->userid, $assignment->contextid);
                }
                $assignments->close();
                return;
            }
            delete_role($newrole->id);
        }
        $oldrole->shortname = self::TARGET;
        if ($oldrole->name === 'eduSupport Team') {
            $oldrole->name = 'Helpdesk Team';
        }
        $DB->update_record('role', $oldrole);
        set_config('supportteamrole', $oldrole->id, self::TARGET);
    }

    /**
     * Make the guest ticket user of local_edusupport ours, so that its forum posts keep their author.
     *
     * @return void
     */
    protected static function take_over_guestuser(): void {
        global $DB, $CFG;
        $olduser = $DB->get_record('user', ['id' => (int) get_config(self::SOURCE, 'guestuserid'), 'deleted' => 0]);
        if (!$olduser) {
            return;
        }
        $identity = ['email' => 'helpdesk@example.com', 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0];
        $newuser = $DB->get_record('user', $identity);
        if ($newuser && $newuser->id != $olduser->id) {
            if ($DB->record_exists('forum_posts', ['userid' => $newuser->id])) {
                // Our own guest user has been posting already: it stays what it is.
                return;
            }
            delete_user($newuser);
        }
        $DB->update_record('user', (object) [
            'id' => $olduser->id,
            'username' => 'helpdesk_guest_ticket',
            'email' => 'helpdesk@example.com',
        ]);
        set_config('guestuserid', $olduser->id, self::TARGET);
    }

    /**
     * Hand the waiting reminders and mails over to our own task classes.
     *
     * @return void
     */
    protected static function take_over_adhoc_tasks(): void {
        global $DB;
        foreach (self::ADHOC_TASKS as $old => $new) {
            $tasks = $DB->get_records('task_adhoc', ['classname' => $old], '', 'id');
            foreach ($tasks as $task) {
                $task->classname = $new;
                $task->component = self::TARGET;
                $DB->update_record('task_adhoc', $task);
            }
        }
    }

    /**
     * Copy what the users chose for the notifications of local_edusupport.
     *
     * @return void
     */
    protected static function copy_message_preferences(): void {
        global $DB;
        $preferences = $DB->get_recordset_select(
            'user_preferences',
            $DB->sql_like('name', '?'),
            [$DB->sql_like_escape(self::PREF_OLD) . '%']
        );
        foreach ($preferences as $preference) {
            $name = self::PREF_NEW . substr($preference->name, strlen(self::PREF_OLD));
            if (!$DB->record_exists('user_preferences', ['userid' => $preference->userid, 'name' => $name])) {
                $DB->insert_record('user_preferences', (object) [
                    'userid' => $preference->userid,
                    'name' => $name,
                    'value' => $preference->value,
                ]);
                mark_user_preferences_changed($preference->userid);
            }
        }
        $preferences->close();
    }

    /**
     * Stop local_edusupport from handling the forums we are responsible for now.
     *
     * Without a registered support forum its observers, reminders and forms have nothing to work on.
     *
     * @return void
     */
    protected static function retire_source(): void {
        global $DB;
        $registry = array_values($DB->get_records('local_edusupport', null, 'id ASC'));
        set_config('edusupportregistrybackup', json_encode($registry), self::TARGET);
        $DB->delete_records('local_edusupport');
        set_config('centralforum', 0, self::SOURCE);
        $DB->set_field('task_scheduled', 'disabled', 1, ['component' => self::SOURCE]);
    }
}
