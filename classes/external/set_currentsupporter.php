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
 * Web service to hand an issue over to a supporter.
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
 * Web service to hand an issue over to a supporter.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_currentsupporter extends external_api {
    /**
     * Returns description of method parameters for set_currentsupporter.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
                'supporterid' => new external_value(PARAM_INT, 'supporterid (userid)'),
            ]
        );
    }

    /**
     * Set the current supporter for a discussion.
     *
     * @param int $discussionid The discussion ID
     * @param int $supporterid The supporter user ID to assign
     * @return int 1 when the issue was handed over.
     * @throws \moodle_exception when the assignment is not allowed.
     */
    public static function execute($discussionid, $supporterid) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['discussionid' => $discussionid, 'supporterid' => $supporterid]
        );
        self::validate_context(context_system::instance());
        // Report a refusal as an exception, so the caller gets the reason instead of a bare failure.
        $error = lib::validate_supporter_assignment($params['discussionid'], $params['supporterid']);
        if ($error !== null) {
            throw new \moodle_exception($error, 'local_helpdesk');
        }
        lib::set_current_supporter($params['discussionid'], $params['supporterid']);
        return 1;
    }

    /**
     * Returns description of the return value for set_currentsupporter.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful.');
    }
}
