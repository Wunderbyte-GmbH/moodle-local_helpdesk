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
 * Tests for creating a support issue through the external function.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;
use local_helpdesk\external\create_issue;
use local_helpdesk\lib;
use local_helpdesk\task\send_mail;
use moodle_exception;
use stdClass;

/**
 * Tests for creating a support issue through the external function.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\external\create_issue
 */
final class create_issue_test extends advanced_testcase {
    /** @var stdClass the course holding the support forum. */
    private $course;

    /** @var stdClass the support forum. */
    private $forum;

    /** @var stdClass the user asking for support. */
    private $student;

    /** @var stdClass the person supporting the course. */
    private $supporter;

    /**
     * Set up a support forum with a student who may post into it.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Without an explicit limit the spam check compares against an empty setting.
        set_config('spamprotectionthreshold', 60, 'local_helpdesk');
        set_config('spamprotectionlimit', 100, 'local_helpdesk');

        $this->setAdminUser();
        $datagenerator = $this->getDataGenerator();

        $this->course = $datagenerator->create_course();
        $this->forum = $datagenerator->create_module('forum', ['course' => $this->course->id]);
        $datagenerator->get_plugin_generator('local_helpdesk')
            ->create_supportforum(['forumid' => $this->forum->id]);

        $this->student = $datagenerator->create_user();
        $datagenerator->enrol_user($this->student->id, $this->course->id, 'student');

        // Somebody has to support the course, otherwise every request escalates straight to
        // the platform team and none of the first level paths below would be taken.
        $this->supporter = $datagenerator->create_user();
        $datagenerator->enrol_user($this->supporter->id, $this->course->id, 'editingteacher');
        lib::assign_first_level($this->course->id, [$this->supporter->id]);
    }

    /**
     * Call create_issue with the defaults a ticket form would send.
     *
     * @param string $subject
     * @param string $forumgroup
     * @param string $image the screenshot as data URL.
     * @param string $imagename the file name of the screenshot.
     * @return array the reply of the external function.
     */
    private function create_issue(string $subject, string $forumgroup = '', string $image = '', string $imagename = ''): array {
        if ($forumgroup === '') {
            $forumgroup = $this->forum->id . '_0';
        }
        return create_issue::execute(
            $subject,
            'Beschreibung des Problems',
            $forumgroup,
            0,
            $image,
            $imagename,
            'https://example.com/course/view.php?id=' . $this->course->id,
            '',
            null,
            null
        );
    }

    /**
     * A ticket ends up as a discussion in the support forum.
     *
     * It does not yet become a tracked issue: local_helpdesk_issues is only written by
     * set_2nd_level(), so with first level support alone the ticket lives in the forum only
     * and does not appear on issues.php. See the escalation test below.
     */
    public function test_create_issue_creates_a_discussion(): void {
        global $DB;

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertGreaterThan(0, $reply['discussionid']);

        $discussion = $DB->get_record('forum_discussions', ['id' => $reply['discussionid']], '*', MUST_EXIST);
        $this->assertEquals($this->forum->id, $discussion->forum);
        $this->assertSame('Drucker geht nicht', $discussion->name);
        $this->assertEquals($this->student->id, $discussion->userid);

        $this->assertFalse($DB->record_exists('local_helpdesk_issues', ['discussionid' => $reply['discussionid']]));
    }

