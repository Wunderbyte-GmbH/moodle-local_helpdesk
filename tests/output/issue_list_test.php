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
 * Tests for the list of issues.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\output;

use advanced_testcase;
use local_helpdesk\lib;

/**
 * Tests for the list of issues.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\output\issue_list
 */
final class issue_list_test extends advanced_testcase {
    /**
     * Issues are sorted into the groups of the page, with the names the page shows.
     */
    public function test_issues_are_grouped_and_named(): void {
        global $DB, $PAGE;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('local_helpdesk');

        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $plugingenerator->create_supportforum(['forumid' => $forum->id]);
        $asking = $generator->create_user(['firstname' => 'Sally', 'lastname' => 'Student']);
        $me = $generator->create_user(['firstname' => 'Sam', 'lastname' => 'Support']);
        $colleague = $generator->create_user(['firstname' => 'Paula', 'lastname' => 'Platform']);
        $plugingenerator->create_supporter(['userid' => $me->id]);
        $plugingenerator->create_supporter(['userid' => $colleague->id]);

        $issue = function (string $subject, array $more = []) use ($plugingenerator, $forum, $asking) {
            $record = ['forumid' => $forum->id, 'userid' => $asking->id, 'subject' => $subject];
            return $plugingenerator->create_issue($record + $more);
        };
        $mine = $issue('Mine', ['currentsupporter' => $me->id]);
        $watched = $issue('Watched', ['currentsupporter' => $colleague->id]);
        $DB->insert_record('local_helpdesk_subscr', (object) [
            'issueid' => $watched->id, 'discussionid' => $watched->discussionid, 'userid' => $me->id,
        ]);
        $issue('Other', ['currentsupporter' => $colleague->id]);
        $closed = $issue('Closed', ['currentsupporter' => $colleague->id]);
        $DB->set_field('local_helpdesk_issues', 'status', lib::STATUS_CLOSED, ['id' => $closed->id]);
        $orphan = $issue('Orphan');
        $DB->delete_records('forum_discussions', ['id' => $orphan->discussionid]);

        // The colleague answers, so the last post is not the one of the person asking.
        $generator->get_plugin_generator('mod_forum')->create_post([
            'discussion' => $mine->discussionid, 'userid' => $colleague->id, 'modified' => time() + 10,
        ]);

        $reads = $DB->perf_get_reads();
        $data = (new issue_list($me->id))->export_for_template($PAGE->get_renderer('core'));
        $this->assertLessThan(12, $DB->perf_get_reads() - $reads, 'The number of queries must not grow with the issues.');

        $this->assertSame(['current' => 1, 'assigned' => 1, 'other' => 1, 'closed' => 1], $data['count']);
        $this->assertSame('Mine', $data['current'][0]->name);
        $this->assertSame('Watched', $data['assigned'][0]->name);
        $this->assertSame('Other', $data['other'][0]->name);
        $this->assertStringContainsString('Closed', $data['closed'][0]->name);

        $this->assertSame(fullname($asking), $data['current'][0]->userfullname);
        $this->assertSame(fullname($colleague), $data['current'][0]->lastpostuserfullname);
        $this->assertSame(fullname($me), $data['current'][0]->currentsupportername);
        $this->assertSame(fullname($asking), $data['other'][0]->lastpostuserfullname);
    }
}
