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
 * The platform support team: second and third level support of the whole site.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\supporter;

/**
 * The platform support team: second and third level support of the whole site.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class platform_team {
    /** @var int Course id standing for the site wide support team rather than a single course. */
    const COURSE_ID = 1;

    /**
     * Check whether a user may look at the issue list and act on the issues in it.
     *
     * The navigation button leading to issues.php and the page itself both ask this, so that
     * nobody is offered a page they are then refused. Site admins are included: they used to
     * see the button without being let in, unless they had also been added to the platform team.
     *
     * @param int $userid check a particular user, or the current one.
     * @return bool
     */
    public static function can_view_issues(int $userid = 0): bool {
        global $USER;

        $userid = empty($userid) ? $USER->id : $userid;

        return \is_siteadmin($userid) || self::is_member($userid);
    }

    /**
     * Get the platform wide support team, which is the second level.
     *
     * Note that supportlevel is a free text label people fill in as they please. It groups
     * the team in the assignment dialogue and carries no meaning for the code.
     *
     * @param bool $assignableonly only those escalation may pick automatically.
     * @param bool $availableonly leave out whoever is on holiday right now.
     * @return array of supporter records, keyed by row id.
     */
    public static function get_members(bool $assignableonly = false, bool $availableonly = false): array {
        global $DB;

        $wheres = ['courseid = :courseid'];
        $params = ['courseid' => self::COURSE_ID];

        if ($assignableonly) {
            $wheres[] = 'autoassign = 1';
        }
        if ($availableonly) {
            $wheres[] = 'holidaymode < :now';
            $params['now'] = time();
        }

        return $DB->get_records_select('local_helpdesk_supporters', implode(' AND ', $wheres), $params);
    }

    /**
     * Check whether a user belongs to the platform wide support team.
     *
     * @param int $userid check a particular user, or the current one.
     * @return bool
     */
    public static function is_member(int $userid = 0): bool {
        global $DB, $USER;

        $userid = empty($userid) ? $USER->id : $userid;

        return $DB->record_exists('local_helpdesk_supporters', [
            'userid' => $userid,
            'courseid' => self::COURSE_ID,
        ]);
    }
}
