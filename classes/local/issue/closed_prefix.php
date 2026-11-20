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
 * The marker in the name of a discussion that shows its issue is closed.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\issue;

/**
 * The marker in the name of a discussion that shows its issue is closed.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class closed_prefix {
    /** @var string Prefix that marks a discussion name as a closed issue. */
    const PREFIX = "🔒 ";

    /** @var array Prefixes used by earlier versions, still present in existing discussion names. */
    const LEGACY = ["[Closed] "];

    /**
     * Add the closed-marker to a discussion name.
     *
     * Any previously set marker - including the ones used by earlier versions - is removed first,
     * so the name never ends up carrying two prefixes.
     *
     * @param string $name the discussion name.
     * @return string the name including the closed-marker.
     */
    public static function add(string $name): string {
        return self::PREFIX . self::strip($name);
    }

    /**
     * Remove the closed-marker from a discussion name.
     *
     * Also removes the markers used by earlier versions, so issues that were closed before the
     * marker changed can still be reopened cleanly.
     *
     * @param string $name the discussion name.
     * @return string the name without any closed-marker.
     */
    public static function strip(string $name): string {
        $prefixes = array_merge([self::PREFIX], self::LEGACY);
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($name, $prefix)) {
                return substr($name, strlen($prefix));
            }
        }
        return $name;
    }

    /**
     * Rewrite discussion names that still carry the closed-marker of an earlier version.
     *
     * Only discussions that are registered as an issue are touched.
     *
     * @return int the number of discussions that were changed.
     */
    public static function migrate_legacy(): int {
        global $DB;
        $changed = 0;
        foreach (self::LEGACY as $legacyprefix) {
            $like = $DB->sql_like('fd.name', ':prefix');
            $sql = "SELECT fd.id, fd.name
                      FROM {forum_discussions} fd
                      JOIN {local_helpdesk_issues} lei ON lei.discussionid = fd.id
                     WHERE $like";
            $params = ['prefix' => $DB->sql_like_escape($legacyprefix) . '%'];
            $discussions = $DB->get_recordset_sql($sql, $params);
            foreach ($discussions as $discussion) {
                // The sql_like() match is case insensitive on most databases, so confirm it here.
                if (!str_starts_with($discussion->name, $legacyprefix)) {
                    continue;
                }
                $DB->set_field(
                    'forum_discussions',
                    'name',
                    self::add($discussion->name),
                    ['id' => $discussion->id]
                );
                $changed++;
            }
            $discussions->close();
        }
        return $changed;
    }
}
