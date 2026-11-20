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
 * Brings the supporter table into a shape that tolerates a unique key on (courseid, userid).
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local\supporter;

/**
 * Brings the supporter table into a shape that tolerates a unique key on (courseid, userid).
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deduplicator {
    /**
     * Bring the supporter table into a shape that tolerates a unique key on (courseid, userid).
     *
     * Duplicates are possible in existing data because nothing ever enforced uniqueness and
     * because set_supporter() decided on an existing row by looking at supportlevel rather
     * than at the row id. Order matters here: rows have to be normalised and merged before
     * the index is added, or the upgrade dies half way through.
     *
     * @return int the number of rows that were removed.
     */
    public static function run(): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // A courseid of 0 was never a course. It always meant the platform wide team.
        $DB->set_field('local_helpdesk_supporters', 'courseid', platform_team::COURSE_ID, ['courseid' => 0]);

        $removed = 0;

        // Rows pointing at users or courses that no longer exist.
        $orphans = "SELECT s.id
                      FROM {local_helpdesk_supporters} s
                 LEFT JOIN {user} u ON u.id = s.userid
                 LEFT JOIN {course} c ON c.id = s.courseid
                     WHERE u.id IS NULL
                        OR u.deleted = 1
                        OR (s.courseid <> :systemcourseid AND c.id IS NULL)";
        $orphanids = array_keys($DB->get_records_sql($orphans, ['systemcourseid' => platform_team::COURSE_ID]));
        if (!empty($orphanids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($orphanids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_helpdesk_supporters', "id $insql", $inparams);
            $removed += count($orphanids);
        }

        // Merge what is left, one group of (courseid, userid) at a time.
        $groups = $DB->get_records_sql(
            "SELECT MIN(id) AS lowestid, courseid, userid, COUNT(*) AS duplicates
               FROM {local_helpdesk_supporters}
           GROUP BY courseid, userid
             HAVING COUNT(*) > 1"
        );
        foreach ($groups as $group) {
            $rows = $DB->get_records(
                'local_helpdesk_supporters',
                ['courseid' => $group->courseid, 'userid' => $group->userid],
                'id ASC'
            );

            // The support level is a free text label without meaning to the code, but keeping
            // a filled one loses less than an empty one. Among equals the oldest row wins.
            $winner = null;
            foreach ($rows as $row) {
                if ($winner === null || ($winner->supportlevel === '' && $row->supportlevel !== '')) {
                    $winner = $row;
                }
            }

            // An ongoing holiday must survive the merge, and so must being assignable -
            // losing either would quietly change who escalation can reach.
            $holidaymode = 0;
            $autoassign = 0;
            foreach ($rows as $row) {
                $holidaymode = max($holidaymode, (int) $row->holidaymode);
                $autoassign = max($autoassign, (int) $row->autoassign);
            }
            if ((int) $winner->holidaymode !== $holidaymode) {
                $DB->set_field('local_helpdesk_supporters', 'holidaymode', $holidaymode, ['id' => $winner->id]);
            }
            if ((int) $winner->autoassign !== $autoassign) {
                $DB->set_field('local_helpdesk_supporters', 'autoassign', $autoassign, ['id' => $winner->id]);
            }

            $DB->delete_records_select(
                'local_helpdesk_supporters',
                'courseid = :courseid AND userid = :userid AND id <> :winner',
                ['courseid' => $group->courseid, 'userid' => $group->userid, 'winner' => $winner->id]
            );
            $removed += count($rows) - 1;
        }

        $transaction->allow_commit();

        return $removed;
    }
}
