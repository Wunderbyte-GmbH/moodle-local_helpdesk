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
 * Clean up what the plugin changed outside of its own tables.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Take back the prohibitions that protect the support forums, the support team role and the guest ticket user.
 *
 * The forums, their discussions and the groups made for the people asking stay: they are forum data.
 *
 * @return bool
 */
function xmldb_local_helpdesk_uninstall() {
    global $DB;

    // Without this nobody but an administrator could ever delete or hide these courses again.
    foreach ($DB->get_records('local_helpdesk') as $supportforum) {
        \local_helpdesk\lib::supportforum_managecaps($supportforum->forumid, false);
    }

    $roleid = (int) get_config('local_helpdesk', 'supportteamrole');
    if ($roleid && $DB->record_exists('role', ['id' => $roleid, 'shortname' => 'local_helpdesk'])) {
        // This takes the assignments in the support forums with it.
        delete_role($roleid);
    }

    $guest = $DB->get_record('user', [
        'id' => (int) get_config('local_helpdesk', 'guestuserid'),
        'username' => 'helpdesk_guest_ticket',
        'deleted' => 0,
    ]);
    if ($guest) {
        // The tickets filed in its name stay in the forums, as they do for any deleted user.
        delete_user($guest);
    }

    return true;
}
