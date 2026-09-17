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
 * Administration settings for local_helpdesk.
 *
 * @package    local_helpdesk
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

global $USER;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_helpdesk_settings', get_string('pluginname', 'local_helpdesk'));
    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_helpdesk_overview',
        get_string('overview', 'local_helpdesk'),
        new moodle_url('/local/helpdesk/overview.php'),
        'moodle/site:config'
    ));

    // Reached from the button below rather than from the tree, so it stays hidden there.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_helpdesk_seedfirstlevel',
        get_string('seedfirstlevel', 'local_helpdesk'),
        new moodle_url('/local/helpdesk/seedfirstlevel.php'),
        'moodle/site:config',
        true
    ));

    // Possibly we changed the menu, therefore we delete the cache. We should find a better place for this.
    $cache = cache::make('local_helpdesk', 'supportmenu');
    $cache->delete($USER->id);

    $settings->add(
        new admin_setting_configtextarea(
            'local_helpdesk/extralinks',
            get_string('extralinks', 'local_helpdesk'),
            get_string('extralinks:description', 'local_helpdesk'),
            '',
            PARAM_TEXT
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/trackhost',
            get_string('trackhost', 'local_helpdesk'),
            get_string('trackhost:description', 'local_helpdesk'),
            1
        )
    );

    // FAQ read.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/faqread',
            get_string('faqread', 'local_helpdesk'),
            '',
            1
        )
    );

    // FAQ Link.
    $settings->add(
        new admin_setting_configtext(
            'local_helpdesk/faqlink',
            get_string('faqlink', 'local_helpdesk'),
            get_string('faqlink:description', 'local_helpdesk'),
            ''
        )
    );

    // Disable User Profile Links.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/userlinks',
            get_string('userlinks', 'local_helpdesk'),
            get_string('userlinks:description', 'local_helpdesk'),
            1
        )
    );

    // Priority LVL.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/prioritylvl',
            get_string('prioritylvl', 'local_helpdesk'),
            get_string('prioritylvl:description', 'local_helpdesk'),
            1
        )
    );

    // Disable Telephone Link.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/phonefield',
            get_string('phonefield', 'local_helpdesk'),
            get_string('phonefield:description', 'local_helpdesk'),
            1
        )
    );

    // Delete threshhold.
    $settings->add(
        new admin_setting_configduration(
            'local_helpdesk/deletethreshhold',
            get_string('deletethreshhold', 'local_helpdesk'),
            get_string('deletethreshhold:description', 'local_helpdesk'),
            4 * WEEKSECS
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/auto2ndlvl',
            get_string('auto2ndlvl', 'local_helpdesk'),
            get_string('auto2ndlvl:description', 'local_helpdesk'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_helpdesk/predefined_subjects',
            get_string('predefined_subjects', 'local_helpdesk'),
            get_string('predefined_subjects:description', 'local_helpdesk'),
            '',
            PARAM_TEXT
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/predefined_subjects_prefix',
            get_string('predefined_subjects_prefix', 'local_helpdesk'),
            get_string('predefined_subjects_prefix:description', 'local_helpdesk'),
            0
        )
    );

    // Prepage before form.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/enableprepage',
            get_string('enableprepage', 'local_helpdesk'),
            get_string('enableprepage:description', 'local_helpdesk'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_helpdesk/prepage',
            get_string('prepage', 'local_helpdesk'),
            get_string('prepage:description', 'local_helpdesk'),
            '',
            PARAM_RAW
        )
    );

    // Prepage before form.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/firstlvlgroupmode',
            get_string('firstlvlgroupmode', 'local_helpdesk'),
            get_string('firstlvlgroupmode:description', 'local_helpdesk'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_helpdesk/customfieldname',
            get_string('customfieldname', 'local_helpdesk'),
            get_string('customfieldname:description', 'local_helpdesk'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_helpdesk/rolename',
            get_string('rolename', 'local_helpdesk'),
            get_string('rolename:description', 'local_helpdesk'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/showresponsibles',
            get_string('showresponsibles', 'local_helpdesk'),
            get_string('showresponsibles:description', 'local_helpdesk'),
            1
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/holidaymodeenabled',
            get_string('holidaymodeenabled', 'local_helpdesk'),
            get_string('holidaymodeenabled:description', 'local_helpdesk'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/guestmodeenabled',
            get_string('guestmodeenabled', 'local_helpdesk'),
            get_string('guestmodeenabled:description', 'local_helpdesk'),
            0
        )
    );

    $options = [
        0 => get_string('inactive'),
        60 => "1 " . get_string('minute'),
        600 => "10 " . get_string('minutes'),
        3600 => "60 " . get_string('minutes'),
    ];

    $settings->add(
        new admin_setting_configselect(
            'local_helpdesk/spamprotectionthreshold',
            get_string('spamprotection:threshold', 'local_helpdesk'),
            get_string('spamprotection:threshold:description', 'local_helpdesk'),
            600,
            $options
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'local_helpdesk/spamprotectionlimit',
            get_string('spamprotection:limit', 'local_helpdesk'),
            get_string('spamprotection:limit:description', 'local_helpdesk'),
            5,
            [ 1 => 1, 2 => 2, 5 => 5, 10 => 10, 20 => 20]
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/sendreminders',
            get_string('cron:reminder:title', 'local_helpdesk'),
            '',
            0
        )
    );

    $settings->add(new admin_setting_configduration(
        'local_helpdesk/timebeforereminder',
        get_string('timebeforereminder', 'local_helpdesk'),
        '',
        2,
        86400
    ));

    $settings->add(new admin_setting_heading('local_helpdesk_messaging', get_string(
        'messagepreferences',
        'message'
    ), ''));
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/sendmsgonset2ndlvl',
            get_string('sendmsgonset2ndlvl', 'local_helpdesk'),
            get_string('sendmsgonset2ndlvl:description', 'local_helpdesk'),
            0
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/sendoriginalrequest',
            get_string('sendoriginalrequest', 'local_helpdesk'),
            get_string('sendoriginalrequest:description', 'local_helpdesk'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/sendsupporterassignments',
            get_string('sendsupporterassignments', 'local_helpdesk'),
            get_string('sendsupporterassignments:description', 'local_helpdesk'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/sendissueclosed',
            get_string('sendissueclosed', 'local_helpdesk'),
            get_string('sendissueclosed:description', 'local_helpdesk'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_helpdesk/sendrequestreceived',
            get_string('sendrequestreceived', 'local_helpdesk'),
            get_string('sendrequestreceived:description', 'local_helpdesk'),
            1
        )
    );

    $actions = [
        (object) ['name' => 'supporters', 'href' => 'choosesupporters.php'],
        (object) ['name' => 'setaccountmanager', 'href' => 'accountmanager.php'],
        (object) ['name' => 'seedfirstlevel', 'href' => 'seedfirstlevel.php'],
        (object) ['name' => 'overview', 'href' => 'overview.php'],
    ];
    $links = "<div class='grid-eq-3'>";
    foreach ($actions as $action) {
        $links .= '<a class="btn btn-secondary mr-2 mb-3" href="' . $CFG->wwwroot . '/local/helpdesk/' . $action->href . '">' .
                        '<i class="fa fa-users"></i> ' .
                        get_string($action->name, 'local_helpdesk') .
                  '</a>';
    }
    $links .= "</div>";
    $settings->add(new admin_setting_heading('local_helpdesk_actions', get_string('settings'), $links));
}
