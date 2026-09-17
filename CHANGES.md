## Version 1.0.0 (2026091700)
* Security: Only site administrators can save the account manager settings.
* Security: Supporters can only edit and delete posts that belong to the issue they are looking at, forwarding, revoking and deleting need a session key, names are escaped in the dialogues.
* Bugfix: Attachments are served again, tickets keep their author in group mode, hidden course categories stay hidden unless they hold a support forum.
* Removed the debug page testreminder.php.
* Port of local_edusupport 2.8.0 to local_helpdesk.
* New: Migrate all data of an existing local_edusupport installation from the plugin settings or via CLI.

# History of local_edusupport

## Version 2.7.1 (2026022300)
* Improvement: Added a tooltip when hovering over helpdesk icon in navigation toolbar.
* Improvement: Migrate hooks implementation to new Moodle Hooks API.
* Bugfix: Make sure e-mails are still sent to assigned supporters even when setting 'sendsupporterassignments' is turned off.