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
 * Tests for the migration from local_edusupport.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\migration;

use advanced_testcase;
use moodle_exception;
use xmldb_file;

/**
 * Tests for the migration from local_edusupport.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\local\migration\edusupport_migrator
 */
final class edusupport_migrator_test extends advanced_testcase {
    /** @var \stdClass a forum that local_edusupport knows as support forum. */
    private $forum;

    /** @var \stdClass the support team role of local_edusupport. */
    private $oldrole;

    /** @var \stdClass the guest ticket user of local_edusupport. */
    private $oldguest;

    /** @var \stdClass a supporter. */
    private $supporter;

    /** @var string[] the tables this test had to create. */
    private $createdtables = [];

    /**
     * Build a site that local_edusupport has been working on.
     *
     * Where local_edusupport is not installed, its tables are created from our own install.xml
     * under their old names: the schema is the same. Where it is installed, its own tables,
     * role and settings are used, and the settings are reduced to the ones set here.
     */
    protected function setUp(): void {
        global $DB, $CFG;

        parent::setUp();
        $this->resetAfterTest(true);

        $dbman = $DB->get_manager();
        $xmldbfile = new xmldb_file($CFG->dirroot . '/local/helpdesk/db/install.xml');
        $xmldbfile->loadXMLStructure();
        $sources = array_flip(edusupport_migrator::TABLES);
        foreach ($xmldbfile->getStructure()->getTables() as $table) {
            if (!isset($sources[$table->getName()])) {
                // A table local_edusupport never had.
                continue;
            }
            $table->setName($sources[$table->getName()]);
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
                $this->createdtables[] = $table->getName();
            }
        }
        unset_all_config_for_plugin('local_edusupport');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->forum = $generator->create_module('forum', ['course' => $course->id]);
        $this->supporter = $generator->create_user();
        $author = $generator->create_user();
        $this->oldguest = $generator->create_user(['username' => 'edusupport_guest_ticket', 'email' => 'edusupport@example.com']);
        $this->oldrole = $DB->get_record('role', ['shortname' => 'local_edusupport']);
        if (!$this->oldrole) {
            $this->oldrole = $DB->get_record('role', ['id' => create_role('eduSupport Team', 'local_edusupport', '')]);
        }
        set_role_contextlevels($this->oldrole->id, [CONTEXT_MODULE]);
        role_assign($this->oldrole->id, $this->supporter->id, \context_module::instance($this->forum->cmid)->id);

        // The ids start high, so that a copy that does not keep them is noticed.
        $DB->insert_record_raw('local_edusupport', (object) [
            'id' => 41, 'categoryid' => $course->category, 'courseid' => $course->id, 'forumid' => $this->forum->id,
            'dedicatedsupporter' => $this->supporter->id,
        ], false, false, true);
        foreach ([51, 52] as $issueid) {
            $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion([
                'course' => $course->id, 'forum' => $this->forum->id, 'userid' => $author->id,
            ]);
            $DB->insert_record_raw('local_edusupport_issues', (object) [
                'id' => $issueid, 'discussionid' => $discussion->id, 'currentsupporter' => $this->supporter->id,
                'priority' => 2, 'status' => 3, 'accountmanager' => null, 'timemodified' => 1700000000, 'timecreated' => 1600000000,
            ], false, false, true);
            $DB->insert_record_raw('local_edusupport_subscr', (object) [
                'id' => $issueid + 20, 'issueid' => $issueid, 'discussionid' => $discussion->id, 'userid' => $this->supporter->id,
            ], false, false, true);
        }
        $DB->insert_record_raw('local_edusupport_supporters', (object) [
            'id' => 61, 'courseid' => \local_helpdesk\lib::SYSTEM_COURSE_ID, 'userid' => $this->supporter->id,
            'supportlevel' => '2nd Level', 'holidaymode' => 0, 'autoassign' => 0,
        ], false, false, true);

