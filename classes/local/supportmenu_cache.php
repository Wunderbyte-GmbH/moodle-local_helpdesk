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
 * The cache of the rendered help menu.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\local;

/**
 * The help menu is cached the way it was rendered for a person, in the language it was rendered in.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supportmenu_cache {
    /**
     * The key of the current user's menu.
     *
     * @return string
     */
    public static function key(): string {
        global $USER;
        return (int) $USER->id . '_' . current_language();
    }

    /**
     * Forget every rendered menu, e.g. after a setting that shows in it was changed.
     *
     * @return void
     */
    public static function purge(): void {
        \cache_helper::purge_by_event('local_helpdesk_setbacksupportmenu');
    }
}
