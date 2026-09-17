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
 * Tests for the account managers.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;

/**
 * Tests for the account managers.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_helpdesk\accountmanager
 */
final class accountmanager_test extends advanced_testcase {
    /**
     * People who may create courses, and administrators, can be made account managers.
     */
    public function test_who_can_be_an_account_manager(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $creator = $generator->create_user(['firstname' => 'Carla', 'lastname' => 'Creator']);
        $student = $generator->create_user();
        $category = $generator->create_category();
        $generator->role_assign('coursecreator', $creator->id, \context_coursecat::instance($category->id)->id);

        $possible = accountmanager::get_all_category_managers_from_site();

        $this->assertSame('Carla Creator', $possible[$creator->id]);
        $this->assertArrayHasKey(get_admin()->id, $possible);
        $this->assertArrayNotHasKey($student->id, $possible);
    }

    /**
     * Whoever holds one of the chosen capabilities somewhere may pick an account manager.
     */
    public function test_who_can_choose_an_account_manager(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $accountmanager = new accountmanager();

        $this->setUser($teacher);
        $this->assertFalse($accountmanager->can_choose_accountmanager(), 'Nothing is configured yet.');

        $accountmanager->form_to_config_helpdesk_accountmanager([get_admin()->id], ['moodle/course:manageactivities']);
        $this->assertTrue($accountmanager->can_choose_accountmanager());

        $this->setUser($student);
        $this->assertFalse($accountmanager->can_choose_accountmanager());
    }

    /**
     * A deleted user is taken off the list, the others stay.
     */
    public function test_delete_account_manager(): void {
        $this->resetAfterTest(true);
        set_config('accountmanagers', '3,14,15', 'local_helpdesk');

        accountmanager::delete_account_manager(14);
        accountmanager::delete_account_manager(92);

        $this->assertSame('3,15', get_config('local_helpdesk', 'accountmanagers'));
    }
}
