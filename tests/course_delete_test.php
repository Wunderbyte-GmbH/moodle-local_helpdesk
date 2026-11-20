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
 * Tests that deleting a course takes its support forums out of the helpdesk.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;

/**
 * Tests that deleting a course takes its support forums out of the helpdesk.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_helpdesk_pre_course_delete
 */
final class course_delete_test extends advanced_testcase {
    /**
     * The support forum of the deleted course is unregistered, the one of another course stays.
     *
     * @return void
     */
    public function test_deleting_a_course_unregisters_its_support_forum(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $helpdesk = $generator->get_plugin_generator('local_helpdesk');

        $keptcourse = $generator->create_course();
        // A few forums that are no support forums, so that forum ids and registry ids differ.
        for ($i = 0; $i < 3; $i++) {
            $generator->create_module('forum', ['course' => $keptcourse->id]);
        }
        $keptforum = $generator->create_module('forum', ['course' => $keptcourse->id]);
        $helpdesk->create_supportforum(['forumid' => $keptforum->id]);

        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $supportforum = $helpdesk->create_supportforum(['forumid' => $forum->id]);
        $this->assertNotEquals($supportforum->id, $supportforum->forumid);

        delete_course($course, false);

        $this->assertFalse($DB->record_exists('local_helpdesk', ['forumid' => $forum->id]));
        $this->assertTrue($DB->record_exists('local_helpdesk', ['forumid' => $keptforum->id]));
    }
}
