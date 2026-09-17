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
 * Library for the helpdesk plugin.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend navigation.
 *
 * @param navigation_node $navigation The navigation node to extend
 */
function local_helpdesk_extend_navigation($navigation) {
    // This node leads to issues.php, so it follows whoever that page lets in.
    if (\local_helpdesk\lib::can_view_issues()) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)) {
            $nodehome = $navigation;
        }
        $label = get_string('issues', 'local_helpdesk');
        $link = new moodle_url('/local/helpdesk/issues.php', []);
        $icon = new pix_icon('docs', '', '');
        $nodecreatecourse = $nodehome->add($label, $link, navigation_node::NODETYPE_LEAF, $label, 'helpdeskissues', $icon);
        $nodecreatecourse->showinflatnavigation = true;
    }
}

/**
 * Extend course navigation with helpdesk nodes.
 *
 * @param navigation_node $parentnode The navigation node to add items to
 * @param stdClass $course The course object
 * @param context $context The context object
 * @return void
 */
function local_helpdesk_extend_navigation_course($parentnode, $course, $context) {
    // Both nodes land in the "More" menu of the course. That is where a setting used a few
    // times a year belongs, and forcing it makes the placement deterministic rather than a
    // side effect of how many nodes happen to fit next to it.
    if (\local_helpdesk\lib::can_assign_first_level($course->id)) {
        $node = navigation_node::create(
            get_string('coursesupporters', 'local_helpdesk'),
            new moodle_url('/local/helpdesk/coursesupporters.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_helpdesk_coursesupporters',
            new pix_icon('i/users', '')
        );
        $node->set_force_into_more_menu(true);
        $parentnode->add_node($node);
    }

    if (is_siteadmin()) {
        $node = navigation_node::create(
            get_string('supportforum:choose', 'local_helpdesk'),
            new moodle_url('/local/helpdesk/chooseforum.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_helpdesk_chooseforum',
            new pix_icon('i/marker', '')
        );
        $node->set_force_into_more_menu(true);
        $parentnode->add_node($node);
    }
}

/**
 * Serves the forum attachments. Implements needed access control ;-)
 *
 * @package  local_helpdesk --> we fake downloads for mod_forum.
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function local_helpdesk_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/local/helpdesk/classes/lib.php');
    require_once($CFG->dirroot . '/mod/forum/lib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Instead of requiring course login we check if the current user is support user of this discussion!
    if (
        !\local_helpdesk\lib::is_second_level($USER->id)
        && !\local_helpdesk\lib::is_first_level($USER->id, $course->id)
    ) {
        return false;
    }

    $postid = (int)array_shift($args);
    if (!$post = $DB->get_record('forum_posts', ['id' => $postid])) {
        return false;
    }

    $areas = \forum_get_file_areas($course, $cm, $context);

    // Filearea must contain a real area.
    if (!isset($areas[$filearea])) {
        return false;
    }

    if (!$discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion])) {
        return false;
    }

    if (!\local_helpdesk\lib::is_supportforum($discussion->forum)) {
        return false;
    }

    $fs = \get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_forum/$filearea/$postid/$relativepath";
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    // We skip this check, we already checked, that we belong to the supportteam and have access.
    // Make sure groups allow this user to see this file.

    // We skip this check, we already checked, that we belong to the supportteam and have access.
    // Make sure we're allowed to see it...

    // Finally send the file.
    send_stored_file($file, 0, 0, true, $options); // Download MUST be forced - security!
    return true;
}

/**
 * If a course category was deleted we remove all contained support forums.
 *
 * @param stdClass $category the course category.
 */
function local_helpdesk_pre_course_category_delete($category) {
    global $DB;
    $courses = $DB->get_records('course', ['category' => $category->id]);
    foreach ($courses as $course) {
        local_helpdesk_pre_course_delete($course);
    }
}

/**
 * If a course was deleted we remove all contained support forums.
 *
 * @param stdClass $course the course.
 */
function local_helpdesk_pre_course_delete($course) {
    global $DB;
    $supportforums = $DB->get_records('local_helpdesk', ['courseid' => $course->id]);
    foreach ($supportforums as $supportforum) {
        \local_helpdesk\lib::supportforum_disable($supportforum->id);
    }
}
/**
 * If a forum was deleted we remove it as support forum.
 *
 * @param stdClass $cm the course module.
 */
function local_helpdesk_pre_course_module_delete($cm) {
    global $DB;
    $forumtype = $DB->get_record('modules', ['name' => 'forum']);
    if (!empty($forumtype->id) && !empty($cm->module) && $cm->module == $forumtype->id) {
        \local_helpdesk\lib::supportforum_disable($cm->instance);
    }
}

/**
 * Renders the popup.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_helpdesk_render_navbar_output(\renderer_base $renderer) {
    $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
    // Early bail out conditions.
    if (!isloggedin()  && !$guestmode) {
        return '';
    }
    if (isguestuser() && !$guestmode) {
        return '';
    }
    // The menu comes from a cache as markup, so its buttons are wired up here.
    global $PAGE;
    $PAGE->requires->js_call_amd('local_helpdesk/actions', 'init');
    return  \local_helpdesk\lib::get_supportmenu();
}
