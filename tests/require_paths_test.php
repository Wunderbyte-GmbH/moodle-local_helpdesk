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
 * Tests that every file the plugin includes exists.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;

/**
 * Tests that every file the plugin includes exists.
 *
 * A require of a missing file is a fatal error, but only when the code runs, so a page or a
 * dialogue that no other test reaches breaks silently.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class require_paths_test extends advanced_testcase {
    /**
     * Every require and include below $CFG->dirroot or $CFG->libdir points to an existing file.
     *
     * @return void
     */
    public function test_included_files_exist(): void {
        global $CFG;
        $root = dirname(__DIR__);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $pattern = '/\b(?:require|include)(?:_once)?\s*\(?\s*\$CFG->(dirroot|libdir)\s*\.\s*[\'"]([^\'"$]+)[\'"]\s*\)?\s*;/';

        $problems = [];
        $checked = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/vendor/')) {
                continue;
            }
            preg_match_all($pattern, file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);
            foreach ($matches as [, $base, $path]) {
                $checked++;
                $target = ($base === 'libdir' ? $CFG->libdir : $CFG->dirroot) . $path;
                if (!is_file($target)) {
                    $problems[] = substr($file->getPathname(), strlen($root) + 1) . ": \$CFG->$base . '$path' does not exist";
                }
            }
        }

        $this->assertGreaterThan(10, $checked, 'The scan found too few includes, it is probably broken.');
        $this->assertSame([], $problems);
    }
}
