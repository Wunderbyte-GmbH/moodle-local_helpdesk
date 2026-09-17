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
 * Web service to close an issue.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\external;

use core_external\external_api;
use core_external\external_function_parameters;
use context_system;
use core_external\external_value;
use local_helpdesk\lib;

/**
 * Web service to close an issue.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_issue extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'discussionid'),
        ]);
    }

    /**
     * Close a support issue (discussion).
     *
     * @param int $discussionid The discussion ID to close
     * @return mixed Result of closing the issue
     */
    public static function execute($discussionid) {
        $params = self::validate_parameters(self::execute_parameters(), ['discussionid' => $discussionid]);
        self::validate_context(context_system::instance());
        return lib::close_issue($params['discussionid']);
    }

    /**
     * Returns description of the return value for close_issue.
     *
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful, or error message.');
    }
}
