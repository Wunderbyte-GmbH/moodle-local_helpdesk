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
 * Web service listing the people an issue can be handed over to.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use local_helpdesk\lib;

/**
 * Web service listing the people an issue can be handed over to.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_potentialsupporters extends external_api {
    /**
     * Returns description of method parameters for get_potentialsupporters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
            ]
        );
    }

    /**
     * Get potential supporters for a discussion.
     *
     * @param int $discussionid The discussion ID
     * @return string JSON encoded array of potential supporters grouped by support level
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function execute(int $discussionid) {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['discussionid' => $discussionid]);
        self::validate_context(context_system::instance());
        // The team is nobody's business but its own.
        if (!lib::can_view_issues()) {
            throw new \moodle_exception('missing_permission', 'local_helpdesk');
        }
        $reply = ['supporters' => []];

        // A ticket is handed over inside the platform team, so only that team is offered.
        $sql = "SELECT s.userid, u.firstname, u.lastname, s.supportlevel
                    FROM {user} u
                    JOIN {local_helpdesk_supporters} s ON s.userid = u.id
                    WHERE s.courseid = :courseid
                        AND u.deleted = 0
                    ORDER BY u.lastname ASC, u.firstname ASC";
        $supporters = $DB->get_records_sql($sql, ['courseid' => lib::SYSTEM_COURSE_ID]);
        foreach ($supporters as $supporter) {
            if (empty($supporter->supportlevel)) {
                $supporter->supportlevel = get_string('label:2ndlevel', 'local_helpdesk');
            }
            if (!isset($reply['supporters'][$supporter->supportlevel])) {
                $reply['supporters'][$supporter->supportlevel] = [];
            }
            if (isset($supporter->userid) && $supporter->userid == $USER->id) {
                $supporter->selected = true;
            }

            $reply['supporters'][$supporter->supportlevel][] = $supporter;
        }

        return json_encode($reply, JSON_NUMERIC_CHECK);
    }

    /**
     * Returns description of the return value for get_potentialsupporters.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'Returns a json encoded array containing potential supporters.');
    }
}
