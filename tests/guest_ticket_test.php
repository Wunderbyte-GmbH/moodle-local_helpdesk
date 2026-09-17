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
 * Tests for requests filed without logging in.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;

/**
 * Tests for requests filed without logging in.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\external\create_issue
 */
final class guest_ticket_test extends advanced_testcase {
    /**
     * A guest files a request: it belongs to the guest account, answers go to the address given, and nobody is named.
     */
    public function test_a_guest_files_a_request(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('guestmodeenabled', 1, 'local_helpdesk');
        set_config('showresponsibles', 1, 'local_helpdesk');
        set_config('spamprotectionthreshold', 60, 'local_helpdesk');
        set_config('spamprotectionlimit', 100, 'local_helpdesk');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('local_helpdesk')->create_supportforum(['forumid' => $forum->id, 'central' => 1]);

        // Nobody is logged in. Opening the form is what lets the guest account into the support course.
        $this->setUser(null);
        lib::before_popup();
        $reply = external\create_issue::execute(
            'Anmeldung klappt nicht',
            'Ich komme nicht hinein.',
            $forum->id . '_0',
            0,
            '',
            '',
            'https://example.com/login/index.php',
            '',
            'fragende@example.com',
            null
        );

        $this->assertGreaterThan(0, $reply['discussionid']);
        $this->assertSame([], $reply['responsibles'], 'Whoever is not logged in is not told who supports the course.');

        $guest = (new guest_supportuser())->get_support_guestuser();
        $discussion = $DB->get_record('forum_discussions', ['id' => $reply['discussionid']], '*', MUST_EXIST);
        $this->assertEquals($guest->id, $discussion->userid);
        $this->assertStringContainsString('[Guestticket: fragende@example.com]', $discussion->name);
        $this->assertSame('fragende@example.com', local\guest_ticket::get_email($discussion->id));
    }

    /**
     * Without the guest mode nothing can be filed without logging in.
     */
    public function test_no_guest_requests_without_the_guest_mode(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('guestmodeenabled', 0, 'local_helpdesk');
        set_config('spamprotectionthreshold', 60, 'local_helpdesk');
        set_config('spamprotectionlimit', 100, 'local_helpdesk');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('local_helpdesk')->create_supportforum(['forumid' => $forum->id, 'central' => 1]);

        $before = $DB->count_records('forum_discussions');

        $this->setUser(null);
        $target = $forum->id . '_0';
        $reply = external\create_issue::execute('Hallo', 'Ich komme nicht hinein.', $target, 0, '', '', '', '', 'x@example.com');

        $this->assertSame(-1, $reply['discussionid']);
        $this->assertSame($before, $DB->count_records('forum_discussions'));
    }
}
