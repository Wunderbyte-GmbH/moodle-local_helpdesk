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
 * Callbacks that Moodle core calls in lib.php. Each one hands over to a class of the plugin.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_helpdesk\local\forum_attachments;
use local_helpdesk\local\help_menu;
use local_helpdesk\local\navigation;
use local_helpdesk\local\supportforum\registry;

/**
 * Extend the global navigation.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @return void
 */
function local_helpdesk_extend_navigation(navigation_node $navigation): void {
    navigation::extend_global($navigation);
}

/**
 * Extend course navigation with helpdesk nodes.
 *
 * @param navigation_node $parentnode The navigation node to add items to
 * @param stdClass $course The course object
 * @param context $context The context object
 * @return void
 */
function local_helpdesk_extend_navigation_course(navigation_node $parentnode, stdClass $course, context $context): void {
    navigation::extend_course($parentnode, $course);
}

/**
 * Serve the attachments of support forum posts to the support team.
 *
 * @param stdClass|null $course course object, null outside of courses
 * @param stdClass|null $cm course module object, null outside of modules
 * @param context $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function local_helpdesk_pluginfile(
    ?stdClass $course,
    ?stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    if ($course === null || $cm === null) {
        return false;
    }
    return forum_attachments::serve($course, $cm, $context, $filearea, $args, $options);
}

/**
 * Unregister the support forums of a course category that is about to be deleted.
 *
 * @param stdClass $category the course category.
 * @return void
 */
function local_helpdesk_pre_course_category_delete(stdClass $category): void {
    registry::disable_in_category($category->id);
}

/**
 * Unregister a forum that is about to be deleted.
 *
 * @param stdClass $cm the course module.
 * @return void
 */
function local_helpdesk_pre_course_module_delete(stdClass $cm): void {
    registry::disable_for_course_module($cm);
}

/**
 * Render the help button in the navigation bar.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_helpdesk_render_navbar_output(renderer_base $renderer): string {
    return help_menu::render_for_navbar();
}
