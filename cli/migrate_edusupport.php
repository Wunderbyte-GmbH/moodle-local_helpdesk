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
 * Take over the data of a local_edusupport installation from the command line.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_helpdesk\local\migration\edusupport_migrator;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(['help' => false, 'run' => false], ['h' => 'help']);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    cli_writeln("Take over the data of local_edusupport into local_helpdesk.

Without --run only the number of records that would be migrated is shown.

Options:
    --run       Migrate the data.
-h, --help      Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php local/helpdesk/cli/migrate_edusupport.php --run");
    exit(0);
}

foreach (edusupport_migrator::get_summary() as $identifier => $count) {
    cli_writeln(str_pad($count, 10, ' ', STR_PAD_LEFT) . '  ' . get_string($identifier, 'local_helpdesk'));
}

if ($blocker = edusupport_migrator::get_blocker()) {
    cli_error(get_string($blocker, 'local_helpdesk'));
}

if (!$options['run']) {
    cli_writeln(get_string('migrate:clidryrun', 'local_helpdesk'));
    exit(0);
}

$migrated = edusupport_migrator::migrate();
cli_writeln(get_string('migrate:done', 'local_helpdesk', array_sum($migrated)));
cli_writeln(get_string('migrate:uninstallhint', 'local_helpdesk'));
