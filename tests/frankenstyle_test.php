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
 * Tests that the plugin defines no global function without its frankenstyle prefix.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use basic_testcase;

/**
 * Tests that the plugin defines no global function without its frankenstyle prefix.
 *
 * A global function named like one of another plugin or of core is a fatal error on every site
 * that has both, so every function outside a class has to start with local_helpdesk_ (or
 * xmldb_local_helpdesk_ for the install, upgrade and uninstall callbacks).
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class frankenstyle_test extends basic_testcase {
    /**
     * Every global function of the plugin carries the prefix.
     *
     * @return void
     */
    public function test_global_functions_are_prefixed(): void {
        $root = dirname(__DIR__);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $checked = 0;
        $unprefixed = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = substr($file->getPathname(), strlen($root) + 1);
            foreach ($this->get_global_functions(file_get_contents($file->getPathname())) as $function) {
                $checked++;
                if (!preg_match('/^(xmldb_)?local_helpdesk_/', $function)) {
                    $unprefixed[] = "$path: $function()";
                }
            }
        }

        // The callbacks in lib.php alone are several functions, so finding none means the scan is broken.
        $this->assertGreaterThan(0, $checked);
        $this->assertSame([], $unprefixed);
    }

    /**
     * The names of the functions a file declares outside of classes, interfaces, traits and enums.
     *
     * @param string $code PHP source.
     * @return string[]
     */
    private function get_global_functions(string $code): array {
        $tokens = \PhpToken::tokenize($code);
        $functions = [];
        $depth = 0;
        // Depth of the braces at which a class-like body starts, innermost last.
        $classdepths = [];
        $pendingclass = false;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                // Not the "class" of Foo::class.
                $previous = $this->previous_significant($tokens, $i);
                if (!$previous || !$previous->is(T_DOUBLE_COLON)) {
                    $pendingclass = true;
                }
            } else if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
                if ($pendingclass) {
                    $classdepths[] = $depth;
                    $pendingclass = false;
                }
            } else if ($token->text === '}') {
                if ($classdepths && end($classdepths) === $depth) {
                    array_pop($classdepths);
                }
                $depth--;
            } else if ($token->is(T_FUNCTION) && !$classdepths) {
                $name = $this->next_significant($tokens, $i);
                // Closures have no name.
                if ($name && $name->is(T_STRING)) {
                    $functions[] = $name->text;
                }
            }
        }
        return $functions;
    }

    /**
     * The next token that is not white space or a comment.
     *
     * @param \PhpToken[] $tokens
     * @param int $index
     * @return \PhpToken|null
     */
    private function next_significant(array $tokens, int $index): ?\PhpToken {
        $count = count($tokens);
        for ($i = $index + 1; $i < $count; $i++) {
            if (!$tokens[$i]->isIgnorable() && $tokens[$i]->text !== '&') {
                return $tokens[$i];
            }
        }
        return null;
    }

    /**
     * The previous token that is not white space or a comment.
     *
     * @param \PhpToken[] $tokens
     * @param int $index
     * @return \PhpToken|null
     */
    private function previous_significant(array $tokens, int $index): ?\PhpToken {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }
        return null;
    }
}
