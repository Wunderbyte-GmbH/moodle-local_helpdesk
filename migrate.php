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
 * Take over the data of a local_edusupport installation.
 *
 * @package    local_helpdesk
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_helpdesk\local\migration\edusupport_migrator;

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$apply = optional_param('apply', 0, PARAM_BOOL);

admin_externalpage_setup('local_helpdesk_migrate');

$url = new moodle_url('/local/helpdesk/migrate.php');

if ($apply) {
    require_sesskey();
    core_php_time_limit::raise();
    $migrated = edusupport_migrator::migrate();
    redirect(
        $url,
        get_string('migrate:done', 'local_helpdesk', array_sum($migrated)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('migrate', 'local_helpdesk'));

$migratedon = get_config('local_helpdesk', 'migratedfromedusupport');
if ($migratedon) {
    echo $OUTPUT->notification(get_string('migrate:alreadydone', 'local_helpdesk', userdate($migratedon)), 'success');
    if (core_plugin_manager::instance()->get_plugin_info('local_edusupport')) {
        echo html_writer::tag('p', get_string('migrate:uninstallhint', 'local_helpdesk'));
        echo $OUTPUT->single_button(
            new moodle_url('/admin/plugins.php', ['uninstall' => 'local_edusupport', 'sesskey' => sesskey()]),
            get_string('migrate:uninstall', 'local_helpdesk'),
            'get'
        );
    }
    echo $OUTPUT->footer();
    die;
}

echo html_writer::tag('p', get_string('migrate:description', 'local_helpdesk'));

$blocker = edusupport_migrator::get_blocker();
if ($blocker === 'migrate:nosource') {
    echo $OUTPUT->notification(get_string($blocker, 'local_helpdesk'), 'info');
    echo $OUTPUT->footer();
    die;
}

$table = new html_table();
$table->head = [get_string('migrate:what', 'local_helpdesk'), get_string('migrate:records', 'local_helpdesk')];
$table->attributes['class'] = 'generaltable';
foreach (edusupport_migrator::get_summary() as $identifier => $count) {
    $table->data[] = [get_string($identifier, 'local_helpdesk'), $count];
}
echo html_writer::table($table);

if ($blocker) {
    echo $OUTPUT->notification(get_string($blocker, 'local_helpdesk'), 'error');
} else {
    echo $OUTPUT->single_button(
        new moodle_url($url, ['apply' => 1, 'sesskey' => sesskey()]),
        get_string('migrate:apply', 'local_helpdesk'),
        'post',
        \single_button::BUTTON_PRIMARY
    );
}

echo $OUTPUT->footer();
