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
 * The list of issues, as the support team sees it.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\output;

use local_helpdesk\lib;
use renderable;
use renderer_base;
use templatable;

/**
 * Collects the issues for the template local_helpdesk/issues.
 *
 * Everything is fetched for all issues at once: the list used to ask the database five to
 * seven times per issue.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_list implements renderable, templatable {
    /** @var int the person looking at the list. */
    protected $userid;

    /**
     * Constructor.
     *
     * @param int $userid the person looking at the list.
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG, $DB;

        $hasprio = get_config('local_helpdesk', 'prioritylvl');
        $data = [
            'current' => [], // Issues the user is responsible for.
            'assigned' => [], // Issues the user receives notifications for.
            'other' => [], // All other issues.
            'closed' => [],
            'wwwroot' => $CFG->wwwroot,
            'userlinks' => get_config('local_helpdesk', 'userlinks'),
            'hasprio' => $hasprio,
            'sesskey' => sesskey(),
        ];

        $issues = $DB->get_records('local_helpdesk_issues', [], 'priority,id,discussionid,status');
        $discussions = $this->get_discussions($issues);
        $lastposters = $this->get_last_posters(array_keys($discussions));
        $watched = $DB->get_records_menu('local_helpdesk_subscr', ['userid' => $this->userid], '', 'discussionid, id');

        $userids = array_merge(
            array_column($discussions, 'userid'),
            $lastposters,
            array_column($issues, 'accountmanager'),
            array_column($issues, 'currentsupporter')
        );
        $names = $this->get_fullnames($userids);

        foreach (array_reverse($issues) as $issue) {
            if (!isset($discussions[$issue->discussionid])) {
                // The discussion is gone, and the issue will follow with the next cleanup.
                continue;
            }
            $discussion = $discussions[$issue->discussionid];
            $issue->name = $discussion->name;
            $issue->userid = $discussion->userid;
            $issue->userfullname = $names[$discussion->userid] ?? '';
            $issue->lastmodified = $issue->timemodified;
            $issue->lastpostuserid = $lastposters[$issue->discussionid] ?? $discussion->userid;
            $issue->lastpostuserfullname = $names[$issue->lastpostuserid] ?? '';
            if (isset($issue->accountmanager)) {
                $issue->accountmanagerfn = $names[$issue->accountmanager] ?? '';
            }
            if (!empty($issue->currentsupporter)) {
                $issue->currentsupportername = $names[$issue->currentsupporter] ?? '';
                $issue->currentsupporterid = $issue->currentsupporter;
            } else {
                $issue->currentsupportername = get_string('label:2ndlevel', 'local_helpdesk');
            }
            $issue->state = lib::status_to_template($issue->status);

            $issue->prio = '';
            $issue->priolow = ($hasprio && $issue->priority <= 1) ? 'active' : '';
            $issue->priomid = ($hasprio && $issue->priority == 2) ? 'active' : '';
            $issue->priohigh = ($hasprio && $issue->priority > 2) ? 'active' : '';

            if ($issue->currentsupporter == $this->userid && $issue->priority > 0) {
                $data['current'][] = $issue;
            } else if (isset($watched[$issue->discussionid])) {
                $data['assigned'][] = $issue;
            } else if ($issue->status != lib::STATUS_CLOSED) {
                $data['other'][] = $issue;
            } else {
                $data['closed'][] = $issue;
            }
        }

        $data['count'] = [
            'current' => count($data['current']),
            'assigned' => count($data['assigned']),
            'other' => count($data['other']),
            'closed' => count($data['closed']),
        ];
        $data['accountmanagerenabled'] = !empty(get_config('local_helpdesk', 'accountmanagers'));
        // The heading rows of the groups span the whole table, which has one column more with account managers.
        $data['columncount'] = $data['accountmanagerenabled'] ? 6 : 5;

        return $data;
    }

    /**
     * The discussions behind the issues.
     *
     * @param \stdClass[] $issues
     * @return \stdClass[] by discussion id.
     */
    protected function get_discussions(array $issues): array {
        global $DB;
        $discussionids = array_column($issues, 'discussionid');
        if (!$discussionids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($discussionids);
        return $DB->get_records_select('forum_discussions', "id $insql", $params, '', 'id, name, userid');
    }

    /**
     * Who wrote last in each discussion.
     *
     * @param int[] $discussionids
     * @return int[] discussion id => user id.
     */
    protected function get_last_posters(array $discussionids): array {
        global $DB;
        if (!$discussionids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($discussionids);
        $sql = "SELECT p.id, p.discussion, p.userid
                  FROM {forum_posts} p
                  JOIN (SELECT discussion, MAX(modified) AS lastmodified
                          FROM {forum_posts}
                         WHERE discussion $insql
                      GROUP BY discussion) latest
                    ON latest.discussion = p.discussion AND latest.lastmodified = p.modified
              ORDER BY p.id ASC";
        $lastposters = [];
        foreach ($DB->get_records_sql($sql, $params) as $post) {
            // Of two posts written in the same second the later one counts.
            $lastposters[$post->discussion] = $post->userid;
        }
        return $lastposters;
    }

    /**
     * The names of the people the list mentions.
     *
     * @param array $userids may hold empty values and duplicates.
     * @return string[] user id => full name.
     */
    protected function get_fullnames(array $userids): array {
        global $DB;
        $userids = array_unique(array_filter(array_map('intval', $userids)));
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $fields = implode(', ', \core_user\fields::get_name_fields());
        $names = [];
        foreach ($DB->get_records_select('user', "id $insql", $params, '', 'id, ' . $fields) as $user) {
            $names[$user->id] = fullname($user);
        }
        return $names;
    }
}
