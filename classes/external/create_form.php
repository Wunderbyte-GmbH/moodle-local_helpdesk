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
 * Web service to get the form a support request is filed with.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use local_helpdesk\form\issue_create_form;
use local_helpdesk\lib;

/**
 * Web service to get the form a support request is filed with.
 *
 * @package    local_helpdesk
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_form extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'url' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image or empty'),
            'forumid' => new external_value(PARAM_INT, 'forumid the form is for'),
        ]);
    }

    /**
     * Create the form for submitting support requests. The form will be displayed in a modal.
     *
     * @param string $url The URL where the error happened
     * @param string $image Base64 encoded image or empty string
     * @param int $forumid The forum ID the form is for
     * @return string The rendered form HTML
     */
    public static function execute($url, $image, $forumid): string {
        global $PAGE, $USER, $OUTPUT;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['url' => $url, 'image' => $image, 'forumid' => $forumid]
        );

        if (isloggedin() && !isguestuser()) {
            self::validate_context(context_system::instance());
        } else {
            // Requests can be filed without logging in, if the guest mode is on.
            $PAGE->set_context(context_system::instance());
        }

        lib::before_popup();

        $params['contactphone'] = $USER->phone1;
        $form = new issue_create_form(null, null, 'post', '_self', ['id' => 'local_helpdesk_create_form'], true);
        $form->set_data((object) $params);
        $prepageenabled = get_config('local_helpdesk', 'enableprepage');
        $prepage = get_config('local_helpdesk', 'prepage');
        if ($prepageenabled && $prepage) {
            $templatedata['prepage'] = format_text($prepage, FORMAT_HTML);
            $templatedata['form'] = $form->render();
            $output = $OUTPUT->render_from_template('local_helpdesk/prepageenabled', $templatedata);
        } else {
            $output = $form->render();
        }
        return $output;
    }

    /**
     * Return definition.
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'Returns the form as html');
    }
}
