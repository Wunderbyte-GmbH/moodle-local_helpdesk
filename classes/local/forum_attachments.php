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
 * Serves the attachments of support forum posts to the support team.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

use context;
use local_helpdesk\local\supporter\course_support;
use local_helpdesk\local\supporter\platform_team;
use local_helpdesk\local\supportforum\registry;
use stdClass;

/**
 * Serves the attachments of support forum posts to the support team.
 *
 * The platform team is not enrolled in the support courses, so the files of mod_forum would refuse
 * them. The issue page links the attachments here instead, which checks membership of the team.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_attachments {
    /**
     * Send an attachment of a post in a support forum.
     *
     * @param stdClass $course the course of the forum.
     * @param stdClass $cm the course module of the forum.
     * @param context $context the module context.
     * @param string $filearea
     * @param array $args the post id followed by the path of the file.
     * @param array $options options for send_stored_file().
     * @return bool false if the file cannot be served; if it can, it is sent and this does not return.
     */
    public static function serve(
        stdClass $course,
        stdClass $cm,
        context $context,
        string $filearea,
        array $args,
        array $options
    ): bool {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        if ($context->contextlevel != CONTEXT_MODULE) {
            return false;
        }

        // Instead of requiring course login we check if the current user supports this course.
        if (!platform_team::is_member($USER->id) && !course_support::is_member($USER->id, $course->id)) {
            return false;
        }

        $postid = (int) array_shift($args);
        if (!$post = $DB->get_record('forum_posts', ['id' => $postid])) {
            return false;
        }

        // Filearea must contain a real area.
        $areas = forum_get_file_areas($course, $cm, $context);
        if (!isset($areas[$filearea])) {
            return false;
        }

        if (!$discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion])) {
            return false;
        }
        if (!registry::is_supportforum($discussion->forum)) {
            return false;
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_forum/$filearea/$postid/$relativepath";
        if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
            return false;
        }

        // Download MUST be forced - security!
        send_stored_file($file, 0, 0, true, $options);
        return true;
    }
}
