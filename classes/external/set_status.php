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
 * Web service to set the status of an issue.
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
 * Web service to set the status of an issue.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_status extends external_api {
    /**
     * Returns description of method parameters for set_status.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'status' => new external_value(PARAM_INT, 'status'),
            'issueid' => new external_value(PARAM_INT, 'issueid'),
        ]);
    }

    /**
     * Set the status of a support issue.
     *
     * @param int $status The status value to set
     * @param int $issueid The issue (discussion) ID
     * @return int Returns 1 if successful, 0 if no permissions
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws require_login_exception
     */
    public static function execute(int $status, int $issueid) {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['status' => $status, 'issueid' => $issueid]);
        self::validate_context(context_system::instance());
        $known = [
            lib::STATUS_NOTSTARTED,
            lib::STATUS_AWAITING_USER_REPLY,
            lib::STATUS_ONGOING,
            lib::STATUS_AWAITING_SUPPORT_ACTION,
            lib::STATUS_CLOSED,
        ];
        if (lib::can_view_issues($USER->id) && in_array($params['status'], $known)) {
            lib::set_status($params['status'], $params['issueid']);
            return 1;
        }
        return 0;
    }

    /**
     * Returns description of the return value for set_status.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }
}
