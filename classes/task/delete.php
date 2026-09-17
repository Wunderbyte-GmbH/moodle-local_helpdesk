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
 * Scheduled task that removes issues closed long enough ago.
 *
 * @package    local_helpdesk
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\task;

/**
 * Scheduled task that removes issues closed long enough ago.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete extends \core\task\scheduled_task {
    /**
     * Get the name shown for this task in the admin screens.
     *
     * @return string
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('cron:deleteexpiredissues:title', 'local_helpdesk');
    }

    /**
     * Delete every issue whose retention time has passed.
     *
     * @param bool $debug whether to report what is being done.
     * @return void
     */
    public function execute($debug = false) {
        $issues = \local_helpdesk\lib::get_expiredissues();
        foreach ($issues as $issue) {
            \local_helpdesk\lib::delete_issue($issue->discussionid);
        }
    }
}
