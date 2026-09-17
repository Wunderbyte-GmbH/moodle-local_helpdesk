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
 * Tests for what the plugin does while a page is built.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;

/**
 * Tests for what the plugin does while a page is built.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\hook_callbacks
 */
final class hook_callbacks_test extends advanced_testcase {
    /**
     * Let the callback see a page.
     *
     * @param string $pagetype
     * @param array $get the parameters of the request.
     * @return void
     */
    private function visit(string $pagetype, array $get): void {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_url('/');
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_pagetype($pagetype);
        $_GET = $get;
        hook_callbacks::before_standard_head_html_generation(
            new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'))
        );
        $_GET = [];
    }

    /**
     * Looking at a hidden category leaves it hidden, unless it holds a support forum.
     */
    public function test_only_categories_with_a_support_forum_are_kept_visible(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $private = $generator->create_category(['visible' => 0]);
        $support = $generator->create_category(['visible' => 0]);
        $course = $generator->create_course(['category' => $support->id]);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('local_helpdesk')->create_supportforum(['forumid' => $forum->id]);

        $this->visit('course-management', ['categoryid' => $private->id]);
        $this->visit('course-management', ['categoryid' => $support->id]);

        $this->assertEquals(0, $DB->get_field('course_categories', 'visible', ['id' => $private->id]));
        $this->assertEquals(1, $DB->get_field('course_categories', 'visible', ['id' => $support->id]));
    }

    /**
     * A support course that was moved is looked up under its new category from then on.
     */
    public function test_a_moved_support_course_is_followed(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $from = $generator->create_category();
        $to = $generator->create_category();
        $course = $generator->create_course(['category' => $from->id]);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('local_helpdesk')->create_supportforum(['forumid' => $forum->id]);
        $this->assertEquals($from->id, $DB->get_field('local_helpdesk', 'categoryid', ['forumid' => $forum->id]));

        move_courses([$course->id], $to->id);
        $this->visit('course-management', ['categoryid' => $from->id]);

        $this->assertEquals($to->id, $DB->get_field('local_helpdesk', 'categoryid', ['forumid' => $forum->id]));
    }

    /**
     * A discussion that does not exist is for the forum to complain about, not for us to stumble over.
     */
    public function test_a_missing_discussion_does_no_harm(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->visit('mod-forum-discuss', ['d' => 987654]);
        $this->visit('mod-forum-discuss', []);

        $this->assertDebuggingNotCalled();
    }
}
