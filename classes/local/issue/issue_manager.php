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
 * The life cycle of a support issue: forwarding, assigning, prioritising, closing and reopening.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\issue;

use context_system;
use core\message\message;
use local_helpdesk\guest_supportuser;
use local_helpdesk\local\supporter\platform_team;
use local_helpdesk\local\supportforum\registry;
use local_helpdesk\task\reminder;
use mod_forum\event\post_created;
use moodle_url;
use stdClass;

/**
 * The life cycle of a support issue: forwarding, assigning, prioritising, closing and reopening.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_manager {
    /**
     * Close an issue.
     *
     * @param int $discussionid the discussion of the issue.
     * @return bool true if the issue was closed.
     */
    public static function close(int $discussionid): bool {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get($discussionid);
        if (!$discussion || !$issue || !registry::is_supportforum($discussion->forum)) {
            return false;
        }
        // The ticket system is the platform team's tool.
        if (!platform_team::is_member()) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post(
            $issue->discussionid,
            get_string(
                'issue_closed:post',
                'local_helpdesk',
                [
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_closed:subject', 'local_helpdesk'),
            get_config('local_helpdesk', 'sendissueclosed')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_helpdesk_subscr', ['discussionid' => $discussionid]);

        $issue->priority = 0;
        $issue->discussionid = $discussionid;
        $issue->status = status::CLOSED;
        $issue->timemodified = time();
        // 4.) remove issue-link from database
        $DB->update_record('local_helpdesk_issues', $issue);
        // Mark post as closed.
        $discussion->name = closed_prefix::add($discussion->name);
        $discussion->modified = time();
        $DB->update_record('forum_discussions', $discussion);
        return true;
    }

    /**
     * Delete an issue of which the discussion has been deleted.
     *
     * @param int $discussionid the discussion of the issue.
     * @return void
     */
    public static function delete(int $discussionid): void {
        global $DB;

        $issue = $DB->get_record('local_helpdesk_issues', ['discussionid' => $discussionid]);
        if (!empty($issue->id)) {
            // Remove all supporters from the abo-list.
            $DB->delete_records('local_helpdesk_subscr', ['discussionid' => $discussionid]);
            // Delete issue.
            $DB->delete_records('local_helpdesk_issues', ['discussionid' => $discussionid]);
        }
    }

    /**
     * Close an issue.
     *
     * @param int $discussionid the discussion of the issue.
     */
    public static function reopen(int $discussionid): bool {
        global $DB;
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get($discussionid);

        $issue->priority = 1;
        $issue->status = status::AWAITING_SUPPORT_ACTION;
        $issue->discussionid = $discussionid;
        $issue->timemodified = time();

        // 4.) remove issue-link from database.
        $DB->update_record('local_helpdesk_issues', $issue);

        // We also want to send reminders when we re-open an issue.
        self::schedule_reminder($issue->id);

        // Remove the closed-marker again.
        $discussion->name = closed_prefix::strip($discussion->name);
        $discussion->modified = time();
        $DB->update_record('forum_discussions', $discussion);
        return true;
    }

    /**
     * Answer to the original discussion post of a discussion
     *
     * @param int $discussionid
     * @param string $text as cpmtent
     * @param string $subject subject for post, if not given first 30 chars of text are used
     * @param int $sendemail 0 do not send, 1 send e-mail.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function create_post(int $discussionid, string $text, string $subject = "", int $sendemail = 1): void {
        global $DB, $USER;

        $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
        if ($guestmode && (isguestuser() || !isloggedin())) {
            $guestuser = new guest_supportuser();
            $user = $guestuser->get_support_guestuser();
        } else {
            $user = $USER;
        }
        if (empty($subject)) {
            $subject = substr($text, 0, 30);
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $post = $DB->get_record('forum_posts', ['discussion' => $discussionid, 'parent' => 0]);
        $post->parent = $post->id;
        unset($post->id);
        $post->userid = $user->id;
        $post->created = time();
        $post->modified = time();
        $post->mailed = ($sendemail) ? 0 : 1;
        $post->subject = $subject;
        $post->message = $text;
        $post->messageformat = 1;
        $post->id = $DB->insert_record('forum_posts', $post, 1);

        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);

        $dbcontext = $DB->get_record('course_modules', ['course' => $discussion->course, 'instance' => $discussion->forum]);
        $context = \context_module::instance($dbcontext->id);
        $eventparams = [
            'context' => $context,
            'objectid' => $post->id,
            'other' => [
                'discussionid' => $discussion->id,
                'forumid' => $discussion->forum,
                'forumtype' => $forum->type,
            ],
        ];

        $event = post_created::create($eventparams);
        $event->add_record_snapshot('forum_posts', $post);
        $event->trigger();
    }

    /**
     * Get the issue for this discussionid.
     *
     * @param int $discussionid the discussion of the issue.
     * @param bool $createifnotexist whether to create the issue when there is none yet.
     * @param stdClass|null $keyvaluepair a field to set on a newly created issue, with key and value.
     * @return stdClass|null the issue, null if there is none (and none was to be created).
     * @throws \dml_exception
     */
    public static function get(int $discussionid, bool $createifnotexist = false, ?stdClass $keyvaluepair = null): ?stdClass {
        global $DB;
        if (empty($discussionid)) {
            return null;
        }
        $issue = $DB->get_record('local_helpdesk_issues', ['discussionid' => $discussionid]) ?: null;
        if ($issue === null && $createifnotexist) {
            $issue = (object) [
                'discussionid' => $discussionid,
                'currentsupporter' => 0,
                'created' => time(),
            ];
            if (isset($keyvaluepair)) {
                $issue->{$keyvaluepair->key} = $keyvaluepair->value;
            }
            $issue->timecreated = time();
            $issue->timemodified = time();
            $issue->id = $DB->insert_record('local_helpdesk_issues', $issue);
        }
        return $issue;
    }

    /**
     * The discussions of closed issues whose retention time has passed.
     *
     * Only the ids are fetched, so this stays small even on sites with many old issues.
     *
     * @return int[] discussion ids.
     */
    public static function get_expired_discussionids(): array {
        global $DB;
        $time = get_config('local_helpdesk', 'deletethreshhold');
        $expirationtime = time() - $time;
        if (!$time || $time == 0) {
            $expirationtime = 0;
        }

        $sql = "SELECT edu.discussionid
                  FROM {local_helpdesk_issues} edu
                  JOIN {forum_discussions} f ON edu.discussionid = f.id
                 WHERE edu.priority = 0
                   AND f.timemodified < :expirationtime";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['expirationtime' => $expirationtime]));
    }

    /**
     * Similar to close(), but can be done by a trainer in the supportforum.
     *
     * @param int $discussionid the discussion of the issue.
     * @return bool true if the issue was taken back.
     */
    public static function revoke(int $discussionid): bool {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get($discussionid);
        if (!$discussion || !$issue || !registry::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action has trainer permissions.
        $coursecontext = \context_course::instance($discussion->course);
        if (!has_capability('local/helpdesk:canforward2ndlevel', $coursecontext)) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post(
            $issue->discussionid,
            get_string(
                'issue_revoke:post',
                'local_helpdesk',
                [
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_revoke:subject', 'local_helpdesk'),
            get_config('local_helpdesk', 'sendissueclosed')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_helpdesk_subscr', ['discussionid' => $discussionid]);

        // 4.) remove issue-link from database
        $DB->delete_records('local_helpdesk_issues', ['discussionid' => $discussionid]);

        return true;
    }

    /**
     * Send an issue to 2nd level support.
     *
     * @param int $discussionid the discussion of the issue.
     * @param stdClass|null $keyvaluepair a field to set if the issue has to be created, with key and value.
     * @return bool true if the issue is with the platform team now.
     */
    public static function forward_to_second_level(int $discussionid, ?stdClass $keyvaluepair = null): bool {
        global $CFG, $DB, $USER, $PAGE, $SITE;

        if (!isset($PAGE->context)) {
            $PAGE->set_context(context_system::instance());
        }

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        if (!$discussion) {
            return false;
        }
        $issue = self::get($discussionid, true, $keyvaluepair);
        if (!registry::is_supportforum($discussion->forum)) {
            return false;
        }
        // Todo: MDL-000000 Only subscribe 1 person and make it responsible!
        $supportforum = $DB->get_record('local_helpdesk', ['forumid' => $discussion->forum]);

        $respectholidays = !empty(get_config('local_helpdesk', 'holidaymodeenabled'));
        $supporters = platform_team::get_members(true, $respectholidays);
        if (empty($supporters) && $respectholidays) {
            // Everybody is away, so fall back to the whole assignable team rather than
            // leaving the request without anybody responsible.
            $supporters = platform_team::get_members(true, false);
        }

        // The dedicated supporter is stored as a user id, while the rows are keyed by their
        // own id, so the person has to be looked up rather than indexed.
        $dedicated = null;
        if (!empty($supportforum->dedicatedsupporter)) {
            foreach ($supporters as $supporter) {
                if ($supporter->userid == $supportforum->dedicatedsupporter) {
                    $dedicated = $supporter;
                    break;
                }
            }
        }
        if ($dedicated === null && !empty($supporters)) {
            // Choose one supporter randomly.
            $keys = array_keys($supporters);
            $dedicated = $supporters[$keys[array_rand($keys)]];
        }

        if (!empty($dedicated->userid)) {
            $DB->set_field(
                'local_helpdesk_issues',
                'currentsupporter',
                $dedicated->userid,
                ['discussionid' => $discussion->id]
            );
            subscriptions::add($discussionid, $dedicated->userid);
        }
        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);

        if (!empty($dedicated->userid) && get_config('local_helpdesk', 'sendmsgonset2ndlvl')) {
            $subject = get_string('issue_assigned:subject', 'local_helpdesk');
            $messagebody = get_string('issue_assign_nextlevel:post', 'local_helpdesk', (object) [
                'fromuserfullname' => fullname($USER),
                'fromuserid' => $USER->id,
                'wwwroot' => $CFG->wwwroot,
                'sitename' => $SITE->fullname,
                'supportforumname' => $forum->name,
            ]);
            // Post assignment message to forum.
            self::create_post(
                $issue->discussionid,
                $messagebody,
                $subject,
                // Only send mail if setting is turned on!
                get_config('local_helpdesk', 'sendsupporterassignments')
            );
            // In any case, send e-mail to the dedicated supporter.
            $issueurl = (new moodle_url('/local/helpdesk/issue.php?d=' . $discussion->id))->out(false);
            $posthtml = get_string('issue:assigned', 'local_helpdesk') . " " . $discussion->name . " $issueurl";
            $postsubject = $discussion->name;
            $msg = new message();
            $touser = $DB->get_record('user', ['id' => $dedicated->userid]);
            $msg->userfrom = $USER;
            $msg->userto = $touser;
            $msg->subject = $postsubject;
            $msg->fullmessage = $posthtml;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml = $posthtml;
            $msg->smallmessage = $postsubject;
            $msg->contexturl = $issueurl; // A relevant URL for the notification.
            // Link title explaining where users get to for the contexturl.
            $msg->contexturlname = get_string('issue', 'local_helpdesk');
            $msg->name = 'helpdesk_issue';
            $msg->component = 'local_helpdesk';
            $msg->notification = 1;
            message_send($msg);
        } else {
            self::set_status(status::AWAITING_SUPPORT_ACTION, $issue->id);
        }

        return true;
    }

    /**
     * Check whether the current user may hand an issue to a particular supporter.
     *
     * Returning the reason rather than just a boolean lets callers tell the user what went
     * wrong. set_current_supporter() uses this to decide whether to act at all.
     *
     * @param int $discussionid the discussion behind the issue.
     * @param int $userid the user the issue should be handed to.
     * @return string|null the language string identifier of the refusal, or null if allowed.
     */
    public static function validate_supporter_assignment(int $discussionid, int $userid): ?string {
        global $DB;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        if (empty($discussion->id)) {
            return 'error:unknowndiscussion';
        }
        if (!registry::is_supportforum($discussion->forum)) {
            return 'error:notasupportforum';
        }
        // Handing a ticket over happens inside the platform team, on both ends: first
        // level works in the forum and never takes ownership of a ticket.
        if (!platform_team::is_member()) {
            return 'error:notasupporter';
        }
        if (!platform_team::is_member($userid)) {
            return 'error:targetnotasupporter';
        }
        return null;
    }

    /**
     * Used by 2nd-level support to assign an issue to a particular person from 3rd level.
     *
     * @param int $discussionid the discussion of the issue.
     * @param int $userid the supporter to hand the issue to.
     * @return bool true when the issue was handed over, false when that was refused.
     */
    public static function set_current_supporter(int $discussionid, int $userid): bool {
        global $CFG, $DB, $USER, $PAGE, $SITE;

        if (!isset($PAGE->context)) {
            $PAGE->set_context(context_system::instance());
        }

        if (self::validate_supporter_assignment($discussionid, $userid) !== null) {
            return false;
        }

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);

        // Set currentsupporter and add to subscribed users.
        $DB->set_field('local_helpdesk_issues', 'currentsupporter', $userid, ['discussionid' => $discussion->id]);
        subscriptions::add($discussionid, $userid);

        $supporter = $DB->get_record('local_helpdesk_supporters', ['userid' => $userid]);
        if (empty($supporter->supportlevel)) {
            $supporter->supportlevel = get_string('label:2ndlevel', 'local_helpdesk');
        }
        $touser = $DB->get_record('user', ['id' => $userid]);
        $subject = get_string('issue_assigned:subject', 'local_helpdesk');
        $messagebody = get_string(
            'issue_assign_nextlevel:post',
            'local_helpdesk',
            (object) [
                'fromuserfullname' => \fullname($USER),
                'fromuserid' => $USER->id,
                'touserfullname' => \fullname($touser),
                'touserid' => $userid,
                'tosupportlevel' => $supporter->supportlevel,
                'wwwroot' => $CFG->wwwroot,
                'sitename' => $SITE->fullname,
                'supportforumname' => $forum->name,
            ]
        );
        // Post assignment message to forum.
        self::create_post(
            $discussionid,
            $messagebody,
            $subject,
            // Only send mail if setting is turned on!
            get_config('local_helpdesk', 'sendsupporterassignments')
        );

        // In any case, send e-mail to the assigned supporter.
        $issueurl = (new moodle_url('/local/helpdesk/issue.php?d=' . $discussion->id))->out(false);
        $posthtml = get_string('issue:assigned', 'local_helpdesk') . " " . $discussion->name . " $issueurl";
        $postsubject = $discussion->name;
        $msg = new message();
        $touser = $DB->get_record('user', ['id' => $userid]);
        $msg->userfrom = $USER;
        $msg->userto = $touser;
        $msg->subject = $postsubject;
        $msg->fullmessage = $posthtml;
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = $posthtml;
        $msg->smallmessage = $postsubject;
        $msg->contexturl = $issueurl; // A relevant URL for the notification.
        // Link title explaining where users get to for the contexturl.
            $msg->contexturlname = get_string('issue', 'local_helpdesk');
        $msg->name = 'helpdesk_issue';
        $msg->component = 'local_helpdesk';
        $msg->notification = 1;
        message_send($msg);
        return true;
    }

    /**
     * Set the priority level of an issue.
     *
     * @param int $discussionid
     * @param int $priority
     * @return bool false if the discussion is no issue.
     * @throws \dml_exception
     */
    public static function set_priority(int $discussionid, int $priority): bool {
        global $DB;

        $issue = self::get($discussionid);
        if (!$issue) {
            return false;
        }
        $issue->priority = $priority;
        $issue->discussionid = $discussionid;
        $issue->timemodified = time();
        $DB->update_record('local_helpdesk_issues', $issue);
        return true;
    }

    /**
     * Updates status of an issueid
     *
     * @param int $status
     * @param int $issueid
     *
     * @return void
     */
    public static function set_status(int $status, int $issueid): void {
        global $DB;
        $issue = new stdClass();
        $issue->id = $issueid;
        $issue->status = $status;
        $issue->timemodified = time();

        $DB->update_record('local_helpdesk_issues', $issue);

        if ($status == status::AWAITING_SUPPORT_ACTION && get_config('local_helpdesk', 'sendreminders')) {
            self::schedule_reminder($issueid);
        }
    }

    /**
     * Helper function to send reminder mails.
     * @param int $issueid
     * @return void
     */
    public static function schedule_reminder(int $issueid): void {

        if (self::has_scheduled_reminder($issueid)) {
            // There already are reminders in the future, so we do not create another one.
            return;
        }

        $taskdata = [
            'issueid' => $issueid,
            // No second reminders anymore.
        ];
        $task = new reminder();
        $task->set_custom_data($taskdata);
        $timebeforereminder = time() + (get_config('local_helpdesk', 'timebeforereminder'));
        $task->set_next_run_time($timebeforereminder);

        // Unfortunately, reschedule does not work because set_status is called at each loading of issues.php.
        // So we need to use the normal queue function.
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Helper function to check if there already is a reminder to be sent in the future.
     * @param int $issueid
     * @return bool
     */
    public static function has_scheduled_reminder(int $issueid): bool {
        foreach (\core\task\manager::get_adhoc_tasks(\local_helpdesk\task\reminder::class) as $task) {
            $data = $task->get_custom_data();
            if (!empty($data->issueid) && (int) $data->issueid === $issueid) {
                return true;
            }
        }
        return false;
    }
}
