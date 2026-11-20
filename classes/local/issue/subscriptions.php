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
 * Which members of the platform team follow an issue.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\issue;

use local_helpdesk\local\supporter\platform_team;

/**
 * Which members of the platform team follow an issue.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {
    /**
     * Add support user to the list of assigned users.
     *
     * @param int $discussionid the discussion of the issue.
     * @param int $userid the user to subscribe, 0 for the current user.
     * @return bool true if the person is subscribed now, false if they are no member of the platform team.
     */
    public static function add(int $discussionid, int $userid = 0): bool {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        if (!platform_team::is_member($userid)) {
            // Only the platform team follows tickets; first level works in the forum.
            return false;
        }
        $issue = issue_manager::get($discussionid);
        if (!$issue) {
            return false;
        }
        $subscription = $DB->get_record('local_helpdesk_subscr', ['discussionid' => $discussionid, 'userid' => $userid]);
        if (empty($subscription->id)) {
            $subscription = (object) [
                'issueid' => $issue->id,
                'discussionid' => $discussionid,
                'userid' => $userid,
            ];
            $DB->insert_record('local_helpdesk_subscr', $subscription);
        }
        return true;
    }

    /**
     * Remove support user from the list of assigned users.
     *
     * @param int $discussionid the discussion of the issue.
     * @param int $userid the user to unsubscribe, 0 for the current user.
     * @return void
     */
    public static function remove(int $discussionid, int $userid = 0): void {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $DB->delete_records('local_helpdesk_subscr', ['discussionid' => $discussionid, 'userid' => $userid]);
    }
}
