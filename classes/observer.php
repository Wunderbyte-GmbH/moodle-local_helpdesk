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
 * Event observers of the Helpdesk plugin.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmangement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use cache_helper;
use local_helpdesk\event\supportuser_added;
use local_helpdesk\event\supportuser_changed;
use local_helpdesk\event\supportuser_deleted;
use local_helpdesk\task\send_mail;

/**
 * Event observers of the Helpdesk plugin.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Observer for the supportuser_added event
     *
     * @param supportuser_added $event
     */
    public static function supportuser_added(supportuser_added $event) {
        // When a support user gets added, changed or deleted, we need to purge the navbar menu cache.
        // The navbar only shows issues if a user is a support user (or an admin).
        cache_helper::purge_by_event('local_helpdesk_setbacksupportmenu');
    }

    /**
     * Observer for the supportuser_changed event
     *
     * @param supportuser_changed $event
     */
    public static function supportuser_changed(supportuser_changed $event) {
        // When a support user gets added, changed or deleted, we need to purge the navbar menu cache.
        // The navbar only shows issues if a user is a support user (or an admin).
        cache_helper::purge_by_event('local_helpdesk_setbacksupportmenu');
    }

    /**
     * Observer for the supportuser_deleted event
     *
     * @param supportuser_deleted $event
     */
    public static function supportuser_deleted(supportuser_deleted $event) {
        // When a support user gets added, changed or deleted, we need to purge the navbar menu cache.
        // The navbar only shows issues if a user is a support user (or an admin).
        cache_helper::purge_by_event('local_helpdesk_setbacksupportmenu');
    }

    /**
     * React to a forum post being created in a support forum.
     *
     * @param \core\event\base $event the event that was triggered.
     * @return void
     */
    public static function event($event) {

        // We should have separate functions for different event types for better readability!

        global $CFG, $DB, $OUTPUT;

        $entry = (object)$event->get_data();
        if ($entry->eventname == '\mod_forum\event\discussion_deleted') {
            $discussionid = $entry->objectid;
            \local_helpdesk\local\guest_ticket::delete($discussionid);
            return \local_helpdesk\lib::delete_issue($discussionid);
        } else {
            if (substr($entry->eventname, 0, strlen("\\mod_forum\\event\\post_")) == "\\mod_forum\\event\\post_") {
                $post = $DB->get_record("forum_posts", ["id" => $entry->objectid]);
                $discussion = $DB->get_record("forum_discussions", ["id" => $post->discussion]);
            } else {
                $discussion = $DB->get_record("forum_discussions", ["id" => $entry->objectid]);
                $post = $DB->get_record("forum_posts", ["discussion" => $discussion->id, "parent" => 0]);
            }

            $forum = $DB->get_record("forum", ["id" => $discussion->forum]);
            $course = $DB->get_record("course", ["id" => $forum->course]);
            $issue = $DB->get_record('local_helpdesk_issues', ['discussionid' => $discussion->id]);
            if (empty($issue->id)) {
                return;
            }
            \local_helpdesk\lib::reopen_issue($discussion->id);
            $author = $DB->get_record('user', ['id' => $post->userid]);

            // Having more than one user in the discussion means that someone has already answered.
            $morethanoneuser = $DB->get_record_sql(
                "SELECT COUNT(DISTINCT userid) AS count
                FROM {forum_posts}
                WHERE discussion = :discussion",
                ['discussion' => $discussion->id]
            );
            if ($morethanoneuser->count > 1) {
                if ($post->userid == $discussion->userid) {
                    // If the user posted, we are waiting for a support action.
                    \local_helpdesk\lib::set_status(LOCAL_HELPDESK_ISSUE_STATUS_AWAITING_SUPPORT_ACTION, $issue->id);
                } else {
                    // If the supporter posted, we are waiting for a user reply.
                    \local_helpdesk\lib::set_status(LOCAL_HELPDESK_ISSUE_STATUS_AWAITING_USER_REPLY, $issue->id);
                }
            } else {
                \local_helpdesk\lib::set_status(LOCAL_HELPDESK_ISSUE_STATUS_AWAITING_SUPPORT_ACTION, $issue->id);
            }
            // Enhance post data.
            $post->wwwroot = $CFG->wwwroot;
            $post->authorfullname = \fullname($author);
            $post->authorlink = $CFG->wwwroot . '/user/view.php?id=' . $author->id;
            $post->authorpicture = $OUTPUT->user_picture($author, ['size' => 40]);
            $post->postdate = userdate($post->created, '%d. %B %Y, %H:%M');

            $post->coursename = $course->fullname;
            $post->forumname = $forum->name;
            $post->discussionname = $discussion->name;

            $post->issuelink = $CFG->wwwroot . '/local/helpdesk/issue.php?d=' . $discussion->id;
            $post->replylink = $CFG->wwwroot . '/local/helpdesk/issue.php?d=' . $discussion->id . '&replyto=' . $post->id;

            // Get all subscribers.
            $subscribers = $DB->get_records('local_helpdesk_subscr', ['discussionid' => $discussion->id]);
            $guestmode = get_config('local_helpdesk', 'guestmodeenabled');

            // Write to Guestuser.
            $mail = $guestmode ? \local_helpdesk\local\guest_ticket::get_email($discussion->id) : null;
            if ($mail) {
                $guestuser = new guest_supportuser();
                $touser = $guestuser->get_support_guestuser();
                $touser->email = $mail;
                $post->furtherquestions = get_string('furtherquestions', 'local_helpdesk', ['sitename' => $CFG->wwwroot]);

                $mailhtml = $OUTPUT->render_from_template('local_helpdesk/post_mailhtml_guest', $post);
                $mailtext = $OUTPUT->render_from_template('local_helpdesk/post_mailtext_guest', $post);
                $subject = $discussion->name;
                // The guest address travels with the queued mail, see send_mail::queue().
                send_mail::queue($touser, $author, $subject, $mailtext, $mailhtml);
            }
            foreach ($subscribers as $subscriber) {
                // We do not want to send to ourselves...
                if ($subscriber->userid == $author->id) {
                    continue;
                }

                $touser = $DB->get_record('user', ['id' => $subscriber->userid]);
                if (empty($touser)) {
                    // The subscriber is gone, so there is nobody left to notify.
                    continue;
                }

                // Send notification.
                $subject = $discussion->name;
                $mailhtml = $OUTPUT->render_from_template('local_helpdesk/post_mailhtml', $post);
                $mailtext = $OUTPUT->render_from_template('local_helpdesk/post_mailtext', $post);

                // Queued, so that a slow mail server does not hold up the request that posted this.
                send_mail::queue($touser, $author, $subject, $mailtext, $mailhtml);
            }
            return true;
        }
    }

    /**
     * Remove everything that ties a deleted user to the support system.
     *
     * This is the only observer for user deletion. observer::event() used to handle it as
     * well and deleted supporter rows by their own id rather than by user id, which removed
     * whichever unrelated supporter happened to have a row id equal to the deleted user's id.
     *
     * The cleanup is the one the privacy provider does for a deletion request, so both end
     * up leaving the same state behind.
     *
     * @param \core\event\user_deleted $event
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        \local_helpdesk\privacy\provider::delete_user_data($event->objectid);
    }
}
