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
 * External service definitions for local_helpdesk.
 *
 * @package    local_helpdesk
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// We define the web service functions to install.
$functions = [
    'local_helpdesk_close_issue' => [
        'classname'   => 'local_helpdesk_external',
        'methodname'  => 'close_issue',
        'classpath'   => 'local/helpdesk/externallib.php',
        'description' => 'Close an issue',
        'type'        => 'write',
        'ajax'        => 1,
    ],
    'local_helpdesk_create_issue' => [
        'classname'   => 'local_helpdesk_external',
        'methodname'  => 'create_issue',
        'classpath'   => 'local/helpdesk/externallib.php',
        'description' => 'Post an issue',
        'type'        => 'write',
        'ajax'        => 1,
        'loginrequired' => false,
    ],
    'local_helpdesk_create_form' => [
        'classname'   => 'local_helpdesk_external',
        'methodname'  => 'create_form',
        'classpath'   => 'local/helpdesk/externallib.php',
        'description' => 'Create form to post an issue',
        'type'        => 'read',
        'ajax'        => 1,
        'loginrequired' => false,
    ],
    'local_helpdesk_get_potentialsupporters' => [
        'classname'   => 'local_helpdesk_external',
        'methodname'  => 'get_potentialsupporters',
        'classpath'   => 'local/helpdesk/externallib.php',
        'description' => 'Get potential supporters for a discussion.',
        'type'        => 'read',
        'ajax'        => 1,
    ],
    'local_helpdesk_set_currentsupporter' => [
        'classname'   => 'local_helpdesk_external',
        'methodname'  => 'set_currentsupporter',
        'classpath'   => 'local/helpdesk/externallib.php',
        'description' => 'Set the current supporter of a discussion.',
        'type'        => 'write',
        'ajax'        => 1,
    ],
    'local_helpdesk_set_status' => [
        'classname'   => 'local_helpdesk_external',
        'methodname'  => 'set_status',
        'classpath'   => 'local/helpdesk/externallib.php',
        'description' => 'Sets the supportlevel of a user',
        'type'        => 'write',
        'ajax'        => 1,
    ],
];
