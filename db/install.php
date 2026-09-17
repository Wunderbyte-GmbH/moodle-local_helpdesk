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
 * Install time setup for local_helpdesk.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learning Management (https://www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set up the role and capabilities the support team needs.
 *
 * @return void
 */
function xmldb_local_helpdesk_install() {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => 'local_helpdesk']);
    if (empty($role->id)) {
        $roleid = create_role(
            'Helpdesk Team',
            'local_helpdesk',
            'This role was automatically created by the local_helpdesk Plugin'
        );
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
    }

    set_config('supportteamrole', $role->id, 'local_helpdesk');

    // Skip the dummy guest user on test sites: it becomes part of the site snapshot and
    // pollutes user-table fixtures (e.g. record-count assertions in behat suites of other
    // plugins). It is created on demand by guest_supportuser wherever it is needed.
    if (!defined('BEHAT_SITE_RUNNING') && !defined('BEHAT_UTIL') && !defined('PHPUNIT_TEST')) {
        $guestuser = new local_helpdesk\guest_supportuser();
        $guestuser->create_guestuser_if_inextistant();
    }

    // Ensure, that this role is assigned in the required context levels.
    $levels = get_role_contextlevels($role->id);
    if (!in_array(CONTEXT_MODULE, $levels)) {
        set_role_contextlevels($role->id, array_merge($levels, [CONTEXT_MODULE]));
    }

    // Ensure, that this role has the required capabilities.
    $ctx = \context_system::instance();
    $caps = [
        'forumreport/summary:view',
        'mod/forum:addnews',
        'mod/forum:addquestion',
        'mod/forum:allowforcesubscribe',
        'mod/forum:canoverridecutoff',
        'mod/forum:canoverridediscussionlock',
        'mod/forum:canposttomygroups',
        'mod/forum:createattachment',
        'mod/forum:deleteownpost',
        'mod/forum:exportdiscussion',
        'mod/forum:exportforum',
        'mod/forum:exportownpost',
        'mod/forum:exportpost',
        'mod/forum:grade',
        'mod/forum:managesubscriptions',
        'mod/forum:postprivatereply',
        'mod/forum:postwithoutthrottling',
        'mod/forum:rate',
        'mod/forum:readprivatereplies',
        'mod/forum:replynews',
        'mod/forum:replypost',
        'mod/forum:viewallratings',
        'mod/forum:viewanyrating',
        'mod/forum:viewdiscussion',
        'mod/forum:viewhiddentimedposts',
        'mod/forum:viewqandawithoutposting',
        'mod/forum:viewrating',
        'mod/forum:viewsubscribers',
        'moodle/course:view',
        'moodle/course:viewhiddencourses',
        'moodle/course:viewhiddensections',
        'moodle/course:viewhiddenuserfields',
        'moodle/course:viewparticipants',
        'moodle/site:accessallgroups',
        'moodle/user:readuserposts',
    ];
    foreach ($caps as $cap) {
        if (get_capability_info($cap)) {
            assign_capability($cap, CAP_ALLOW, $role->id, $ctx->id);
        }
    }
}
