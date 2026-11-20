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
 * The forums that serve as support forums: registering them, their protection and their roles.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\supportforum;

use local_helpdesk\guest_supportuser;
use local_helpdesk\local\supporter\course_support;
use local_helpdesk\local\supporter\platform_team;
use stdClass;

/**
 * The forums that serve as support forums: registering them, their protection and their roles.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /**
     * Checks for groupmode in a forum and lists available groups of this user.
     *
     * @param int $forumid the forum.
     * @return array of groups.
     */
    public static function get_groups_for_user(int $forumid): array {
        // Store rating if we are permitted to.
        global $CFG, $DB, $USER;
        $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
        if ((empty($USER->id) || isguestuser()) && !$guestmode) {
            return [];
        }
        if ($guestmode && (!isloggedin() || isguestuser())) {
            $supportuser = new guest_supportuser();
            $user = $supportuser->get_support_guestuser();
        } else {
            $user = $USER;
        }

        $forum = $DB->get_record('forum', ['id' => $forumid]);
        $course = $DB->get_record('course', ['id' => $forum->course]);

        $cm = \get_coursemodule_from_instance('forum', $forumid);

        $groupmode = \groups_get_activity_groupmode($cm);
        // If we do not use groups in this forum, return without groups.
        if (empty($groupmode)) {
            return [];
        }

        // We do not use the function groups_get_user_groups, as it does not
        // return groups that don't have members!!
        $groupsfromdb = $DB->get_records('groups', ['courseid' => $course->id]);
        if (count($groupsfromdb) == 0) {
            return [];
        }

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $groups = [];
        foreach ($groupsfromdb as $k => $group) {
            $ismember = $DB->get_record('groups_members', ['groupid' => $group->id, 'userid' => $user->id]);
            if (!empty($ismember->id)) {
                // Only allow the group generated for the user.
                if ($group->name == fullname($user) . ' (' . $user->id . ')') {
                    $groups[$k] = $group;
                }
            }
        }
        return $groups;
    }

    /**
     * Get potential targets of a user.
     *
     * @param int $userid if empty will use current user.
     * @return array containing forums and their possible groups.
     */
    public static function get_potential_targets(int $userid = 0): array {
        global $DB, $USER;
        $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
        if (empty($userid)) {
            if ($guestmode && (isguestuser() || !isloggedin())) {
                $guestuser = new guest_supportuser();
                $guestsupportuser = $guestuser->get_support_guestuser();
                $userid = $guestsupportuser->id;
            } else {
                $userid = $USER->id;
            }
        }

        $forums = [];
        $courseids = array_keys(enrol_get_all_users_courses($userid));
        if (!empty($courseids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids);
            $sql = " SELECT f.id,f.name,f.course
                        FROM {local_helpdesk} be, {forum} f, {course} c
                        WHERE f.course=c.id
                            AND be.forumid=f.id
                            AND c.id $insql
                        ORDER BY c.fullname ASC, f.name ASC";
            $forumsfromdb = $DB->get_records_sql($sql, $inparams);
            $delimiter = ' > ';
            foreach ($forumsfromdb as &$forum) {
                $course = $DB->get_record('course', ['id' => $forum->course], 'id,fullname');
                $coursecontext = \context_course::instance($forum->course);
                if (empty($coursecontext->id)) {
                    continue;
                }

                $fcm = get_coursemodule_from_instance('forum', $forum->id, 0, false, MUST_EXIST);
                $fctx = \context_module::instance($fcm->id);
                $modinfo = get_fast_modinfo($course);
                $cm = $modinfo->get_cm($fcm->id);

                if ($cm->uservisible && has_capability('mod/forum:startdiscussion', $fctx, $userid)) {
                    $forum->name = $course->fullname . $delimiter . $forum->name;
                    $forum->postto2ndlevel = has_capability('local/helpdesk:canforward2ndlevel', $coursecontext, $userid);
                    $forum->potentialgroups = self::get_groups_for_user($forum->id);
                    $forums[$forum->id] = $forum;
                }
            }
        }
        return $forums;
    }

    /**
     * Checks if a given forum is used as support-forum.
     *
     * @param int $forumid the forum.
     * @return bool
     */
    public static function is_supportforum(int $forumid): bool {
        global $DB;
        $chk = $DB->get_record('local_helpdesk', ['forumid' => $forumid]);
        return !empty($chk->id);
    }

    /**
     * Unregister the support forums of a course that is about to be deleted.
     *
     * @param int $courseid
     * @return void
     */
    public static function disable_in_course(int $courseid): void {
        global $DB;
        foreach ($DB->get_fieldset('local_helpdesk', 'forumid', ['courseid' => $courseid]) as $forumid) {
            self::disable($forumid);
        }
    }

    /**
     * Unregister the support forums of all courses of a category that is about to be deleted.
     *
     * @param int $categoryid
     * @return void
     */
    public static function disable_in_category(int $categoryid): void {
        global $DB;
        $courses = $DB->get_recordset('course', ['category' => $categoryid], '', 'id');
        foreach ($courses as $course) {
            self::disable_in_course($course->id);
        }
        $courses->close();
    }

    /**
     * Unregister a forum whose course module is about to be deleted.
     *
     * @param stdClass $cm the course_modules record.
     * @return void
     */
    public static function disable_for_course_module(stdClass $cm): void {
        global $DB;
        $forumtype = $DB->get_field('modules', 'id', ['name' => 'forum']);
        if ($forumtype && !empty($cm->module) && $cm->module == $forumtype) {
            self::disable($cm->instance);
        }
    }

    /**
     * Removes a forum as potential supportforum.
     *
     * @param int $forumid the forum.
     * @return void
     */
    public static function disable(int $forumid): void {
        global $DB;
        $DB->delete_records('local_helpdesk', ['forumid' => $forumid]);
        self::set_protection($forumid, false);
        self::sync_roles($forumid);
        $centralforum = get_config('local_helpdesk', 'centralforum');
        if ($forumid == $centralforum) {
            self::disable_central();
        }
        // TODO MDL-000000 shall we check for orphaned discussions too?
    }

    /**
     * Removes a forum as central support-forum.
     * @return void
     */
    public static function disable_central(): void {
        if (!is_siteadmin()) {
            return;
        }
        set_config('centralforum', 0, 'local_helpdesk');
    }

    /**
     * Sets a forum as possible support-forum.
     *
     * @param int $forumid the forum.
     * @return stdClass|null the support forum record, null if the forum does not exist.
     */
    public static function enable(int $forumid): ?stdClass {
        global $DB;
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return null;
        }

        $supportforum = $DB->get_record('local_helpdesk', ['forumid' => $forumid]);
        if (empty($supportforum->id)) {
            $course = $DB->get_record('course', ['id' => $forum->course]);
            $supportforum = (object) [
                'categoryid' => $course->category,
                'courseid' => $forum->course,
                'forumid' => $forum->id,
                'dedicatedsupporter' => 0,
            ];
            $supportforum->id = $DB->insert_record('local_helpdesk', $supportforum);
        }

        self::set_protection($forumid, true);
        self::sync_roles($forumid);
        return $supportforum;
    }

    /**
     * Sets a forum as central support-forum.
     *
     * @param int $forumid the forum.
     * @return bool true if the forum is the central support forum now.
     */
    public static function enable_central(int $forumid): bool {
        global $DB;
        if (!is_siteadmin()) {
            return false;
        }
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return false;
        }

        $supportforum = $DB->get_record('local_helpdesk', ['forumid' => $forumid]);
        if (!empty($supportforum->id)) {
            set_config('centralforum', $forum->id, 'local_helpdesk');
            return true;
        }
        return false;
    }

    /**
     * Protect a support forum and its course from being changed or deleted, or lift that again.
     *
     * The capabilities are prohibited for the default role of authenticated users
     * ($CFG->defaultuserroleid). Every logged in person has that role and a prohibition cannot
     * be overridden further down, so while the forum is a support forum nobody but site
     * administrators can remove or hide it or change the course around it.
     *
     * The role is read when the forum is enabled and again when it is disabled. If an
     * administrator changes the default role in between, the prohibitions stay on the old role
     * and have to be removed there by hand.
     *
     * @param int $forumid
     * @param bool $trigger true if we enable the forum, false if we disable it.
     * @return bool false if there is no forum, course or default role to work with.
     */
    public static function set_protection(int $forumid, bool $trigger): bool {
        global $CFG, $DB;

        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course) || empty($CFG->defaultuserroleid)) {
            return false;
        }

        $cm = \get_coursemodule_from_instance('forum', $forumid, 0, false, MUST_EXIST);
        $ctxmod = \context_module::instance($cm->id);
        $ctxcourse = \context_course::instance($forum->course);

        // Each capability with the context its prohibition is set in.
        $capabilities = [
            'moodle/course:activityvisibility' => $ctxmod,
            'moodle/course:changecategory' => $ctxcourse,
            'moodle/course:changefullname' => $ctxcourse,
            'moodle/course:changeidnumber' => $ctxcourse,
            'moodle/course:changeshortname' => $ctxcourse,
            'moodle/course:enrolconfig' => $ctxcourse,
            'moodle/course:manageactivities' => $ctxmod,
            'moodle/course:delete' => $ctxcourse,
            'moodle/course:reset' => $ctxcourse,
            'moodle/course:visibility' => $ctxcourse,
            'moodle/restore:configure' => $ctxcourse,
            'moodle/restore:restorecourse' => $ctxcourse,
            'moodle/restore:restoresection' => $ctxcourse,
            'moodle/restore:viewautomatedfilearea' => $ctxcourse,
        ];
        $permission = ($trigger) ? CAP_PROHIBIT : CAP_INHERIT;
        foreach ($capabilities as $capability => $context) {
            \role_change_permission($CFG->defaultuserroleid, $context, $capability, $permission);
        }
        return true;
    }

    /**
     * Checks for a forum, if all supportteam-members have the required role.
     *
     * @param int $forumid the forum to check, 0 for all support forums.
     * @return void
     */
    public static function sync_roles(int $forumid = 0): void {
        global $DB;
        if (empty($forumid)) {
            // We have to re-sync all supportforums.
            $forums = $DB->get_records('local_helpdesk', []);
            foreach ($forums as $forum) {
                self::sync_roles($forum->forumid);
            }
        } else {
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING);
            if (empty($forum->id)) {
                return;
            }
            $issupportforum = self::is_supportforum($forumid);

            $cm = \get_coursemodule_from_instance('forum', $forumid, $forum->course, false, MUST_EXIST);
            $ctx = \context_module::instance($cm->id);

            $roleid = get_config('local_helpdesk', 'supportteamrole');

            // Get all users that currently have the supporter-role.
            $sql = "SELECT userid FROM {role_assignments} WHERE roleid=? AND contextid=?";
            $curmembers = array_keys($DB->get_records_sql($sql, [$roleid, $ctx->id]));
            foreach ($curmembers as $curmember) {
                $unassign = false;
                if (!$issupportforum) {
                    $unassign = true;
                } else {
                    // Must mirror the assignment query below, or every run would hand the
                    // role out and take it away again.
                    $issupporter = platform_team::is_member($curmember)
                        || course_support::is_member($curmember, $forum->course);
                    $unassign = !$issupporter;
                }
                if ($unassign) {
                    role_unassign($roleid, $curmember, $ctx->id);
                }
            }

            if ($issupportforum) {
                // Assign all current supportteam users.
                $sql = "SELECT les.*
                        FROM {local_helpdesk_supporters} les
                        JOIN {user} u
                        ON u.id = les.userid
                        WHERE (les.courseid=? OR les.courseid=?)
                        AND u.deleted != 1";
                $params = [platform_team::COURSE_ID, $forum->course];
                $members = $DB->get_records_sql($sql, $params);
                foreach ($members as $member) {
                    role_assign($roleid, $member->userid, $ctx->id);
                }
            }
        }
    }

    /**
     * Set the dedicated supporter for a particular forum.
     *
     * @param int $forumid the support forum.
     * @param int $userid the dedicated supporter, -1 for none.
     * @return bool true if the dedicated supporter was set.
     */
    public static function set_dedicated_supporter(int $forumid, int $userid): bool {
        if (!self::is_supportforum($forumid)) {
            return false;
        }
        global $DB;
        if ($userid == -1) {
            $DB->set_field('local_helpdesk', 'dedicatedsupporter', -1, ['forumid' => $forumid]);
        } else {
            if (!platform_team::is_member($userid)) {
                return false;
            }
            $DB->set_field('local_helpdesk', 'dedicatedsupporter', $userid, ['forumid' => $forumid]);
        }
        return true;
    }
}