    /**
     * With groups per person the ticket still belongs to the person who filed it.
     *
     * The supporters sharing the value of the profile field are added to that group, and used
     * to take the place of the author while that happened.
     */
    public function test_group_mode_keeps_the_author(): void {
        global $DB;

        $datagenerator = $this->getDataGenerator();
        $datagenerator->create_custom_profile_field(['shortname' => 'school', 'name' => 'School', 'datatype' => 'text']);
        profile_save_custom_fields($this->student->id, ['school' => 'HTL']);
        profile_save_custom_fields($this->supporter->id, ['school' => 'HTL']);
        set_config('firstlvlgroupmode', 1, 'local_helpdesk');
        set_config('customfieldname', 'School', 'local_helpdesk');
        set_config('rolename', 'editingteacher', 'local_helpdesk');

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $discussion = $DB->get_record('forum_discussions', ['id' => $reply['discussionid']], '*', MUST_EXIST);
        $this->assertEquals($this->student->id, $discussion->userid);

        $groupname = fullname($this->student) . ' (' . $this->student->id . '-coursesupport)';
        $groupid = groups_get_group_by_name($this->course->id, $groupname);
        $this->assertTrue(groups_is_member($groupid, $this->student->id));
        $this->assertTrue(groups_is_member($groupid, $this->supporter->id));
    }

    /**
     * With automatic escalation the ticket becomes a tracked issue.
     */
    public function test_create_issue_registers_a_tracked_issue_when_escalated(): void {
        global $DB;

        set_config('auto2ndlvl', 1, 'local_helpdesk');

        $datagenerator = $this->getDataGenerator();
        $supporter = $datagenerator->create_user();
        $datagenerator->enrol_user($supporter->id, $this->course->id, 'teacher');
        $datagenerator->get_plugin_generator('local_helpdesk')
            ->create_supporter(['userid' => $supporter->id]);

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertGreaterThan(0, $reply['discussionid']);
        $this->assertTrue($DB->record_exists('local_helpdesk_issues', ['discussionid' => $reply['discussionid']]));
    }

    /**
     * The reported contacts are the people assigned to the course, and only those.
     *
     * Being able to edit the course is no longer enough. That used to be the rule, and it is
     * what put an integration account with a site wide manager role in front of the person
     * filing a request, email address included.
     */
    public function test_create_issue_reports_the_assigned_supporters(): void {
        $datagenerator = $this->getDataGenerator();

        $editor = $datagenerator->create_user();
        $datagenerator->enrol_user($editor->id, $this->course->id, 'editingteacher');

        // A member of the platform team, who is second level and not a contact here.
        $platform = $datagenerator->create_user();
        $datagenerator->get_plugin_generator('local_helpdesk')->create_supporter(['userid' => $platform->id]);

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $responsibleids = array_map('intval', array_column($reply['responsibles'], 'userid'));
        $this->assertContains((int) $this->supporter->id, $responsibleids);
        $this->assertNotContains((int) $editor->id, $responsibleids);
        $this->assertNotContains((int) $platform->id, $responsibleids);
    }

    /**
     * Without anybody assigned the request goes straight to the platform team.
     *
     * That is how an organisation without sub units runs: nobody is named per course, so
     * every request is handled centrally.
     */
    public function test_create_issue_escalates_without_a_first_level(): void {
        global $DB;

        $platform = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('local_helpdesk')
            ->create_supporter(['userid' => $platform->id]);
        lib::assign_first_level($this->course->id, []);

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertTrue($DB->record_exists('local_helpdesk_issues', ['discussionid' => $reply['discussionid']]));
        $this->assertEquals(
            $platform->id,
            $DB->get_field('local_helpdesk_issues', 'currentsupporter', ['discussionid' => $reply['discussionid']])
        );
    }

    /**
     * The support contacts can be kept from the person filing the request.
     */
    public function test_support_contacts_can_be_hidden(): void {
        set_config('showresponsibles', 0, 'local_helpdesk');

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertGreaterThan(0, $reply['discussionid']);
        $this->assertSame([], $reply['responsibles']);
    }

    /**
     * Hiding the contacts also keeps them out of the ticket itself.
     */
    public function test_hiding_the_contacts_skips_the_post_naming_them(): void {
        global $DB;

        set_config('showresponsibles', 0, 'local_helpdesk');

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        // Only the request itself, no automatic post listing who is responsible.
        $this->assertSame(1, $DB->count_records('forum_posts', ['discussion' => $reply['discussionid']]));
    }

