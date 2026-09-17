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
 * The address a guest wants the answers to a ticket sent to.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

/**
 * Keeps the mail address of a guest with the discussion it belongs to.
 *
 * The address used to be read back from the title of the discussion. A title is written by
 * the person filing a ticket, and can be changed by everybody who may edit the post, so it
 * decided where the answers of the support team were sent to.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guest_ticket {
    /** @var string the table. */
    const TABLE = 'local_helpdesk_guesttickets';

    /**
     * Remember where the answers to a discussion go.
     *
     * @param int $discussionid
     * @param string $email
     * @return void
     */
    public static function set_email(int $discussionid, string $email): void {
        global $DB;
        $email = clean_param($email, PARAM_EMAIL);
        if ($email === '' || $DB->record_exists(self::TABLE, ['discussionid' => $discussionid])) {
            return;
        }
        $DB->insert_record(self::TABLE, (object) [
            'discussionid' => $discussionid,
            'email' => $email,
            'timecreated' => time(),
        ]);
    }

    /**
     * Where the answers to a discussion go, if it was filed by a guest.
     *
     * @param int $discussionid
     * @return string|null
     */
    public static function get_email(int $discussionid): ?string {
        global $DB;
        $email = $DB->get_field(self::TABLE, 'email', ['discussionid' => $discussionid]);
        return $email ?: null;
    }

    /**
     * Forget the address of a discussion.
     *
     * @param int $discussionid
     * @return void
     */
    public static function delete(int $discussionid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['discussionid' => $discussionid]);
    }

    /**
     * Take the addresses of older guest tickets out of their titles, once.
     *
     * Only discussions written by the guest ticket user are looked at: nobody else can start
     * a discussion in that name, so these titles were written by the plugin itself.
     *
     * @return int the number of addresses found.
     */
    public static function backfill_from_titles(): int {
        global $DB;
        $guestuserid = (int) get_config('local_helpdesk', 'guestuserid');
        if (!$guestuserid) {
            return 0;
        }
        $sql = "SELECT d.id, d.name
                  FROM {forum_discussions} d
                  JOIN {local_helpdesk} sf ON sf.forumid = d.forum
             LEFT JOIN {" . self::TABLE . "} gt ON gt.discussionid = d.id
                 WHERE d.userid = :userid AND gt.id IS NULL AND " . $DB->sql_like('d.name', ':pattern');
        $discussions = $DB->get_recordset_sql($sql, ['userid' => $guestuserid, 'pattern' => '%[Guestticket: %]%']);
        $found = 0;
        foreach ($discussions as $discussion) {
            if (preg_match('/\[Guestticket: ([^\]\s]+)\]/', $discussion->name, $matches)) {
                $before = $DB->count_records(self::TABLE);
                self::set_email($discussion->id, $matches[1]);
                $found += $DB->count_records(self::TABLE) - $before;
            }
        }
        $discussions->close();
        return $found;
    }
}
