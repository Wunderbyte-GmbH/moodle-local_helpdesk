## Version 1.0.0 (2026112000)
* Compatibility: Supports Moodle 4.5 to 5.3. The guest ticket user is created with \core\user::create_user() on Moodle 5.3. CI tests Moodle 4.5, 5.0, 5.1, 5.2 and 5.3.
* Bugfix: On the issue page of Moodle 4.5 and later, the forum's own controls covered the buttons of the helpdesk. The forum tools are hidden by the stylesheet now instead of by rewriting the markup, and the helpdesk actions have a row of their own.
* Bugfix: Deleting a course unregistered the wrong support forum (the registry id was passed as forum id).
* Bugfix: The supporters page listed the first level support of courses too, and saving such a row turned the person into platform support. It shows and edits the platform team only.
* Bugfix: Editing a post added another "Re:" to its subject every time.
* Release: The release workflow builds the package with git archive and the root directory "helpdesk", so it can be installed from the ZIP file, and hands that package to the Plugins directory.
* Standards: No inline JavaScript any more: the request form, the page before it and the choice of the dedicated supporter use AMD modules and data attributes. Markup built in JavaScript (supporter selection, contact list, buttons, spinner) and the tables of the migration and first level pages come from Mustache templates.
* Standards: All texts come from language strings, including the support team role and error messages, in English and German. Missing and unused strings fixed.
* Standards: The edusupport migrator reads the old settings with get_config() instead of querying config_plugins; recordsets for the privacy export, the cleanup task and other potentially large reads.
* Tests: Every global function has the frankenstyle prefix (checked by a test), deleting a course.
* Standards: Every parameter and return value has a type declaration; a return value has one type ("nothing" is null, no more false|object or bool|int). Only the parameters of moodleform overrides stay untyped, as their core parents are. A test checks this for all classes and functions. close_issue returns a boolean.
* Cleanup: Unused variables, globals and a forum lookup through a global variable removed, as found by PHP Mess Detector.
* Documentation: README rewritten with installation, configuration and use, new screenshots.
* Security: Only site administrators can save the account manager settings.
* Security: Supporters can only edit and delete posts that belong to the issue they are looking at, forwarding, revoking and deleting need a session key, names are escaped in the dialogues.
* Bugfix: Attachments are served again, tickets keep their author in group mode, hidden course categories stay hidden unless they hold a support forum.
* Removed the debug page testreminder.php.
* Security: Tickets are counted per person or per address instead of per session, screenshots have to be pictures of acceptable size, a ticket can only go into a group of its author, and people who are not logged in are no longer told who supports a course. Mail addresses of supporters are not handed out any more.
* Standards: Web services moved to classes/external on the core_external API with context validation, forms moved to the form namespace, status constants are class constants of lib, role set up through the role API, new db/uninstall.php takes back the capability prohibitions, the role and the guest user, settings only built for the full admin tree, no concatenated SQL, broken db/mobile.php removed.
* Performance: The issue list asks the database a fixed number of times instead of five to seven times per issue.
* Cleanup: unused strings, unused methods, the never used column archiveid, commented out code and a debug mode of the reminder task removed; the reminder task only handles the one issue it is queued for; no role id or unbounded recursion in the enrolment helper; the account manager queries take parameters.
* Bugfix: The guest ticket user is loaded completely after it was created, so its name can be built.
* Tests for the issue list, the reminder and cleanup tasks, account managers, the page hooks and requests filed without logging in.
* Modernisation: main.js rewritten as an ES module without jQuery string building; buttons carry data-action attributes handled by the small module local_helpdesk/actions, so there are no inline onclick handlers and the dialogues are only loaded when used. Markup carries the Bootstrap 5 attributes and classes next to the Bootstrap 4 ones. One stylesheet with prefixed classes; the spinner is styled again.
* Removed html2canvas: nothing called it, screenshots are uploaded by the person filing the request.
* Improvement: The help menu cache respects the language and is purged when a setting changes. All strings are available in German, icon titles are translated.
* Security: The address of a guest is stored with the ticket (new table local_helpdesk_guesttickets) instead of being read from the title of the discussion.
* Port of local_edusupport 2.8.0 to local_helpdesk.
* New: Migrate all data of an existing local_edusupport installation from the plugin settings or via CLI.

# History of local_edusupport

## Version 2.7.1 (2026022300)
* Improvement: Added a tooltip when hovering over helpdesk icon in navigation toolbar.
* Improvement: Migrate hooks implementation to new Moodle Hooks API.
* Bugfix: Make sure e-mails are still sent to assigned supporters even when setting 'sendsupporterassignments' is turned off.