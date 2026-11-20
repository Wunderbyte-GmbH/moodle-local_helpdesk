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
 * Form used to file a support request.
 *
 * @package    local_helpdesk
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\form;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . "/formslib.php");

use local_helpdesk\accountmanager;
use moodleform;
use local_helpdesk\local\supportforum\registry;

/**
 * Form used to file a support request.
 *
 * @package    local_helpdesk
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_create_form extends moodleform {
    /** @var int Maximum size of a single attachment in bytes. */
    public $maxbytes = 1024 * 1024;

    /** @var int Maximum size of all attachments of an area in bytes. */
    public $areamaxbytes = 10485760;

    /** @var int Maximum number of attachments. */
    public $maxfiles = 1;

    /** @var int Whether attachments may use subdirectories. */
    public $subdirs = 0;

    /**
     * Define the form.
     *
     * @return void
     */
    public function definition(): void {
        global $CFG, $COURSE, $SITE, $OUTPUT;

        $faqread = get_config('local_helpdesk', 'faqread');
        $faqlink = get_config('local_helpdesk', 'faqlink');
        $disablephonefield = get_config('local_helpdesk', 'phonefield');
        $guestuserallowed = true; // Do we need 'guestuserallowed' from get_config?

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // TODO: MDL-000000 Obsolete forumid remove in the future.
        $mform->addElement('hidden', 'forumid', '');
        $mform->setType('forumid', PARAM_INT);

        $mform->addElement('hidden', 'url', '');
        $mform->setType('url', PARAM_TEXT);
        $mform->addElement('hidden', 'image', ''); // Base64 encoded image.
        $mform->setType('image', PARAM_RAW);

        $mform->addElement('header', 'header', get_string('header', 'local_helpdesk', $COURSE->fullname));

        if ($faqread) {
            $mform->addElement('checkbox', 'faqread', '', get_string('faqread:description', 'local_helpdesk', $faqlink));
            $mform->setType('faqread', PARAM_BOOL);
            $mform->addRule('faqread', get_string('subject_missing', 'local_helpdesk'), 'required', true, 'server');
        }

        require_once($CFG->dirroot . '/local/helpdesk/classes/lib.php');

        $potentialtargets = registry::get_potential_targets();

        $hideifs = ['mail'];

        // If there are not potentialtargets we don't care. We will send a mail to the Moodle default support contact.
        $options = [];
        foreach ($potentialtargets as $pt) {
            if (empty($pt->potentialgroups) || count($pt->potentialgroups) == 0) {
                $options[$pt->id . '_0'] = $pt->name;
                if (empty($pt->postto2ndlevel)) {
                    $hideifs[] = $pt->id . '_0';
                }
            } else {
                foreach ($pt->potentialgroups as $group) {
                    $options[$pt->id . '_' . $group->id] = $pt->name . ' > ' . $group->name;
                    if (empty($pt->postto2ndlevel)) {
                        $hideifs[] = $pt->id . '_' . $group->id;
                    }
                }
            }
        }
        if (count($potentialtargets) == 0) {
            $supportuser = \core_user::get_support_user();
            $options['mail'] = get_string('email_to_xyz', 'local_helpdesk', (object) ['email' => $supportuser->email]);
        }

        // The targets that cannot forward to the 2nd level. local_helpdesk/main hides the checkbox for them.
        $mform->addElement(
            'select',
            'forum_group',
            get_string('to_group', 'local_helpdesk'),
            $options,
            ['data-no2ndlevel' => json_encode($hideifs)]
        );
        $mform->setType('forum_group', PARAM_INT);

        if (!empty($usesubjects = get_config('local_helpdesk', 'predefined_subjects'))) {
            $options = ['' => ''];
            $options += explode(PHP_EOL, $usesubjects);
            $options = array_combine($options, $options);
            $mform->addElement(
                'select',
                'subject',
                get_string('subject', 'local_helpdesk'),
                $options,
                ['style' => 'width: 100%;']
            );
            $mform->setType('subject', PARAM_TEXT);
            $mform->addRule('subject', get_string('subject_missing', 'local_helpdesk'), 'required', null, 'server');
        } else {
            $mform->addElement(
                'text',
                'subject',
                get_string('subject', 'local_helpdesk'),
                ['style' => 'width: 100%;', 'type' => 'tel']
            );
            $mform->setType('subject', PARAM_TEXT);
            $mform->addRule('subject', get_string('subject_missing', 'local_helpdesk'), 'required', null, 'server');
        }

        if (!$disablephonefield) {
            $mform->addElement(
                'text',
                'contactphone',
                get_string('contactphone', 'local_helpdesk'),
                ['style' => 'width: 100%;']
            );
        } else {
            $mform->addElement('hidden', 'contactphone', '');
        }
        $mform->setType('contactphone', PARAM_TEXT);

        if ((isguestuser() || !isloggedin()) && $guestuserallowed) {
            $mform->addElement('text', 'guestmail', get_string('guestmail', 'local_helpdesk'), ['style' => 'width: 100%;']);
            $mform->setType('guestmail', PARAM_EMAIL);
            $mform->addRule('guestmail', get_string('mail_missing', 'local_helpdesk'), 'required', null, 'server');
        }

        // Accountmanager select.
        $am = new accountmanager();
        $am->prepare_accountmanager_for_form($mform);

        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'local_helpdesk'),
            ['style' => 'width: 100%;', 'rows' => 10]
        );
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', get_string('description_missing', 'local_helpdesk'), 'required', null, 'server');

        $mform->addElement('checkbox', 'postto2ndlevel', '', get_string(
            'postto2ndlevel:description',
            'local_helpdesk',
            ['sitename' => $SITE->fullname]
        ));
        $mform->setType('postto2ndlevel', PARAM_BOOL);
        $mform->setDefault('postto2ndlevel', 0);

        $mform->addElement('html', $OUTPUT->render_from_template('local_helpdesk/screenshot_upload', []));
    }

    /**
     * Validate the submitted data. Custom validation should be added here.
     *
     * @param array $data the submitted data.
     * @param array $files the submitted files.
     * @return array of errors, keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = [];
        return $errors;
    }

    /**
     * Get the priority levels a request can be filed with.
     *
     * @return array of prefix => label.
     */
    public function return_priority_options(): array {
        return [
            "" => get_string('prioritylvl:low', 'local_helpdesk'),
            "!" => get_string('prioritylvl:mid', 'local_helpdesk'),
            "!!" => get_string('prioritylvl:high', 'local_helpdesk'),
        ];
    }
}
