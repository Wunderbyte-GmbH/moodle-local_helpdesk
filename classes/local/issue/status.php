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
 * The states of a support issue.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\issue;

/**
 * The states of a support issue.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status {
    /** @var int Nobody has looked at the issue yet. */
    const NOT_STARTED = 1;

    /** @var int The support team answered, now the person asking has to. */
    const AWAITING_USER_REPLY = 2;

    /** @var int Somebody is working on the issue. */
    const ONGOING = 3;

    /** @var int The person asking answered, now the support team has to. */
    const AWAITING_SUPPORT_ACTION = 4;

    /** @var int The issue is closed. */
    const CLOSED = 5;

    /**
     * Translates the status number to data used by the template. Returns associative array: ['status' => string, 'class' => string]
     *
     * @param int $status
     * @return array
     */
    public static function to_template(int $status): array {
        switch ($status) {
            case self::NOT_STARTED:
                return ['status' => get_string('status:notstarted', 'local_helpdesk'), 'class' => 'badge badge-danger bg-danger',
                    'stateclass' => 'notstarted'];
                break;
            case self::AWAITING_USER_REPLY:
                return ['status' => get_string('status:awaitinguserreply', 'local_helpdesk'),
                    'class' => 'badge local-helpdesk-badge-brown',
                    'stateclass' => 'awaiting'];
                break;
            case self::ONGOING:
                return ['status' => get_string('status:ongoing', 'local_helpdesk'), 'class' => 'badge badge-success bg-success',
                    'stateclass' => 'ongoing'];
                break;
            case self::AWAITING_SUPPORT_ACTION:
                return ['status' => get_string('status:awaitingsupportaction', 'local_helpdesk'),
                    'class' => 'badge local-helpdesk-badge-orange',
                    'stateclass' => 'awaitingsupportaction'];
                break;
            case self::CLOSED:
                return ['status' => get_string('status:closed', 'local_helpdesk'), 'class' => 'badge badge-success bg-success',
                    'stateclass' => 'closed'];
                break;
        }
        return [];
    }
}
