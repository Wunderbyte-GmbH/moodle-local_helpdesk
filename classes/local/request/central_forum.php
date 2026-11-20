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
 * Prepares a person for filing a request in the central support forum.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\request;

use local_helpdesk\guest_supportuser;
use local_helpdesk\local\supportforum\registry;
use stdClass;

/**
 * Prepares a person for filing a request in the central support forum.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class central_forum {
    /**
     * Perform some actions before the popup is rendered.
     * @return void
     */
    public static function prepare_for_request(): void {
        global $CFG, $DB, $USER;
        $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
        if ($guestmode && (isguestuser() || !isloggedin())) {
            $guestuser = new guest_supportuser();
            $user = $guestuser->get_support_guestuser();
        } else {
            $user = $USER;
        }
        $centralforum = get_config('local_helpdesk', 'centralforum');
        if (!empty($centralforum) && registry::is_supportforum($centralforum)) {
            $forum = $DB->get_record('forum', ['id' => $centralforum]);
            $coursectx = \context_course::instance($forum->course);
            if (!empty($coursectx->id)) {
                if (!is_enrolled($coursectx, $user, '', true)) {
                    // Enrol with the role of a student.
                    $studentroles = get_archetype_roles('student');
                    $studentrole = reset($studentroles);
                    if ($studentrole) {
                        self::enrol_manually([$forum->course], [$user->id], $studentrole->id);
                    }
                }
                require_once("$CFG->dirroot/group/lib.php");
                $groupname = fullname($user) . ' (' . $user->id . ')';
                $group = $DB->get_record('groups', ['courseid' => $forum->course, 'name' => $groupname]);
                if (empty($group->id)) {
                    // Create a group for this user.
                    $group = (object) [
                        'courseid' => $forum->course,
                        'name' => $groupname,
                        'description' => '',
                        'descriptionformat' => 1,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ];
                    $group->id = groups_create_group($group, false);
                }
                if (!empty($group->id)) {
                    groups_add_member($group->id, $user);
                }
            }
        }
    }

    /**
     * Enrols users to specific courses
     *
     * @param array $courseids containing courseids or a single courseid
     * @param array $userids containing userids or a single userid
     * @param int $roleid roleid to assign, or -1 if wants to unenrolroleid to assign, or -1 if wants to unenrol
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function enrol_manually(array $courseids, array $userids, int $roleid): bool {
        global $DB;
        // Check manual enrolment plugin instance is enabled/exist.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }
        $failures = 0;
        $instances = [];
        foreach ($courseids as $courseid) {
            // Check if course exists.
            $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
            if (empty($course->id)) {
                continue;
            }
            if (empty($instances[$courseid])) {
                $instances[$courseid] = self::get_enrol_instance($courseid);
            }
            if (empty($instances[$courseid])) {
                // The course takes no manual enrolments, and none could be set up.
                $failures++;
                continue;
            }

            foreach ($userids as $userid) {
                $user = $DB->get_record('user', ['id' => $userid]);
                if (empty($user->id)) {
                    continue;
                }
                if ($roleid == -1) {
                    $enrol->unenrol_user($instances[$courseid], $userid);
                } else {
                    $enrol->enrol_user($instances[$courseid], $userid, $roleid, time(), 0, ENROL_USER_ACTIVE);
                }
            }
        }
        return ($failures == 0);
    }

    /**
     * Get the enrol instance for manual enrolments of a course, or create one.
     *
     * @param int $courseid the course.
     * @return stdClass|null the instance, null if the course has none and none could be added.
     */
    private static function get_enrol_instance(int $courseid): ?stdClass {
        // Check manual enrolment plugin instance is enabled/exist.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }
        // Look twice at most: the second time after an instance was added.
        foreach ([false, true] as $added) {
            foreach (enrol_get_instances($courseid, false) as $courseenrolinstance) {
                if ($courseenrolinstance->enrol == "manual") {
                    return $courseenrolinstance;
                }
            }
            if (!$added) {
                $enrol->add_default_instance(get_course($courseid));
            }
        }
        return null;
    }
}
