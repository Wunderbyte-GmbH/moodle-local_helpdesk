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
 * Tests for the cleanup of issues that were closed long ago.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\task;

use advanced_testcase;

/**
 * Tests for the cleanup of issues that were closed long ago.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\task\delete
 */
final class delete_test extends advanced_testcase {
    /**
     * Only issues taken off the list long enough ago go; their discussions stay.
     */
    public function test_expired_issues_are_deleted(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('deletethreshhold', WEEKSECS, 'local_helpdesk');

        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('local_helpdesk');
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $plugingenerator->create_supportforum(['forumid' => $forum->id]);

        $create = function (string $subject, int $priority, int $age) use ($plugingenerator, $forum, $DB) {
            $issue = $plugingenerator->create_issue(['forumid' => $forum->id, 'subject' => $subject, 'priority' => $priority]);
            $DB->set_field('forum_discussions', 'timemodified', time() - $age, ['id' => $issue->discussionid]);
            return $issue;
        };
        $old = $create('Closed long ago', 0, 2 * WEEKSECS);
        $recent = $create('Closed yesterday', 0, DAYSECS);
        $open = $create('Still open', 1, 2 * WEEKSECS);

        (new delete())->execute();

        $this->assertFalse($DB->record_exists('local_helpdesk_issues', ['id' => $old->id]));
        $this->assertTrue($DB->record_exists('forum_discussions', ['id' => $old->discussionid]));
        $this->assertTrue($DB->record_exists('local_helpdesk_issues', ['id' => $recent->id]));
        $this->assertTrue($DB->record_exists('local_helpdesk_issues', ['id' => $open->id]));
    }
}
