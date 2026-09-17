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
 * The dialogues of the helpdesk: filing a request, handing an issue over, closing and forwarding it.
 *
 * Nothing here is wired to the page by itself. local_helpdesk/actions listens for the elements
 * carrying a data-action and loads this module when one of them is used.
 *
 * @module     local_helpdesk/main
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Url from 'core/url';
import {getString, getStrings} from 'core/str';

const FORM = '#local_helpdesk_create_form';

/** The dialogue a request is filed with. It is kept, so that what was typed survives closing it. */
let requestModal = null;

/** The screenshot chosen in the form, as data URL, and its file name. */
let screenshot = '';
let screenshotName = '';

/** True while a request is on its way, so that a second click does not file it twice. */
let sending = false;

/** How many things the spinner is waiting for. */
let spinnerSteps = 0;

/**
 * Call one web service.
 *
 * @param {string} methodname
 * @param {object} args
 * @returns {Promise}
 */
const call = (methodname, args) => Promise.resolve(Ajax.call([{methodname, args}])[0]);

/**
 * Create an element with a text in it. Whatever the text is, it stays text.
 *
 * @param {string} tag
 * @param {string} text
 * @param {object} attributes
 * @returns {HTMLElement}
 */
const element = (tag, text = '', attributes = {}) => {
    const node = document.createElement(tag);
    node.textContent = text;
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
    return node;
};

/**
 * Show or hide the spinner that covers the page while the form is fetched.
 *
 * @param {number} steps 1 when something starts, -1 when it is done.
 */
const triggerSpinner = (steps) => {
    spinnerSteps += steps;
    const spinner = document.getElementById('helpdesk-spinner');
    if (spinnerSteps > 0 && !spinner) {
        const node = element('div', '', {id: 'helpdesk-spinner', 'class': 'spinner-grid show'});
        for (let i = 0; i < 4; i++) {
            node.append(element('div'));
        }
        document.body.append(node);
    } else if (spinnerSteps <= 0 && spinner) {
        spinner.remove();
    }
};

/**
 * Let a supporter pick who takes an issue.
 *
 * @param {number} discussionid
 */
