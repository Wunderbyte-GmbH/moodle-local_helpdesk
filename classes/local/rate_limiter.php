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
 * Limit how many tickets can be filed from one place.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

use moodle_exception;

/**
 * Counts the tickets of a person, or of an address where there is no person.
 *
 * The count used to live in the session. Tickets can be filed without logging in, and whoever
 * does that on purpose just does not send the session cookie again.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /**
     * Count one more ticket, or refuse it.
     *
     * @return void
     * @throws moodle_exception when the limit of the settings is reached.
     */
    public static function register_ticket(): void {
        global $USER;

        $seconds = (int) get_config('local_helpdesk', 'spamprotectionthreshold');
        $limit = (int) get_config('local_helpdesk', 'spamprotectionlimit');
        if ($seconds <= 0 || $limit <= 0) {
            return;
        }

        if (isloggedin() && !isguestuser()) {
            $key = 'user' . $USER->id;
        } else {
            $key = 'ip' . sha1(getremoteaddr('unknown'));
        }

        $cache = \cache::make('local_helpdesk', 'spamprotect');
        $since = time() - $seconds;
        $log = array_values(array_filter($cache->get($key) ?: [], function ($time) use ($since) {
            return $time >= $since;
        }));
        if (count($log) >= $limit) {
            $cache->set($key, $log);
            throw new moodle_exception('spamprotection:exception', 'local_helpdesk');
        }
        $log[] = time();
        $cache->set($key, $log);
    }
}
