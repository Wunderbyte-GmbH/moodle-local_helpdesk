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
 * Overview of all support issues for the support team.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new moodle_url('/local/helpdesk/issues.php'));
$PAGE->requires->js_call_amd('local_helpdesk/actions', 'init');
$title = get_string('issues', 'local_helpdesk');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$issupportteam = \local_helpdesk\lib::can_view_issues();

// Handle all actions before any output is sent, so we can redirect afterwards (post/redirect/get).
// Without the redirect a reload or the back button would trigger the same action again, which for
// close and reopen means another status post in the discussion and another round of notifications.
if ($issupportteam) {
    $assign = optional_param('assign', 0, PARAM_INT); // Discussion id we want to assign to.
    $unassign = optional_param('unassign', 0, PARAM_INT); // Discussion id we want to unassign from.
    $take = optional_param('take', 0, PARAM_INT); // Discussion id we want to take over ourselves.
    $reopen = optional_param('reopen', 0, PARAM_INT); // Discussion id we want to reopen.
    $close = optional_param('close', 0, PARAM_INT); // Discussion id we want to close.
    $prio = optional_param('prio', 0, PARAM_INT); // Discussion id we want to set the priority for.
    $lvl = optional_param('lvl', 0, PARAM_INT); // The priority level to set.

    if (
        !empty($assign) || !empty($unassign) || !empty($take)
        || !empty($reopen) || !empty($close) || !empty($prio)
    ) {
        require_sesskey();

        // Only act on discussions that actually are registered as an issue.
        $isissue = function ($discussionid) use ($DB) {
            return !empty($discussionid)
                && $DB->record_exists('local_helpdesk_issues', ['discussionid' => $discussionid]);
        };

        if ($isissue($assign)) {
            \local_helpdesk\lib::subscription_add($assign);
        }
        if ($isissue($unassign)) {
            \local_helpdesk\lib::subscription_remove($unassign);
        }
        if ($isissue($take)) {
            \local_helpdesk\lib::set_current_supporter($take, $USER->id);
            \local_helpdesk\lib::subscription_add($take);
        }
        if ($isissue($reopen)) {
            \local_helpdesk\lib::reopen_issue($reopen);
        }
        if ($isissue($close)) {
            \local_helpdesk\lib::close_issue($close);
        }
        if (!empty($lvl) && $isissue($prio)) {
            \local_helpdesk\lib::set_prioritylvl($prio, $lvl);
        }

        redirect($PAGE->url);
    }

    // Holiday mode is handled here as well, so its form can redirect just like every other action.
    if (get_config('local_helpdesk', 'holidaymodeenabled')) {
        // Holiday mode decides whether escalation reaches somebody, so it belongs to the
        // platform team row. A course assignment carries no holiday of its own, and with the
        // unique key that row is unambiguous.
        $supporterconditions = [
            'userid' => $USER->id,
            'courseid' => \local_helpdesk\lib::SYSTEM_COURSE_ID,
        ];
        $supporter = $DB->get_record('local_helpdesk_supporters', $supporterconditions);
        if (!empty($supporter->id)) {
            $holidaymodeform = new \local_helpdesk\form\holidaymode_form();
            $holidaymodeend = optional_param('holidaymodeend', 0, PARAM_INT);
            if (!empty($holidaymodeend)) {
                require_sesskey();
                $DB->set_field('local_helpdesk_supporters', 'holidaymode', 0, $supporterconditions);
                redirect($PAGE->url);
            } else if ($holidaymodedata = $holidaymodeform->get_data()) {
                // The date_time_selector hands us a timestamp, and get_data() has checked the sesskey.
                $DB->set_field(
                    'local_helpdesk_supporters',
                    'holidaymode',
                    (int) $holidaymodedata->holidaymode,
                    $supporterconditions
                );
                redirect($PAGE->url);
            } else if (!empty($supporter->holidaymode) && $supporter->holidaymode < time()) {
                // Expired holiday mode - invalidate it.
                $supporter->holidaymode = 0;
                $DB->set_field('local_helpdesk_supporters', 'holidaymode', 0, $supporterconditions);
            }
        }
    }
}

echo $OUTPUT->header();

if (!$issupportteam) {
    echo $OUTPUT->render_from_template('local_helpdesk/alert', [
        'content' => get_string('missing_permission', 'local_helpdesk'),
        'type' => 'danger',
        'url' => new moodle_url('/my'),
    ]);
} else {
    // Holiday mode was already handled before any output was sent, this only renders it.
    if (!empty($supporter->id) && !empty($holidaymodeform)) {
        $supporter->holidayform = $holidaymodeform->render();
        $supporter->wwwroot = $CFG->wwwroot;
        $supporter->uniqid = uniqid();
        $supporter->sesskey = sesskey();
        echo $OUTPUT->render_from_template('local_helpdesk/holidaymode', $supporter);
    }
    $issuelist = new \local_helpdesk\output\issue_list($USER->id);
    echo $OUTPUT->render_from_template('local_helpdesk/issues', $issuelist->export_for_template($OUTPUT));
}

echo $OUTPUT->footer();
