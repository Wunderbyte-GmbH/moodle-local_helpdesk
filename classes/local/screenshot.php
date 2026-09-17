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
 * The screenshot that comes with a ticket.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

use moodle_exception;

/**
 * Turns the data URL sent by the browser into a file, if it is a picture of acceptable size.
 *
 * The web service can be called without the form, and without logging in, so nothing the form
 * promises about the upload holds here.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class screenshot {
    /** @var int[] the picture types we take, and their file extension. */
    const TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];

    /** @var int never take more than this, whatever the site allows. */
    const MAXBYTES = 10485760;

    /** @var string the decoded picture. */
    protected $content;

    /** @var string the file name, with the extension of what the picture really is. */
    protected $filename;

    /**
     * Read a data URL.
     *
     * @param string $dataurl what the browser sent, e.g. data:image/png;base64,....
     * @param string $filename the name the browser suggests.
     * @throws moodle_exception if this is not a picture we take.
     */
    public function __construct(string $dataurl, string $filename = '') {
        global $CFG;

        $maxbytes = min(self::MAXBYTES, get_max_upload_file_size($CFG->maxbytes ?? 0) ?: self::MAXBYTES);
        // Base64 needs four characters for three bytes, so this can be told before decoding.
        if (strlen($dataurl) > $maxbytes * 4 / 3 + 100) {
            throw new moodle_exception('screenshot:toobig', 'local_helpdesk', '', display_size($maxbytes));
        }
        if (!preg_match('#^data:image/[a-z0-9.+-]+;base64,#i', $dataurl, $matches)) {
            throw new moodle_exception('screenshot:invalid', 'local_helpdesk');
        }
        $content = base64_decode(substr($dataurl, strlen($matches[0])), true);
        $info = $content === false ? false : @getimagesizefromstring($content);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            throw new moodle_exception('screenshot:invalid', 'local_helpdesk');
        }
        if (strlen($content) > $maxbytes) {
            throw new moodle_exception('screenshot:toobig', 'local_helpdesk', '', display_size($maxbytes));
        }

        $this->content = $content;
        $name = pathinfo(clean_param($filename, PARAM_FILE), PATHINFO_FILENAME);
        $this->filename = ($name !== '' ? $name : 'screenshot') . '.' . self::TYPES[$info[2]];
    }

    /**
     * The file name to use.
     *
     * @return string
     */
    public function get_filename(): string {
        return $this->filename;
    }

    /**
     * Write the picture into a file nobody can guess the name of.
     *
     * @param bool $keep true if the file has to outlive this request, because a queued mail attaches it.
     *                   Whoever asks for that deletes the file.
     * @return string the path.
     */
    public function write_tempfile(bool $keep = false): string {
        $dir = $keep ? make_temp_directory('local_helpdesk') : make_request_directory();
        $path = $dir . '/' . random_string(32);
        file_put_contents($path, $this->content);
        return $path;
    }
}