export const assignSupporter = async(discussionid) => {
    try {
        const result = JSON.parse(await call('local_helpdesk_get_potentialsupporters', {discussionid}));
        const select = element('select', '', {'class': 'form-select custom-select'});
        Object.entries(result.supporters).forEach(([supportlevel, supporters]) => {
            const group = element('optgroup', '', {label: supportlevel});
            supporters.forEach((supporter) => {
                const option = element('option', supporter.firstname + ' ' + supporter.lastname, {value: supporter.userid});
                option.selected = !!supporter.selected;
                group.append(option);
            });
            select.append(group);
        });

        const modal = await ModalSaveCancel.create({
            title: getString('select', 'core'),
            body: select.outerHTML,
            show: true,
            removeOnClose: true,
        });
        modal.getRoot().on(ModalEvents.save, async(e) => {
            e.preventDefault();
            try {
                const supporterid = modal.getRoot().find('.modal-body select').val();
                await call('local_helpdesk_set_currentsupporter', {discussionid, supporterid});
                window.top.location.reload();
            } catch (error) {
                Notification.exception(error);
            }
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Close an issue and go back to the list.
 *
 * @param {number} discussionid
 */
export const closeIssue = async(discussionid) => {
    try {
        await call('local_helpdesk_close_issue', {discussionid});
        window.top.location.href = Url.relativeUrl('/local/helpdesk/issues.php', {});
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Ask before an issue is forwarded to the platform team, or taken back from it.
 *
 * @param {number} discussionid
 * @param {boolean} revoke true to take the issue back.
 */
export const injectForwardModal = async(discussionid, revoke) => {
    try {
        const [title, body] = await getStrings([
            {key: 'confirm', component: 'core'},
            {key: revoke ? 'issue_revoke' : 'issue_assign_nextlevel', component: 'local_helpdesk'},
        ]);
        const modal = await ModalSaveCancel.create({title, body, show: true, removeOnClose: true});
        modal.getRoot().on(ModalEvents.save, () => {
            window.top.location.href = Url.relativeUrl(
                '/local/helpdesk/forward_2nd_level.php',
                {d: discussionid, revoke: revoke ? 1 : 0, sesskey: M.cfg.sesskey}
            );
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Put the button that forwards a discussion to the platform team next to its title.
 *
 * @param {number} discussionid
 * @param {boolean} isissue true if the discussion is with the platform team already.
 */
export const injectForwardButton = async(discussionid, isissue) => {
    if (typeof discussionid === 'undefined') {
        return;
    }
    try {
        const label = await getString(isissue ? 'issue_revoke' : 'issue_assign_nextlevel', 'local_helpdesk');
        const title = document.querySelector('#page-content div[role="main"] .discussionname');
        if (!title || !title.parentNode) {
            return;
        }
        title.parentNode.prepend(element('a', label, {
            href: '#',
            'class': 'btn btn-secondary float-right float-end',
            'data-action': 'local_helpdesk-forward',
            'data-discussionid': discussionid,
            'data-revoke': isissue ? 1 : 0,
        }));
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Highlight a discussion whose title asks for it with "! " or "!!".
 */
export const injectTest = () => {
    const title = document.querySelector('.discussionname');
    if (!title) {
        return;
    }
    const start = title.textContent.substring(0, 2);
    if (start === '! ') {
        title.classList.add('alert-warning');
    } else if (start === '!!') {
        title.classList.add('alert-danger');
    }
};

/**
 * Replace the reply links of the forum by ones that stay inside the helpdesk.
 *
 * @param {number} discussion
 */
export const injectReplyButtons = async(discussion) => {
    try {
        const label = await getString('reply', 'forum');
        document.querySelectorAll(
            'a[href*="issue.php?discussion=' + discussion + '&parent="],'
            + 'a[href*="issue.php?discussion=' + discussion + '&delete="],'
            + 'a[href*="post.php?prune="]'
        ).forEach((link) => link.remove());

        document.querySelectorAll('.forum-post-container>.forumpost').forEach((post) => {
            const postid = post.getAttribute('data-post-id');
            const actions = post.querySelector('.post-actions:first-child');
            if (!actions || post.querySelector('.reply-' + postid)) {
                return;
            }
            actions.append(element('a', label, {
                'data-region': 'post-action',
                'class': 'btn btn-link reply-' + postid,
                title: label,
                'aria-label': label,
                role: 'menuitem',
                tabindex: -1,
                href: Url.relativeUrl('/local/helpdesk/issue.php?discussion=' + discussion + '&replyto=' + postid
                    + '#mformforum'),
            }));
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Read the file chosen as screenshot, so that it can be sent along with the request.
 */
export const uploadScreenshot = () => {
    const container = document.getElementById('helpdesk_screenshot');
    const input = container ? container.querySelector('input[type="file"]') : null;
    if (!input || !input.files.length) {
        return;
    }
    const show = (selector) => container.querySelector(selector)?.classList.remove('hidden');
    container.querySelectorAll('div.alert').forEach((alert) => alert.classList.add('hidden'));
    input.classList.add('disabled');

    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = () => {
        screenshot = reader.result;
        screenshotName = file.name;
        show('div.alert-success');
        input.classList.remove('disabled');
    };
    reader.onerror = () => {
        show('div.alert-danger');
        input.classList.remove('disabled');
    };
    reader.readAsDataURL(file);
};

/**
 * Tell the person what is missing.
 *
 * @param {string} key a string of local_helpdesk.
 */
const complain = async(key) => {
    Notification.alert('', await getString(key, 'local_helpdesk'));
};

/**
 * The list of people who look after the request.
 *
 * @param {Array} responsibles
 * @returns {string} markup, empty if there is nobody to name.
 */
const renderResponsibles = (responsibles) => {
    if (!Array.isArray(responsibles) || !responsibles.length) {
        return '';
    }
    const list = element('ul', '', {'class': 'helpdesk_responsible'});
    responsibles.forEach((responsible) => {
        const item = element('li');
        if (responsible.userid > 0) {
            item.append(element('a', responsible.name, {
                href: Url.fileUrl('/user', 'view.php?id=' + parseInt(responsible.userid, 10)),
                target: '_blank',
            }));
        } else if (responsible.email) {
            item.append(element('a', responsible.name, {href: 'mailto:' + responsible.email}));
        } else {
            item.textContent = responsible.name;
        }
        list.append(item);
    });
    return list.outerHTML;
};

/**
 * Tell the person what became of the request.
 *
 * @param {object} result the answer of local_helpdesk_create_issue.
 */
const showResult = async(result) => {
    const responsibles = renderResponsibles(result.responsibles);
    const discussionid = parseInt(result.discussionid, 10);
    const strings = (keys) => getStrings(keys.map((key) => ({key, component: 'local_helpdesk'})));

    if (discussionid === -999) {
        // It went out by mail.
        const [title, description, named, close] = await strings(['create_issue_success_title',
            'create_issue_success_description_mail', 'create_issue_success_responsibles', 'create_issue_success_close']);
        Notification.alert(title, responsibles ? named + responsibles : description, close);
    } else if (discussionid > 0) {
        const [title, description, named, goto, close] = await strings(['create_issue_success_title',
            'create_issue_success_description', 'create_issue_success_responsibles', 'create_issue_success_goto',
            'create_issue_success_close']);
        Notification.confirm(title, responsibles ? named + responsibles : description, goto, close, () => {
            window.top.location.href = Url.fileUrl('/mod/forum', 'discuss.php?d=' + discussionid);
        });
    } else {
        const [title, description] = await strings(['create_issue_error_title', 'create_issue_error_description']);
        Notification.alert(title, description);
    }
};

/**
 * File the request that was typed into the form.
 *
 * @param {object} modal the dialogue holding the form.
 */
const postBox = async(modal) => {
    if (sending) {
        return;
    }
    const form = document.querySelector(FORM);
    const field = (id) => form.querySelector('#' + id);
    const value = (id) => (field(id) ? field(id).value : null);

    const subject = value('id_subject') || '';
    const description = value('id_description') || '';
    const guestmail = value('id_guestmail');

    if (!field('id_faqread')?.checked) {
        complain('faqread');
        return;
    }
    if (subject.length === 0) {
        complain('select_subject');
        return;
    }
    if (subject.length < 3 || description.length < 5) {
        complain('be_more_accurate');
        return;
    }
    const validmail = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    if (field('id_guestmail') && !validmail.test(guestmail)) {
        complain('invalidmail');
        return;
    }

    sending = true;
    try {
        const result = await call('local_helpdesk_create_issue', {
            subject,
            description,
            'forum_group': value('id_forum_group'),
            postto2ndlevel: field('id_postto2ndlevel')?.checked ? 1 : 0,
            image: screenshot,
            screenshotname: screenshotName,
            url: window.top.location.href,
            contactphone: value('id_contactphone') || '',
            guestmail,
            accountmanager: value('id_accountmanager'),
        });
        modal.hide();
        await showResult(result);
    } catch (error) {
        Notification.exception(error);
    } finally {
        sending = false;
    }
};

/**
 * Show the dialogue a request is filed with.
 *
 * @param {number} forumid the forum to offer, 0 for all of them.
 */
export const showBox = async(forumid = 0) => {
    if (requestModal) {
        requestModal.show();
        return;
    }
    triggerSpinner(1);
    try {
        const body = await call('local_helpdesk_create_form', {url: window.top.location.href, image: '', forumid});
        // Remove any previously created forms.
        document.querySelector(FORM)?.remove();

        const modal = await ModalSaveCancel.create({body, large: true});
        modal.setSaveButtonText(getString('create_issue', 'local_helpdesk'));
        modal.getRoot().on(ModalEvents.save, (e) => {
            // The dialogue stays open until the request is filed.
            e.preventDefault();
            postBox(modal);
        });
        const target = modal.getRoot().find('#id_forum_group');
        if (target.find('option').length <= 1) {
            // With one place to ask there is nothing to choose.
            target.parent().parent().css('display', 'none');
        }
        requestModal = modal;
        modal.show();
    } catch (error) {
        Notification.exception(error);
    } finally {
        triggerSpinner(-1);
    }
};
