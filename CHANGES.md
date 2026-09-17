## Version 1.0.0 (2026091700)
* Security: Only site administrators can save the account manager settings.
* Security: Supporters can only edit and delete posts that belong to the issue they are looking at, forwarding, revoking and deleting need a session key, names are escaped in the dialogues.
* Bugfix: Attachments are served again, tickets keep their author in group mode, hidden course categories stay hidden unless they hold a support forum.
* Removed the debug page testreminder.php.
* Security: Tickets are counted per person or per address instead of per session, screenshots have to be pictures of acceptable size, a ticket can only go into a group of its author, and people who are not logged in are no longer told who supports a course. Mail addresses of supporters are not handed out any more.
* Standards: Web services moved to classes/external on the core_external API with context validation, forms moved to the form namespace, status constants are class constants of lib, role set up through the role API, new db/uninstall.php takes back the capability prohibitions, the role and the guest user, settings only built for the full admin tree, no concatenated SQL, broken db/mobile.php removed.
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