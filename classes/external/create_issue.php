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
 * Web service to file a support request.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\external;

use completion_info;
use context_module;
use context_system;
use core\message\message;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_user;
use local_helpdesk\guest_supportuser;
use local_helpdesk\lib;
use local_helpdesk\task\send_mail;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Web service to file a support request.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_issue extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'subject' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'description' => new external_value(PARAM_RAW, 'default for whole package otherwise channel name'),
                // We use PARAM_RAW here, as the editor can send HTML.
            'forum_group' => new external_value(PARAM_TEXT, 'Forum-ID and Group-ID to post to in format forumid_groupid.'),
            'postto2ndlevel' => new external_value(PARAM_INT, '1st level supporters can directly call the 2nd level support'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image as data url or empty string'),
            'screenshotname' => new external_value(PARAM_TEXT, 'the filename to use'),
            'url' => new external_value(PARAM_TEXT, 'URL where the error happened'),
                // We use PARAM_TEXT, as any input by the user is valid.
            'contactphone' => new external_value(PARAM_TEXT, 'Contactphone'),
                // We use PARAM_TEXT, was the user can enter any contact information.
            // Top level parameters must not be VALUE_OPTIONAL, only VALUE_DEFAULT or VALUE_REQUIRED.
            'guestmail' => new external_value(PARAM_EMAIL, 'Guestmail', VALUE_DEFAULT, null, NULL_ALLOWED),
            'accountmanager' => new external_value(PARAM_INT, 'Accountmanager', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Create an issue in the targetforum.
     *
     * @param string $subject The subject of the issue.
     * @param string $description The description of the issue.
     * @param string $forumgroup Forum ID and group ID in format forumid_groupid.
     * @param int $postto2ndlevel 1st level supporters can directly call the 2nd level support.
     * @param string $image Base64 encoded image as data url or empty string.
     * @param string $screenshotname The filename to use for the screenshot.
     * @param string $url URL where the error happened.
     * @param string $contactphone Contact phone number.
     * @param string|null $guestmail Guest email address (optional).
     * @param int|null $accountmanager Account manager ID (optional).
     * @return array Array containing discussionid and responsibles.
     */
    public static function execute(
        $subject,
        $description,
        $forumgroup,
        $postto2ndlevel,
        $image,
        $screenshotname,
        $url,
        $contactphone,
        $guestmail,
        $accountmanager = null
    ): array {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER, $SITE;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        // Counted per person, or per address for somebody who is not logged in.
        \local_helpdesk\local\rate_limiter::register_ticket();

        $subjectprefixenabled = get_config('local_helpdesk', 'predefined_subjects_prefix');
        $guestmodeenabled = false;
        $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
        if ($guestmode && isset($guestmail) && (isguestuser() || !isloggedin())) {
            $guestuser = new guest_supportuser();
            $user = $guestuser->get_support_guestuser();
            $guestmodeenabled = true;
        } else {
            $user = $USER;
        }

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['subject' => $subject, 'description' => $description, 'forum_group' => $forumgroup,
                        'postto2ndlevel' => $postto2ndlevel, 'image' => $image, 'screenshotname' => $screenshotname, 'url' => $url,
            'contactphone' => $contactphone,
            'guestmail' => $guestmail,
            'accountmanager' => $accountmanager]
        );
        $reply = [
                'discussionid' => 0,
                'responsibles' => [],
        ];
        // Whether the person filing the request gets to see who is going to look after it.
        // Somebody who is not logged in never does: the names would be there for everybody to collect.
        $showresponsibles = !empty(get_config('local_helpdesk', 'showresponsibles')) && !$guestmodeenabled;

        // Anything can be sent in place of a screenshot, so have a look before anything is created.
        $screenshot = null;
        if (!empty($params['image'])) {
            $screenshot = new \local_helpdesk\local\screenshot($params['image'], $params['screenshotname']);
        }
        if (!empty(get_config('local_helpdesk', 'trackhost'))) {
            $params['webhost'] = gethostname();
        }
        $params['description'] = nl2br($params['description']);

        $tmp = explode('_', $forumgroup);
        $forumid = 0;
        $groupid = 0;
        if (count($tmp) == 2) {
            $forumid = $tmp[0];
            $groupid = $tmp[1];
        }

        if (isloggedin() && !isguestuser()) {
            self::validate_context(context_system::instance());
        } else {
            // Requests can be filed without logging in, if the guest mode is on.
            $PAGE->set_context(context_system::instance());
        }

        if ($forumgroup == 'mail' || empty($forumid)) {
            // Fallback and send by mail!
            $subject = $params['subject'];
            $params['includeemail'] = $user->email;
            $messagehtml = $OUTPUT->render_from_template("local_helpdesk/issue_template", $params);
            $messagetext = html_to_text($messagehtml);

            $supportuser = core_user::get_support_user();
            $recipients = [$supportuser];
            if ($showresponsibles) {
                $reply['responsibles'][] = [
                        'userid' => $supportuser->id,
                        'name' => \fullname($supportuser),
                        'email' => $supportuser->email,
                ];
            }
            $fromuser = $user;

            // The mails are queued rather than sent from here: talking to the mail server takes
            // as long as it takes, and the person filing the request is waiting for the answer.
            if ($screenshot) {
                $filename = $screenshot->get_filename();
                // Write image to a temporary file. The task deletes it once the mail has gone out.
                $filepath = $screenshot->write_tempfile(true);
                \core\antivirus\manager::scan_file($filepath, $filename, true);
                foreach ($recipients as $index => $recipient) {
                    // Every queued mail deletes its attachment, so each one needs a file of its own.
                    $attachment = $filepath;
                    if ($index > 0) {
                        $attachment = $filepath . '-' . $index;
                        copy($filepath, $attachment);
                    }
                    send_mail::queue($recipient, $fromuser, $subject, $messagetext, $messagehtml, $attachment, $filename);
                }
            } else {
                foreach ($recipients as $recipient) {
                    send_mail::queue($recipient, $fromuser, $subject, $messagetext, $messagehtml);
                }
            }
            $reply['discussionid'] = -999;
            return $reply;
        } else {
            $potentialtargets = lib::get_potentialtargets();
            if (lib::is_supportforum($forumid) && !empty($potentialtargets[$forumid]->id)) {
                $canpostto2ndlevel = $potentialtargets[$forumid]->postto2ndlevel;
                // Mainly copied from mod/forum/externallib.php > add_discussion().
                $warnings = [];

                // Request and permission validation.
                $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
                [$course, $cm] = get_course_and_cm_from_instance($forum, 'forum');

                if ($showresponsibles) {
                    foreach (lib::get_course_supporters($forum) as $coursesupporter) {
                        $reply['responsibles'][] = [
                                'userid' => $coursesupporter->id,
                                'name' => \fullname($coursesupporter),
                                // The profile is linked, the address is nobody's business.
                                'email' => '',
                        ];
                    }
                }

                $context = context_module::instance($cm->id);

                // Validate options.
                $options = [
                        'discussionsubscribe' => true,
                        'discussionpinned' => false,
                        'inlineattachmentsid' => 0,
                        'attachmentsid' => null,
                ];

                // Create group for user id firstlvlgroupmode is active.
                if (
                    get_config('local_helpdesk', 'firstlvlgroupmode') &&
                        $cfn = get_config('local_helpdesk', 'customfieldname')
                ) {
                    require_once("$CFG->dirroot/group/lib.php");
                    $groupname = fullname($user) . ' (' . $user->id . '-coursesupport)';
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
                        // Find support users.
                        $groupusers = lib::get_support_user_by_matching_customfield($forum->course, $cfn);
                        groups_add_member($group->id, $user);
                        if ($groupusers) {
                            $responsibles = [];
                            // Not $user: that is the person filing the request, and is needed below.
                            foreach ($groupusers as $groupuser) {
                                groups_add_member($group->id, $groupuser->userid);
                                $responsibles[] =
                                    "<a href='{$CFG->wwwroot}/user/profile.php?id={$groupuser->userid}' target='_blank'>" .
                                        s("{$groupuser->firstname} {$groupuser->lastname}") . "</a>";
                            }
                        } else {
                            $postto2ndlevel = true;
                        }
                    }
                }
                // Normalize group.
                if (!groups_get_activity_groupmode($cm)) {
                    // Groups not supported, force to -1.
                    $groupid = -1;
                } else {
                    // Check if we receive the default or and empty value for groupid,
                    // in this case, get the group for the user in the activity.
                    if (empty($groupid)) {
                        $groupid = groups_get_activity_group($cm);
                    } else if (
                        !$DB->record_exists('groups', ['id' => $groupid, 'courseid' => $forum->course])
                        || !groups_is_member($groupid, $user->id)
                    ) {
                        // The form only offers the groups of the person filing the request.
                        throw new moodle_exception('cannotcreatediscussion', 'forum');
                    }
                }

                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                /* if (!forum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
                    throw new moodle_exception('cannotcreatediscussion', 'forum');
                }*/

                $thresholdwarning = forum_check_throttling($forum, $cm);
                forum_check_blocking_threshold($thresholdwarning);

                $message = $OUTPUT->render_from_template("local_helpdesk/issue_template", $params);

                // Create the discussion.
                $discussion = new stdClass();
                $discussion->course = $course->id;
                $discussion->forum = $forum->id;
                $discussion->message = $message;
                $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
                $discussion->messagetrust = trusttext_trusted($context);
                $discussion->itemid = 0;
                // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
                /* $options['inlineattachmentsid']; */
                $discussion->groupid = $groupid;
                $discussion->mailnow = 1;
                if ($guestmodeenabled) {
                    $discussion->subject = '[Guestticket: ' . $params['guestmail'] . '] ' . $params['subject'];
                    $discussion->name = $discussion->subject;
                } else {
                    $discussion->subject = $params['subject'];
                    $discussion->name = $discussion->subject;
                }
                if ($subjectprefixenabled) {
                    $discussion->subject = get_string('subject_prefix', 'local_helpdesk') . " " . $discussion->subject;
                    $discussion->name = $discussion->subject;
                }

                $discussion->timestart = 0;
                $discussion->timeend = 0;
                $discussion->timelocked = 0;
                $discussion->attachment = 0;

                if (has_capability('mod/forum:pindiscussions', $context) && $options['discussionpinned']) {
                    $discussion->pinned = FORUM_DISCUSSION_PINNED;
                } else {
                    $discussion->pinned = FORUM_DISCUSSION_UNPINNED;
                }

                if ($discussionid = forum_add_discussion($discussion, null, null, $user->id)) {
                    $discussion->id = $discussionid;

                    if ($guestmodeenabled) {
                        // The title shows the address to the supporters; the answers go to this one.
                        \local_helpdesk\local\guest_ticket::set_email($discussionid, $params['guestmail']);
                    }

                    if ($screenshot) {
                        $filename = $screenshot->get_filename();
                        $filepath = $screenshot->write_tempfile();

                        $fs = get_file_storage();
                        // Scan for viruses.
                        \core\antivirus\manager::scan_file($filepath, $filename, true);

                        $fr = new stdClass();
                        $fr->component = 'mod_forum';
                        $fr->contextid = $context->id;
                        $fr->userid = $user->id;
                        $fr->filearea = 'attachment';
                        $fr->filename = $filename;
                        $fr->filepath = '/';
                        $fr->itemid = $discussion->firstpost;
                        $fr->license = $CFG->sitedefaultlicense;
                        $fr->author = fullname($user);
                        $fr->source = $filename;

                        $fs->create_file_from_pathname($fr, $filepath);
                        $DB->set_field('forum_posts', 'attachment', 1, ['id' => $discussion->firstpost]);
                    }

                    // Trigger events and completion.

                    $evparams = [
                            'context' => $context,
                            'objectid' => $discussion->id,
                            'other' => [
                                    'forumid' => $forum->id,
                            ],
                    ];
                    // Send email to user if configured.
                    $a = new stdClass();
                    $a->wwwroot = $CFG->wwwroot;
                    $a->cmid = $cm->id;
                    $a->sitename = $SITE->fullname;
                    $subject = get_string('issuereceived:subject', 'local_helpdesk');
                    $mailhtml = get_string('issuereceived', 'local_helpdesk', $a);
                    $mailtext = format_text($mailhtml, FORMAT_PLAIN);
                    if (get_config('local_helpdesk', 'sendrequestreceived')) {
                        // Queued, so that a slow mail server does not hold up the answer to the browser.
                        send_mail::queue($user, $user, $subject, $mailtext, $mailhtml);
                    }
                    $event = \mod_forum\event\discussion_created::create($evparams);
                    $event->add_record_snapshot('forum_discussions', $discussion);
                    $event->trigger();

                    $completion = new completion_info($course);
                    if (
                        $completion->is_enabled($cm) &&
                            ($forum->completiondiscussions || $forum->completionposts)
                    ) {
                        $completion->update_state($cm, COMPLETION_COMPLETE);
                    }

                    // Set the forum post as already mailed if the original request should not be sent to user.
                    $sendemail = get_config('local_helpdesk', 'sendoriginalrequest');
                    if (!$sendemail) {
                        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
                        $DB->set_field('forum_posts', 'mailed', 1, ['id' => $discussion->firstpost]);
                    }

                    $settings = new stdClass();
                    $settings->discussionsubscribe = $options['discussionsubscribe'];
                    forum_post_subscription($settings, $forum, $discussion);
                    $keyvaluepair = null;
                    if (isset($params['accountmanager'])) {
                        $keyvaluepair = new stdClass();
                        $keyvaluepair->key = 'accountmanager';
                        $keyvaluepair->value = $params['accountmanager'];
                    }
                    if ($postto2ndlevel && get_config('local_helpdesk', 'firstlvlgroupmode')) {
                        lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else if ($canpostto2ndlevel && !empty($postto2ndlevel)) {
                        lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else if (get_config('local_helpdesk', 'auto2ndlvl')) {
                        lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else if (empty(lib::get_first_level($forum->course))) {
                        // Nobody supports this course, which is how a small organisation
                        // without sub units is set up. The platform team takes it directly.
                        lib::set_2nd_level($discussion->id, $keyvaluepair);
                    } else {
                        $supporters = array_values(lib::get_course_supporters($forum));

                        // Post answer containing the reponsibles, unless that was turned off.
                        if ($showresponsibles) {
                            $responsibles = [];
                            if (!get_config('local_helpdesk', 'firstlvlgroupmode')) {
                                foreach ($supporters as $supporter) {
                                    $responsibles[] =
                                            "<a href='{$CFG->wwwroot}/user/profile.php?id={$supporter->id}' target='_blank'>" .
                                                "{$supporter->firstname} {$supporter->lastname}</a>";
                                }
                            }
                            $forum = $DB->get_record('forum', ['id' => $discussion->forum]);

                            $subject = get_string('issue_responsibles:subject', 'local_helpdesk');
                            $messagebody = get_string(
                                'issue_responsibles:post',
                                'local_helpdesk',
                                [
                                    'responsibles' => implode(', ', $responsibles),
                                    'sitename' => $SITE->fullname,
                                    'supportforumname' => $forum->name,
                                ]
                            );

                            // Post assignment message to forum.
                            lib::create_post(
                                $discussion->id,
                                $messagebody,
                                $subject,
                                // Only send mail if setting is turned on!
                                get_config('local_helpdesk', 'sendsupporterassignments')
                            );
                        }
                        // In any case, we want to inform the supporters.
                        foreach ($supporters as $supporter) {
                            // In any case, send e-mail to the dedicated supporter.
                            $issueurl = (new moodle_url('/local/helpdesk/issue.php?d=' . $discussion->id))->out(false);
                            $posthtml = get_string('issue:assigned', 'local_helpdesk') . " " . $discussion->name . " $issueurl";
                            $postsubject = $discussion->name;
                            $msg = new message();
                            $touser = $DB->get_record('user', ['id' => $supporter->id]);
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
                        }
                    }

                    $reply['discussionid'] = $discussionid;
                    return $reply;
                } else {
                    throw new moodle_exception('couldnotadd', 'forum');
                }
            } else {
                $reply['discussionid'] = -1;
                return $reply;
            }
        }
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'discussionid' => new external_value(
                    PARAM_INT,
                    'Returns the discussion id of the created issue, -999 when mail was sent, or -1 on error'
                ),
                'responsibles' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'userid' => new external_value(PARAM_INT, 'UserID of person or entity'),
                            'name' => new external_value(PARAM_TEXT, 'Name of person or entity'),
                            'email' => new external_value(PARAM_EMAIL, 'e-Mail of person or entity'),
                        ]
                    )
                ),
            ]
        );
    }
}
