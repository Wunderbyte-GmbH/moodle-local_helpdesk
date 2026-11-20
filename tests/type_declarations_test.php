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
 * Tests that the code of the plugin declares its parameter and return types.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

use advanced_testcase;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Tests that the code of the plugin declares its parameter and return types.
 *
 * The rules:
 * - every parameter has a type, unless the method overrides a method of core whose parameter has none
 *   (PHP does not allow an override to narrow a parameter type);
 * - every function and method, constructors aside, has a return type;
 * - a return type is a single type. "Nothing" is expressed by a nullable type (?stdClass), not by a union
 *   such as bool|stdClass.
 *
 * @package    local_helpdesk
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class type_declarations_test extends advanced_testcase {
    /**
     * Classes, global functions and the callbacks in db/ follow the rules.
     *
     * @return void
     */
    public function test_types_are_declared(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/local/helpdesk/lib.php');
        require_once($CFG->dirroot . '/local/helpdesk/db/install.php');
        require_once($CFG->dirroot . '/local/helpdesk/db/upgrade.php');
        require_once($CFG->dirroot . '/local/helpdesk/db/uninstall.php');

        $problems = [];
        $checked = 0;
        foreach ($this->get_plugin_classes() as $classname) {
            $class = new ReflectionClass($classname);
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $classname) {
                    continue;
                }
                $checked++;
                $problems = array_merge(
                    $problems,
                    $this->check($method, $classname . '::' . $method->getName() . '()', $this->get_parent_method($method))
                );
            }
        }
        foreach (get_defined_functions()['user'] as $function) {
            if (preg_match('/^(xmldb_)?local_helpdesk_/', $function)) {
                $checked++;
                $problems = array_merge($problems, $this->check(new \ReflectionFunction($function), $function . '()', null));
            }
        }

        $this->assertGreaterThan(50, $checked, 'The scan found too little code, it is probably broken.');
        $this->assertSame([], $problems);
    }

    /**
     * The problems of one function or method.
     *
     * @param ReflectionFunctionAbstract $function
     * @param string $name how to name it in the report.
     * @param ReflectionMethod|null $parent the method it overrides or implements, if any.
     * @return string[]
     */
    private function check(ReflectionFunctionAbstract $function, string $name, ?ReflectionMethod $parent): array {
        $problems = [];
        $parentparams = $parent ? $parent->getParameters() : [];
        foreach ($function->getParameters() as $position => $parameter) {
            $inherited = isset($parentparams[$position]) && !$parentparams[$position]->hasType();
            if (!$parameter->hasType() && !$inherited) {
                $problems[] = "$name: parameter \${$parameter->getName()} has no type";
            }
        }
        if ($function->getName() === '__construct') {
            return $problems;
        }
        if (!$function->hasReturnType()) {
            $problems[] = "$name: no return type";
        } else if (!$function->getReturnType() instanceof ReflectionNamedType) {
            $problems[] = "$name: return type {$function->getReturnType()} is not a single type";
        } else if ($function->getReturnType()->getName() === 'mixed') {
            $problems[] = "$name: return type mixed is not a single type";
        }
        return $problems;
    }

    /**
     * The method of a parent class or interface that a method overrides or implements.
     *
     * @param ReflectionMethod $method
     * @return ReflectionMethod|null
     */
    private function get_parent_method(ReflectionMethod $method): ?ReflectionMethod {
        try {
            return $method->getPrototype();
        } catch (ReflectionException $e) {
            // Not declared by an interface or abstract parent, but it may still override a parent method.
            $parent = $method->getDeclaringClass()->getParentClass();
            return ($parent && $parent->hasMethod($method->getName())) ? $parent->getMethod($method->getName()) : null;
        }
    }

    /**
     * The names of all classes of the plugin.
     *
     * @return string[]
     */
    private function get_plugin_classes(): array {
        $root = dirname(__DIR__) . '/classes/';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $classes = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $classname = 'local_helpdesk\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));
            $this->assertTrue(
                class_exists($classname) || interface_exists($classname) || trait_exists($classname),
                "$classname cannot be loaded"
            );
            $classes[] = $classname;
        }
        return $classes;
    }
}
