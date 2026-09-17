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
 * Scheduled task that reminds supporters of issues waiting for them.
 *
 * @package    local_helpdesk
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\task;

/**
 * Ad hoc task that reminds supporters of issues waiting for them.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder extends \core\task\adhoc_task {
    /**
     * Get the name shown for this task in the admin screens.
     *
     * @return string
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('cron:reminder:title', 'local_helpdesk');
    }

    /**
     * Remind the supporter of the issue this task was queued for, if it is still waiting for them.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $taskdata = $this->get_custom_data();
        if (!get_config('local_helpdesk', 'sendreminders') || empty($taskdata->issueid)) {
            return;
        }

        $issue = $DB->get_record('local_helpdesk_issues', [
            'id' => $taskdata->issueid,
            'status' => \local_helpdesk\lib::STATUS_AWAITING_SUPPORT_ACTION,
        ]);
        if (!$issue || $issue->priority <= 0 || empty($issue->currentsupporter)) {
            return;
        }

        $supporter = $DB->get_record('user', ['id' => $issue->currentsupporter, 'deleted' => 0]);
        $discussion = $DB->get_record('forum_discussions', ['id' => $issue->discussionid]);
        $post = $discussion ? $DB->get_record('forum_posts', ['id' => $discussion->firstpost]) : false;
        $user = $discussion ? $DB->get_record('user', ['id' => $discussion->userid]) : false;
        if (!$supporter || !$post || !$user) {
            return;
        }

        $discussion->message = $post->message;
        $discussion->userfullname = \fullname($user);
        $discussion->useremail = $user->email;
        $this->send($supporter, [$discussion]);
    }

    /**
     * Send one reminder message to a supporter.
     *
     * @param object $supporter the user to remind.
     * @param array $reminders the discussions to remind them of.
     * @return void
     */
    private function send($supporter, $reminders = []) {
        global $CFG, $OUTPUT;
        if (!empty($supporter->id) && $supporter->id > 0 && count($reminders) > 0) {
            $subject = $this->get_name();
            $mailhtml = $OUTPUT->render_from_template(
                'local_helpdesk/reminder_discussions',
                ['discussions' => $reminders, 'wwwroot' => $CFG->wwwroot]
            );
            $mailtext = html_to_text($mailhtml);

            $fromuser = \core_user::get_support_user();
            \email_to_user($supporter, $fromuser, $subject, $mailtext, $mailhtml, '', '', true);
        }
    }
}
