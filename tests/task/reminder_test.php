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
 * Tests for the reminder sent to the supporter of a waiting issue.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\task;

use advanced_testcase;
use local_helpdesk\lib;

/**
 * Tests for the reminder sent to the supporter of a waiting issue.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\task\reminder
 */
final class reminder_test extends advanced_testcase {
    /**
     * An issue with a supporter, waiting for the support team.
     *
     * @return \stdClass the issue.
     */
    private function create_waiting_issue(): \stdClass {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('sendreminders', 1, 'local_helpdesk');
        set_config('timebeforereminder', DAYSECS, 'local_helpdesk');

        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('local_helpdesk');
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $plugingenerator->create_supportforum(['forumid' => $forum->id]);
        $supporter = $generator->create_user(['email' => 'supportteam@example.com']);
        $plugingenerator->create_supporter(['userid' => $supporter->id]);
        $issue = $plugingenerator->create_issue([
            'forumid' => $forum->id,
            'userid' => $generator->create_user()->id,
            'subject' => 'Drucker geht nicht',
            'currentsupporter' => $supporter->id,
        ]);
        $DB->set_field('local_helpdesk_issues', 'status', lib::STATUS_AWAITING_SUPPORT_ACTION, ['id' => $issue->id]);
        return $issue;
    }

    /**
     * Run a reminder for an issue right away.
     *
     * @param int $issueid
     * @return \stdClass[] the mails that went out.
     */
    private function remind(int $issueid): array {
        $sink = $this->redirectEmails();
        $task = new reminder();
        $task->set_custom_data(['issueid' => $issueid]);
        $task->execute();
        $messages = $sink->get_messages();
        $sink->close();
        return $messages;
    }

    /**
     * The supporter of a waiting issue gets a mail naming it.
     */
    public function test_the_supporter_is_reminded(): void {
        $issue = $this->create_waiting_issue();

        $messages = $this->remind($issue->id);

        $this->assertCount(1, $messages);
        $this->assertSame('supportteam@example.com', $messages[0]->to);
        $this->assertStringContainsString('Drucker geht nicht', quoted_printable_decode($messages[0]->body));
    }

    /**
     * Nobody is reminded of an issue that is not waiting for the support team any more, or when reminders are off.
     */
    public function test_no_reminder_without_a_reason(): void {
        global $DB;
        $issue = $this->create_waiting_issue();

        $DB->set_field('local_helpdesk_issues', 'status', lib::STATUS_CLOSED, ['id' => $issue->id]);
        $this->assertCount(0, $this->remind($issue->id));

        $DB->set_field('local_helpdesk_issues', 'status', lib::STATUS_AWAITING_SUPPORT_ACTION, ['id' => $issue->id]);
        set_config('sendreminders', 0, 'local_helpdesk');
        $this->assertCount(0, $this->remind($issue->id));

        set_config('sendreminders', 1, 'local_helpdesk');
        $this->assertCount(0, $this->remind($issue->id + 1000), 'There is no such issue.');
    }

    /**
     * One reminder per issue is queued, however often it is asked for.
     */
    public function test_a_reminder_is_queued_once(): void {
        $issue = $this->create_waiting_issue();
        foreach (\core\task\manager::get_adhoc_tasks(reminder::class) as $task) {
            \core\task\manager::adhoc_task_complete($task);
        }

        $this->assertFalse(lib::issue_already_has_reminder($issue->id));
        lib::send_reminder($issue->id);
        lib::send_reminder($issue->id);

        $this->assertTrue(lib::issue_already_has_reminder($issue->id));
        $this->assertFalse(lib::issue_already_has_reminder($issue->id + 1));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(reminder::class));
    }
}