    /**
     * With the setting on, the ticket carries the post naming the contacts.
     */
    public function test_showing_the_contacts_posts_them_into_the_ticket(): void {
        global $DB;

        set_config('showresponsibles', 1, 'local_helpdesk');

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertSame(2, $DB->count_records('forum_posts', ['discussion' => $reply['discussionid']]));
    }

    /**
     * Without a target forum the request is sent to the site support address instead.
     *
     * The mail itself is left to cron, so that a slow mail server cannot hold up the answer
     * the browser is waiting for. See {@see \local_helpdesk\task\send_mail}.
     */
    public function test_create_issue_falls_back_to_mail(): void {
        $sink = $this->redirectEmails();

        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht', 'mail');

        $this->assertEquals(-999, $reply['discussionid']);
        $this->assertNotEmpty($reply['responsibles']);
        $this->assertSame(0, $sink->count(), 'Nothing may be sent while the web service is still answering.');

        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(send_mail::class));
        $this->runAdhocTasks(send_mail::class);

        $this->assertSame(1, $sink->count());
        $sink->close();
    }

    /**
     * Too many tickets in a row are refused.
     */
    public function test_spam_protection_blocks_a_burst_of_issues(): void {
        set_config('spamprotectionlimit', 1, 'local_helpdesk');

        $this->setUser($this->student);
        $this->create_issue('Erstes Ticket');

        $this->expectException(moodle_exception::class);
        $this->create_issue('Zweites Ticket');
    }

    /**
     * Somebody who is not logged in is counted by address, so dropping the session does not help.
     */
    public function test_spam_protection_survives_a_new_session(): void {
        set_config('spamprotectionlimit', 1, 'local_helpdesk');

        $this->setUser(null);
        \local_helpdesk\local\rate_limiter::register_ticket();
        \core\session\manager::init_empty_session();

        $this->expectException(moodle_exception::class);
        \local_helpdesk\local\rate_limiter::register_ticket();
    }

    /**
     * What comes as a screenshot has to be a picture.
     */
    public function test_a_screenshot_has_to_be_a_picture(): void {
        $this->setUser($this->student);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('screenshot:invalid', 'local_helpdesk'));
        try {
            $this->create_issue('Drucker', '', 'data:image/png;base64,' . base64_encode('<?php echo 1;'), 'shot.php');
        } finally {
            global $DB;
            $this->assertSame(0, $DB->count_records('forum_discussions'));
        }
    }

    /**
     * A picture is attached to the ticket under the extension of what it really is.
     */
    public function test_a_screenshot_is_attached(): void {
        global $DB;

        $this->setUser($this->student);
        // The smallest GIF there is.
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $reply = $this->create_issue('Drucker', '', 'data:image/png;base64,' . base64_encode($gif), 'shot.php');

        $discussion = $DB->get_record('forum_discussions', ['id' => $reply['discussionid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $this->forum->id);
        $files = get_file_storage()->get_area_files(
            \context_module::instance($cm->id)->id,
            'mod_forum',
            'attachment',
            $discussion->firstpost,
            'id',
            false
        );
        $this->assertCount(1, $files);
        $this->assertSame('shot.gif', reset($files)->get_filename());
    }

    /**
     * A ticket can only go into a group of the person filing it.
     */
    public function test_a_foreign_group_is_refused(): void {
        global $DB;

        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['instance' => $this->forum->id]);
        rebuild_course_cache($this->course->id, true);
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        $this->setUser($this->student);
        $this->expectException(moodle_exception::class);
        $this->create_issue('Drucker', $this->forum->id . '_' . $group->id);
    }

    /**
     * The people looking after a ticket are named, their mail addresses are not handed out.
     */
    public function test_no_mail_addresses_of_supporters(): void {
        $this->setUser($this->student);
        $reply = $this->create_issue('Drucker geht nicht');

        $this->assertNotEmpty($reply['responsibles']);
        $this->assertSame([''], array_unique(array_column($reply['responsibles'], 'email')));
    }
}
