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
 * Connects the buttons of the helpdesk with what they do.
 *
 * This is all that is loaded with every page. The dialogues themselves are fetched when
 * somebody uses one of the buttons.
 *
 * @module     local_helpdesk/actions
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const PREFIX = 'local_helpdesk-';

let initialised = false;

/**
 * What each data-action does.
 *
 * @param {object} main the module local_helpdesk/main.
 * @param {string} action the action, without the prefix.
 * @param {DOMStringMap} data the data attributes of the element.
 */
const run = (main, action, data) => {
    const discussionid = parseInt(data.discussionid, 10);
    switch (action) {
        case 'showbox':
            main.showBox(parseInt(data.forumid, 10) || 0);
            break;
        case 'assign':
            main.assignSupporter(discussionid);
            break;
        case 'close':
            main.closeIssue(discussionid);
            break;
        case 'forward':
            main.injectForwardModal(discussionid, data.revoke === '1');
            break;
        case 'uploadscreenshot':
            main.uploadScreenshot();
            break;
    }
};

/**
 * Handle a click, or the change of a file input.
 *
 * @param {Event} e
 */
const handle = async(e) => {
    const target = e.target.closest('[data-action^="' + PREFIX + '"]');
    if (!target) {
        return;
    }
    const action = target.dataset.action.substring(PREFIX.length);
    // A file input reports its file with "change", everything else is a button.
    if ((action === 'uploadscreenshot') !== (e.type === 'change')) {
        return;
    }
    if (e.type === 'click') {
        e.preventDefault();
    }
    const main = await import('local_helpdesk/main');
    run(main, action, target.dataset);
};

/**
 * Start listening. Can be called more than once.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('click', handle);
    document.addEventListener('change', handle);
};
