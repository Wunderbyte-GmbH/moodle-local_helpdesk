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
 * The help button in the navigation bar.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

use local_helpdesk\local\supporter\platform_team;
use moodle_url;

/**
 * The help button in the navigation bar.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class help_menu {
    /**
     * The help button for the navigation bar, if the current person may use it.
     *
     * @return string the markup, empty if the button is not offered.
     */
    public static function render_for_navbar(): string {
        global $PAGE;
        $guestmode = get_config('local_helpdesk', 'guestmodeenabled');
        if ((!isloggedin() || isguestuser()) && !$guestmode) {
            return '';
        }
        // The menu comes from a cache as markup, so its buttons are wired up here.
        $PAGE->requires->js_call_amd('local_helpdesk/actions', 'init');
        return self::get_html();
    }

    /**
     * Get the helpbutton menu from cache or generate it.
     * @return string the markup of the help button.
     */
    public static function get_html(): string {
        global $CFG, $OUTPUT;
        $cache = \cache::make('local_helpdesk', 'supportmenu');
        $cachekey = \local_helpdesk\local\supportmenu_cache::key();
        if (!empty($cache->get($cachekey))) {
            return $cache->get($cachekey);
        }

        $extralinksfromconfig = get_config('local_helpdesk', 'extralinks');
        $extralinks = [];
        if (!empty($extralinksfromconfig)) {
            $extralinksfromconfig = explode("\n", $extralinksfromconfig);
            $count = count($extralinksfromconfig);
            for ($a = 0; $a < $count; $a++) {
                $tmp = explode('|', $extralinksfromconfig[$a]);
                $extralink = (object) ['id' => $a];
                if (!empty($tmp[0])) {
                    $extralink->name = $tmp[0];
                }
                if (!empty($tmp[1])) {
                    $extralink->url = $tmp[1];
                }
                if (!empty($tmp[2])) {
                    $extralink->faicon = $tmp[2];
                }
                if (!empty($tmp[3])) {
                    $extralink->target = trim($tmp[3]);
                }
                $extralinks[] = $extralink;
            }
        }

        $showissues = null;
        $issuesurl = null;
        // Anybody who is offered the button has to be let into the page behind it.
        // We only show the "issues" navbar button starting from Moodle 4.0.
        if ($CFG->version >= 2022041900 && platform_team::can_view_issues()) {
            $showissues = true;
            $issuesurl = new moodle_url('/local/helpdesk/issues.php');
        }

        $nav = $OUTPUT->render_from_template(
            'local_helpdesk/helpbutton',
            [
            'extralinks' => $extralinks,
            'hasextralinks' => count($extralinks) > 0,
            'showissues' => $showissues,
            'issuesurl' => $issuesurl,
            ]
        );
        $cache->set($cachekey, $nav);
        return $nav;
    }
}
