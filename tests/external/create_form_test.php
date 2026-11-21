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
 * Tests for the web service that delivers the support form.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\external;

use advanced_testcase;

/**
 * Tests for the web service that delivers the support form.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\external\create_form
 * @covers     \local_helpdesk\form\issue_create_form
 */
final class create_form_test extends advanced_testcase {
    /**
     * A user gets the form with the fields the support dialogue fills in.
     *
     * @return void
     */
    public function test_form_is_rendered(): void {
        $this->resetAfterTest(true);
        set_config('faqread', 1, 'local_helpdesk');

        $datagenerator = $this->getDataGenerator();
        $course = $datagenerator->create_course();
        $forum = $datagenerator->create_module('forum', ['course' => $course->id]);
        $datagenerator->get_plugin_generator('local_helpdesk')->create_supportforum(['forumid' => $forum->id]);
        $user = $datagenerator->create_user();
        $datagenerator->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $html = create_form::execute('https://example.com/course/view.php', '', 0);

        $this->assertStringContainsString('id="local_helpdesk_create_form"', $html);
        $this->assertStringContainsString('name="faqread"', $html);
        $this->assertStringContainsString('name="subject"', $html);
    }
}
