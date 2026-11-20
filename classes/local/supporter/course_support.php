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
 * The first level support of a course: the people assigned to answer its requests.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\supporter;

use local_helpdesk\local\supportforum\registry;
use stdClass;

/**
 * The first level support of a course: the people assigned to answer its requests.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_support {
    /**
     * Get the first level support of a single course.
     *
     * @param int $courseid
     * @return array of supporter records, keyed by row id.
     */
    public static function get_members(int $courseid): array {
        global $DB;

        if ($courseid == platform_team::COURSE_ID) {
            // That id is the sentinel for the platform team, it is never a course.
            return [];
        }

        return $DB->get_records('local_helpdesk_supporters', ['courseid' => $courseid]);
    }

    /**
     * Check whether a user is first level support of a particular course.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function is_member(int $userid, int $courseid): bool {
        global $DB;

        if ($courseid == platform_team::COURSE_ID) {
            return false;
        }

        return $DB->record_exists('local_helpdesk_supporters', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
    }

    /**
     * Get the users of a course that may be made first level support.
     *
     * Eligible is whoever is enrolled and can both start a discussion in a forum and see
     * hidden activities. That pair is what separates teaching staff from students: a student
     * may start discussions too, but cannot see hidden activities.
     *
     * The two capabilities cannot be handed to one get_enrolled_users() call, because a list
     * of capabilities there means "one of these is enough" (see get_with_capability_join() in
     * lib/accesslib.php). The two sets are intersected in a single query instead.
     *
     * @param int $courseid
     * @param string $search optional name or identity search for an autocomplete.
     * @param int $limit optional maximum number of users to return.
     * @return array of user records, keyed by user id.
     */
    public static function get_assignable_users(int $courseid, string $search = '', int $limit = 0): array {
        global $DB;

        $context = \context_course::instance($courseid);
        [$postsql, $postparams] = get_enrolled_sql($context, 'mod/forum:startdiscussion', 0, true);
        [$staffsql, $staffparams] = get_enrolled_sql($context, 'moodle/course:viewhiddenactivities', 0, true);

        $wheres = ["u.id IN ($postsql)", "u.id IN ($staffsql)", 'u.deleted = 0'];
        $params = array_merge($postparams, $staffparams);

        if ($search !== '') {
            [$searchsql, $searchparams] = users_search_sql($search, 'u', USER_SEARCH_CONTAINS);
            $wheres[] = $searchsql;
            $params = array_merge($params, $searchparams);
        }

        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        $sql = "SELECT u.id, u.email $namefields
                  FROM {user} u
                 WHERE " . implode(' AND ', $wheres) . "
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Re-sync the support role in the forums of a single course.
     *
     * registry::sync_roles() without a forum walks every support forum on the site, which
     * on a platform with many of them is far too slow to sit inside a request. Assigning
     * somebody in one course only ever affects that course's forums.
     *
     * @param int $courseid
     * @return void
     */
    public static function sync_roles(int $courseid): void {
        global $DB;

        $forums = $DB->get_records('local_helpdesk', ['courseid' => $courseid], '', 'id, forumid');
        foreach ($forums as $forum) {
            registry::sync_roles($forum->forumid);
        }
    }

    /**
     * Check whether somebody may assign the first level support of a course.
     *
     * @param int $courseid
     * @param int $userid check a particular user, or the current one.
     * @return bool
     */
    public static function can_assign(int $courseid, int $userid = 0): bool {
        global $USER;

        $userid = empty($userid) ? $USER->id : $userid;

        return has_capability(
            'local/helpdesk:assignsupporters',
            \context_course::instance($courseid),
            $userid
        );
    }

    /**
     * Set the first level support of a course to exactly the given users.
     *
     * Anybody not on the list loses the assignment, anybody on it who is not eligible is
     * refused. The forum role is re-synced afterwards, for this course only.
     *
     * @param int $courseid
     * @param array $userids the users that should support this course from now on.
     * @param bool $replace false to only add, leaving anybody already assigned in place.
     * @return array with the keys added, removed and refused, each holding user ids.
     */
    public static function assign(int $courseid, array $userids, bool $replace = true): array {
        global $DB;

        $result = ['added' => [], 'removed' => [], 'refused' => []];
        if ($courseid == platform_team::COURSE_ID) {
            // That id is the platform team, which is maintained elsewhere.
            return $result;
        }

        $eligible = self::get_assignable_users($courseid);
        $current = [];
        foreach (self::get_members($courseid) as $row) {
            $current[$row->userid] = $row;
        }

        $wanted = [];
        foreach ($userids as $userid) {
            $userid = (int) $userid;
            if (!isset($eligible[$userid])) {
                $result['refused'][] = $userid;
                continue;
            }
            $wanted[$userid] = $userid;
        }

        $context = \context_course::instance($courseid);

        foreach ($wanted as $userid) {
            if (isset($current[$userid])) {
                continue;
            }
            $DB->insert_record('local_helpdesk_supporters', (object) [
                'courseid' => $courseid,
                'userid' => $userid,
                'supportlevel' => '',
                'holidaymode' => 0,
                'autoassign' => 0,
            ]);
            $result['added'][] = $userid;
            \local_helpdesk\event\supportuser_added::create([
                'objectid' => $courseid,
                'context' => $context,
                'relateduserid' => $userid,
                'other' => ['supportuserid' => $userid, 'supportlevel' => ''],
            ])->trigger();
        }

        foreach ($current as $userid => $row) {
            if (!$replace || isset($wanted[$userid])) {
                continue;
            }
            $DB->delete_records('local_helpdesk_supporters', ['id' => $row->id]);
            $result['removed'][] = $userid;
            \local_helpdesk\event\supportuser_deleted::create([
                'objectid' => $courseid,
                'context' => $context,
                'relateduserid' => $userid,
                'other' => ['supportuserid' => $userid, 'supportlevel' => $row->supportlevel],
            ])->trigger();
        }

        if (!empty($result['added']) || !empty($result['removed'])) {
            self::sync_roles($courseid);
        }

        return $result;
    }

    /**
     * Fill the first level of every support course from the eligibility rule.
     *
     * This is a one off aid for sites that used to have first level decided by
     * moodle/course:update. It only ever adds, so an assignment somebody made on purpose is
     * never taken away, and running it twice changes nothing the second time.
     *
     * @param bool $dryrun true to report what would happen without writing anything.
     * @return array one entry per support course, each with courseid, coursename, eligible,
     *               assigned, toadd and the names that would be added.
     */
    public static function seed_from_capabilities(bool $dryrun = true): array {
        global $DB;

        $courseids = $DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {local_helpdesk} ORDER BY courseid');
        $report = [];

        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            if ($courseid == platform_team::COURSE_ID || !$DB->record_exists('course', ['id' => $courseid])) {
                continue;
            }

            $eligible = self::get_assignable_users($courseid);
            $assigned = [];
            foreach (self::get_members($courseid) as $row) {
                $assigned[$row->userid] = true;
            }

            $missing = array_diff_key($eligible, $assigned);
            $course = get_course($courseid);

            $report[$courseid] = [
                'courseid' => $courseid,
                'coursename' => format_string($course->fullname),
                'eligible' => count($eligible),
                'assigned' => count($assigned),
                'toadd' => count($missing),
                'names' => array_values(array_map(fn(stdClass $user): string => fullname($user), $missing)),
            ];

            if (!$dryrun && !empty($missing)) {
                self::assign($courseid, array_keys($missing), false);
            }
        }

        return $report;
    }

    /**
     * Get the first level support of a support forum.
     *
     * First level is assigned explicitly per course, which is what carries the per school
     * model: every school has its own support course, and the people named there answer the
     * requests filed in it. Only when they cannot help does an issue get escalated to the
     * second level, the platform wide team kept under the sentinel course id.
     *
     * An empty result means nobody was named, and requests escalate straight away.
     *
     * @param stdClass $forum the support forum.
     * @return array of user records, keyed by user id.
     */
    public static function get_users_of_forum(stdClass $forum): array {
        global $DB;

        $rows = self::get_members($forum->course);
        if (empty($rows)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_column($rows, 'userid'), SQL_PARAMS_NAMED);

        return $DB->get_records_select('user', "id $insql AND deleted = 0", $inparams);
    }

    /**
     * Find a support user that has the same customfieldvalue as a user (can be enabled in settings)
     *
     * @param int $courseid the course to look for support users in.
     * @return stdClass[] the matching support users, keyed by user id; empty if there are none.
     */
    public static function find_by_custom_field(int $courseid): array {
        global $DB, $USER;
        $userid = $USER->id;
        $customfieldname = get_config('local_helpdesk', 'customfieldname');
        $sql = "SELECT uid.data, uif.id FROM {user_info_data} uid
        LEFT JOIN {user_info_field} uif
        on uid.fieldid = uif.id
        WHERE uif.name = :customfieldname AND uid.userid = :userid";
        $params = ['courseid' => $courseid, 'customfieldname' => $customfieldname, 'userid' => $userid];
        $customfielddata = $DB->get_record_sql($sql, $params);
        if (!$customfielddata) {
            // The person has no value in the field, so nobody can match it.
            return [];
        }
        $role = get_config('local_helpdesk', 'rolename');
        $params = ['courseid' => $courseid, 'fieldid' => $customfielddata->id, 'customfieldvalue' => $customfielddata->data,
            'role' => $role];
        $sql = "SELECT  uid.userid, u.firstname, u.lastname,  uid.data,  r.shortname from {course} ic
                        JOIN {context} con ON con.instanceid = ic.id
                        JOIN {role_assignments} ra ON con.id = ra.contextid AND con.contextlevel = 50
                        JOIN {role} r ON ra.roleid = r.id
                        JOIN {user} u ON u.id = ra.userid
                        LEFT JOIN {groups_members} gm ON u.id = gm.userid
                        LEFT JOIN {user_info_data} uid on u.id = uid.userid
                        WHERE ic.id = :courseid AND u.id > 0 AND uid.fieldid = :fieldid
                        AND uid.data = :customfieldvalue AND r.shortname = :role";
        return $DB->get_records_sql($sql, $params);
    }
}
