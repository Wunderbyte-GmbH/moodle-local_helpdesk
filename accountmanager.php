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
 * Assign the account manager a user turns to for support.
 *
 * @package    local_helpdesk
 * @copyright  2022 Thomas Winkler
 * @author     Thomas Winkler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk;

require_once('../../config.php');

use local_helpdesk\form\accountmanager_form;
use moodle_url;
use stdClass;

$context = \context_system::instance();

// Set PAGE variables.
$PAGE->set_context($context);
$PAGE->set_url($CFG->wwwroot . '/local/helpdesk/accountmanager.php');

// Force the user to login/create an account to access this page.
require_login();

$PAGE->set_pagelayout('admin');
$accountmanager = new accountmanager();

$title = get_string('accountmanagers', 'local_helpdesk');
$heading = get_string('accountmanagers', 'local_helpdesk');
$mform = new accountmanager_form();
$PAGE->set_title($title);
$PAGE->set_heading($heading);

$url = new \moodle_url('/admin/search.php', []);
$PAGE->navbar->add(get_string('administrationsite'), $url);

$url = new \moodle_url('/admin/category.php', ['category' => 'modules']);
$PAGE->navbar->add(get_string('plugins', 'core_admin'), $url);

$url = new \moodle_url('/admin/category.php', ['category' => 'localplugins']);
$PAGE->navbar->add(get_string('localplugins'), $url);

$url = new \moodle_url('/admin/settings.php', ['section' => 'local_helpdesk_settings']);
$PAGE->navbar->add(get_string('pluginname', 'local_helpdesk'), $url);

$PAGE->navbar->add(get_string('supporters', 'local_helpdesk'), $PAGE->url);

if (!is_siteadmin()) {
    $tourl = new moodle_url('/my', []);
    echo $OUTPUT->render_from_template('local_helpdesk/alert', [
        'content' => get_string('missing_permission', 'local_helpdesk'),
        'type' => 'danger',
        'url' => $tourl->__toString(),
    ]);
}
if ($mform->is_cancelled()) {
    redirect($url);
} else if ($data = $mform->get_data()) {
    $accountmanager->form_to_config_helpdesk_accountmanager($data->possiblemanagers, $data->capstocheck);
}
echo $OUTPUT->header();
$mform->set_data(new stdClass());
$mform->display();
echo $OUTPUT->footer();
