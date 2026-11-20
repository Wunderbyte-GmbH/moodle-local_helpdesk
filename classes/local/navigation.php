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
 * The navigation nodes of the helpdesk.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

use local_helpdesk\local\supporter\course_support;
use local_helpdesk\local\supporter\platform_team;
use moodle_url;
use navigation_node;
use pix_icon;
use stdClass;

/**
 * The navigation nodes of the helpdesk.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Add the link to the issue list for the platform team.
     *
     * @param navigation_node $navigation the global navigation.
     * @return void
     */
    public static function extend_global(navigation_node $navigation): void {
        // This node leads to issues.php, so it follows whoever that page lets in.
        if (!platform_team::can_view_issues()) {
            return;
        }
        $nodehome = $navigation->get('home') ?: $navigation;
        $label = get_string('issues', 'local_helpdesk');
        $link = new moodle_url('/local/helpdesk/issues.php');
        $node = $nodehome->add($label, $link, navigation_node::NODETYPE_LEAF, $label, 'helpdeskissues', new pix_icon('docs', ''));
        $node->showinflatnavigation = true;
    }

    /**
     * Add the pages of the helpdesk to the navigation of a course.
     *
     * Both nodes land in the "More" menu of the course. That is where a setting used a few times a
     * year belongs, and forcing it makes the placement deterministic rather than a side effect of how
     * many nodes happen to fit next to it.
     *
     * @param navigation_node $parentnode the course navigation.
     * @param stdClass $course
     * @return void
     */
    public static function extend_course(navigation_node $parentnode, stdClass $course): void {
        if (course_support::can_assign($course->id)) {
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
}