        set_config('version', edusupport_migrator::REQUIRED_SOURCE_VERSION, 'local_edusupport');
        set_config('supportteamrole', $this->oldrole->id, 'local_edusupport');
        set_config('guestuserid', $this->oldguest->id, 'local_edusupport');
        set_config('centralforum', $this->forum->id, 'local_edusupport');
        set_config('accountmanagers', $this->supporter->id, 'local_edusupport');
        set_config('extralinks', 'Docs|https://example.com', 'local_edusupport');

        $DB->insert_record('task_adhoc', (object) [
            'component' => 'local_edusupport', 'classname' => '\\local_edusupport\\task\\reminder',
            'nextruntime' => time() + DAYSECS, 'customdata' => '{"issueid":51}', 'blocking' => 0,
        ]);
        set_user_preference('message_provider_local_edusupport_edusupport_issue_enabled', 'email', $this->supporter);
    }

    /**
     * Remove the tables of local_edusupport again.
     */
    protected function tearDown(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach ($this->createdtables as $name) {
            $table = new \xmldb_table($name);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        parent::tearDown();
    }

    /**
     * The summary counts what is there and changes nothing.
     */
    public function test_summary_is_a_dry_run(): void {
        global $DB;

        $this->assertNull(edusupport_migrator::get_blocker());
        $summary = edusupport_migrator::get_summary();

        $this->assertSame(1, $summary['migrate:count:supportforums']);
        $this->assertSame(2, $summary['migrate:count:issues']);
        $this->assertSame(1, $summary['migrate:count:supporters']);
        $this->assertSame(2, $summary['migrate:count:subscriptions']);
        $this->assertSame(3, $summary['migrate:count:settings']);
        $this->assertSame(1, $summary['migrate:count:adhoctasks']);
        $this->assertSame(1, $summary['migrate:count:preferences']);
        foreach (array_keys($summary) as $identifier) {
            $this->assertTrue(get_string_manager()->string_exists($identifier, 'local_helpdesk'));
        }

        $this->assertSame(0, $DB->count_records('local_helpdesk_issues'));
        $this->assertSame(1, $DB->count_records('local_edusupport'));
    }

    /**
     * The records arrive with the ids they had, and the next record gets a free id.
     */
    public function test_tables_are_copied_with_their_ids(): void {
        global $DB;

        edusupport_migrator::migrate();

        $supportforum = $DB->get_record('local_helpdesk', ['id' => 41], '*', MUST_EXIST);
        $this->assertEquals($this->forum->id, $supportforum->forumid);
        $this->assertEquals($this->supporter->id, $supportforum->dedicatedsupporter);

        $old = $DB->get_record('local_edusupport_issues', ['id' => 52], '*', MUST_EXIST);
        $new = $DB->get_record('local_helpdesk_issues', ['id' => 52], '*', MUST_EXIST);
        $this->assertEquals($old, $new);
        $this->assertTrue($DB->record_exists('local_helpdesk_subscr', ['id' => 72, 'issueid' => 52]));
        $this->assertEquals(0, $DB->get_field('local_helpdesk_supporters', 'autoassign', ['id' => 61]));

        $newid = $DB->insert_record('local_helpdesk_issues', (object) ['discussionid' => 1, 'priority' => 1, 'status' => 1]);
        $this->assertGreaterThan(52, $newid);

        $this->assertTrue(\local_helpdesk\lib::is_supportforum($this->forum->id));
    }

    /**
     * Settings, role, guest user, waiting tasks and preferences follow.
     */
    public function test_data_outside_the_tables_is_taken_over(): void {
        global $DB;

        $ownrole = $DB->get_record('role', ['shortname' => 'local_helpdesk']);
        edusupport_migrator::migrate();

        $this->assertEquals($this->forum->id, get_config('local_helpdesk', 'centralforum'));
        $this->assertEquals($this->supporter->id, get_config('local_helpdesk', 'accountmanagers'));
        $this->assertSame('Docs|https://example.com', get_config('local_helpdesk', 'extralinks'));
        $this->assertNotEquals(edusupport_migrator::REQUIRED_SOURCE_VERSION, get_config('local_helpdesk', 'version'));

        // The old role is ours now, with its assignments; the unused one from our install is gone.
        $role = $DB->get_record('role', ['shortname' => 'local_helpdesk'], '*', MUST_EXIST);
        $this->assertEquals($this->oldrole->id, $role->id);
        $this->assertSame('Helpdesk Team', $role->name);
        $this->assertEquals($role->id, get_config('local_helpdesk', 'supportteamrole'));
        $this->assertTrue(user_has_role_assignment(
            $this->supporter->id,
            $role->id,
            \context_module::instance($this->forum->cmid)->id
        ));
        if ($ownrole) {
            $this->assertFalse($DB->record_exists('role', ['id' => $ownrole->id]));
        }

        $guest = $DB->get_record('user', ['id' => $this->oldguest->id]);
        $this->assertSame('helpdesk_guest_ticket', $guest->username);
        $this->assertSame('helpdesk@example.com', $guest->email);
        $this->assertEquals($guest->id, get_config('local_helpdesk', 'guestuserid'));
        $this->assertEquals($guest->id, (new \local_helpdesk\guest_supportuser())->get_support_guestuser()->id);

        $this->assertFalse($DB->record_exists('task_adhoc', ['component' => 'local_edusupport']));
        $this->assertTrue($DB->record_exists('task_adhoc', [
            'component' => 'local_helpdesk', 'classname' => '\\local_helpdesk\\task\\reminder',
        ]));
        $this->assertTrue(\local_helpdesk\lib::issue_already_has_reminder(51));

        $this->assertSame(
            'email',
            get_user_preferences('message_provider_local_helpdesk_helpdesk_issue_enabled', null, $this->supporter->id)
        );
    }

    /**
     * local_edusupport forgets its forums, keeps the rest as a backup, and we remember what it knew.
     */
    public function test_source_is_retired(): void {
        global $DB;

        edusupport_migrator::migrate();

        $this->assertSame(0, $DB->count_records('local_edusupport'));
        $this->assertSame(2, $DB->count_records('local_edusupport_issues'));
        $this->assertSame(1, $DB->count_records('local_edusupport_supporters'));
        $this->assertSame(2, $DB->count_records('local_edusupport_subscr'));
        $this->assertEquals(0, get_config('local_edusupport', 'centralforum'));

        $backup = json_decode(get_config('local_helpdesk', 'edusupportregistrybackup'));
        $this->assertCount(1, $backup);
        $this->assertEquals($this->forum->id, $backup[0]->forumid);
        $this->assertNotEmpty(get_config('local_helpdesk', 'migratedfromedusupport'));
    }

    /**
     * A second run is refused, and so is a run into a helpdesk that is in use.
     */
    public function test_migration_needs_empty_tables(): void {
        global $DB;

        edusupport_migrator::migrate();
        $this->assertSame('migrate:notempty', edusupport_migrator::get_blocker());

        $this->expectException(moodle_exception::class);
        try {
            edusupport_migrator::migrate();
        } finally {
            $this->assertSame(2, $DB->count_records('local_helpdesk_issues'));
        }
    }

    /**
     * An older local_edusupport has another schema and has to be upgraded first.
     */
    public function test_migration_needs_current_source(): void {
        set_config('version', 2022100703, 'local_edusupport');
        $this->assertSame('migrate:sourcetooold', edusupport_migrator::get_blocker());
    }

    /**
     * Without the tables of local_edusupport there is nothing to do.
     */
    public function test_migration_needs_source_tables(): void {
        global $DB;

        if (!in_array('local_edusupport_subscr', $this->createdtables)) {
            $this->markTestSkipped('local_edusupport is installed here, its tables are not ours to drop.');
        }
        $DB->get_manager()->drop_table(new \xmldb_table('local_edusupport_subscr'));

        $this->assertFalse(edusupport_migrator::source_exists());
        $this->assertSame('migrate:nosource', edusupport_migrator::get_blocker());
        $this->assertSame([], edusupport_migrator::get_summary());
    }
}
