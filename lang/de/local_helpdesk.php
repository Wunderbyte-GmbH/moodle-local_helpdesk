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
 * German strings for local_helpdesk.
 *
 * @package   local_helpdesk
 * @copyright 2018 Digital Education Society (http://www.dibig.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accountmanager'] = 'Dein Account Manager';
$string['accountmanagers'] = 'Account Manager';
$string['accountmanagertitle'] = 'Account Manager';
$string['archive'] = 'Archiv';
$string['assigned'] = 'Zugeordnet';
$string['auto2ndlvl'] = 'Automatische Weiterleitung zum 2nd Level Support';
$string['auto2ndlvl:description'] = 'Automatische Weiterleitung zum 2nd Level Support nach Anlegen eines neuen Tickets';
$string['autoassign'] = 'Automatisch zuweisbar';
$string['back'] = 'Zurück';
$string['be_more_accurate'] = 'Bitte beschreiben Sie das Problem genauer!';
$string['cachedef_spamprotect'] = 'Zählt die Anfragen einer Person oder einer Adresse';
$string['cachedef_supportmenu'] = 'Cache für das Supportmenü';
$string['capstocheck'] = 'Rechte die geprüft werden';
$string['changes_saved_fail'] = 'Änderungen konnten nicht gespeichert werden.';
$string['changes_saved_successfully'] = 'Änderungen erfolgreich gespeichert.';
$string['changestatus'] = 'Status ändern';
$string['contactphone'] = 'Telefon';
$string['continue'] = 'Weiter';
$string['coursecategorydeletion'] = 'Sie versuchen einen Kursbereich zu löschen, der Supportforen enthält. Bitte stellen Sie sicher, dass Sie zuvor die Supportforen deaktivieren!';
$string['coursesupporters'] = '1st Level Support';
$string['coursesupporters:assign'] = '1st Level Support zuweisen';
$string['coursesupporters:assign_help'] = 'Auswählbar sind nur in diesem Kurs eingeschriebene Personen, die sowohl eine Diskussion im Forum beginnen als auch verborgene Aktivitäten sehen dürfen. Das unterscheidet Lehrende von Teilnehmenden. Wer systemweit eine Rolle hat, aber hier nicht eingeschrieben ist, steht nicht zur Auswahl.';
$string['coursesupporters:description'] = 'Diese Personen bearbeiten die Supportanfragen aus diesem Kurs. Ist niemand zugewiesen, gehen Anfragen direkt an das Plattform-Supportteam.';
$string['coursesupporters:none'] = 'Es ist noch niemand zugewiesen, Anfragen gehen daher direkt an das Plattform-Supportteam.';
$string['coursesupporters:nosupportforum'] = 'Dieser Kurs enthält kein Supportforum, es können hier also keine Anfragen gestellt werden.';
$string['create_issue'] = 'Support kontaktieren';
$string['create_issue_error_description'] = 'Die Anfrage konnte nicht gespeichert werden!';
$string['create_issue_error_title'] = 'Fehler';
$string['create_issue_success_close'] = 'Schließen';
$string['create_issue_success_description'] = 'Ihre Anfrage wurde gespeichert. Wir kümmern uns darum so rasch wie möglich!';
$string['create_issue_success_description_mail'] = 'Ihre Anfrage wurde per e-Mail gesendet. Wir kümmern uns darum so rasch wie möglich!';
$string['create_issue_success_goto'] = 'Anfrage öffnen';
$string['create_issue_success_responsibles'] = 'Ansprechperson für diese Ticket ist/sind:';
$string['create_issue_success_title'] = 'Erfolg';
$string['cron:deleteexpiredissues:title'] = 'Lösche alte Tickets';
$string['cron:reminder:intro'] = 'Dies ist eine freundlicher Erinnerung an jene offenen Tickets, die Ihnen als Supporter zugeteilt wurden!';
$string['cron:reminder:title'] = 'Support Erinnerung';
$string['cron:sendmail:title'] = 'E-Mail zu einer Supportanfrage versenden';
$string['customfieldname'] = 'Profilfeldname für den Gruppenmodus';
$string['customfieldname:description'] = 'Profilfeldname für den Gruppenmodus';
$string['dedicatedsupporter'] = 'Zugewiesen';
$string['dedicatedsupporter:not_successfully_set'] = 'Konnte bevorzugte/n Supportmitarbeiter/in nicht auswählen.';
$string['dedicatedsupporter:successfully_set'] = 'Erfolgreich eine/n bevorzugte/n Supportmitarbeiter/in ausgewählt.';
$string['deletethreshhold'] = 'Geschlossene Anfragen löschen nach';
$string['deletethreshhold:description'] = 'Legt fest, nach welcher Zeit geschlossene Anfragen aus der Anfragenliste gelöscht werden. Das betrifft nur die Anfragenliste, nicht die Forumsbeiträge. 0 bedeutet, dass geschlossene Anfragen für immer behalten werden (derzeit nicht empfohlen).';
$string['description'] = 'Beschreiben Sie das Problem und posten Sie den Link zur Seite oder zum Kurs wo das Problem auftrat';
$string['description_missing'] = 'Bitte geben Sie eine detaillierte Beschreibung an!';
$string['email_to_xyz'] = 'Sende e-Mail an {$a->email}';
$string['enableprepage'] = "enable Prepage";
$string['enableprepage:description'] = "enables a site before form";
$string['error:mailnotsent'] = 'Die E-Mail an {$a} konnte nicht versendet werden.';
$string['error:notasupporter'] = 'Nur Mitglieder des Supportteams können ein Ticket übergeben.';
$string['error:notasupportforum'] = 'Diese Diskussion gehört zu keinem Supportforum und kann daher nicht zugewiesen werden.';
$string['error:targetnotasupporter'] = 'Die gewählte Person gehört nicht zum Supportteam dieses Kurses.';
$string['error:unknowndiscussion'] = 'Diese Diskussion existiert nicht mehr.';
$string['extralinks'] = 'Extralinks';
$string['extralinks:description'] = 'Wenn Sie hier Links eintragen, dann wird der "Hilfe"-Button zu einem Menü. Dieses wird den "Hilfe"-Button als ersten Menüeintrag anzeigen, und alle hier eingetragenen Links als zusätzliche Hilfeanlaufstellen. Geben Sie die Links zeilenweise in folgendem Format an: Linkname|URL|faicon|Target';
$string['faqlink'] = 'FAQ-Link';
$string['faqlink:description'] = 'Addresse zum FAQ';
$string['faqread'] = 'Bitte bestätigen, dass Sie die FAQ gelesen haben!';
$string['faqread:description'] = 'Ich bestätige hiermit die <a href="{$a}">FAQ</a> gelesen zu haben';
$string['firstlvlgroupmode'] = '1st level Support Gruppen Modus';
$string['firstlvlgroupmode:description'] = 'Aktiviert den Gruppenmodus, so dass Nicht-Lehrer (andere Rollen) auf der Grundlage eines benutzerdefinierten Feldes verbunden werden und im Kursforum antworten können (stellen Sie sicher, dass Sie der Rolle "canforward2ndlevel" das Recht geben. Aktivieren Sie auch den Gruppenmodus im Kurs und aktivieren Sie getrennte Gruppen.';
$string['furtherquestions'] = 'Da Sie eine Supportanfrage als Gastbenutzer gestellt haben, können Sie nicht antworten oder weitere Kommentare zu dieser Anfrage abgeben. Wenn Sie weitere Unterstützung wünschen, registrieren Sie sich bitte unter {$a->sitename}.';
$string['guestmail'] = 'Ihre E-Mail Adresse';
$string['guestmodeenabled'] = 'Gastmodus aktiv';
$string['guestmodeenabled:description'] = 'Gäste können auch Supporttickets anlegen und werden dann per Mail benachrichtigt';
$string['header'] = 'Hilfe in &nbsp;<i>{$a}</i>&nbsp; anfordern';
$string['helpdesk:assignsupporters'] = '1st Level Support eines Kurses zuweisen';
$string['helpdesk:canforward2ndlevel'] = 'Kann Probleme an das Plattform Support Team melden';
$string['holidaymode'] = 'Urlaubsmodus';
$string['holidaymode_end'] = 'Beende Urlaubsmodus';
$string['holidaymode_is_on'] = 'Urlaubsmodus ist an';
$string['holidaymode_is_on_descr'] = 'Bei aktiviertem Urlaubsmodus werden Ihnen keine neuen Tickets zugewiesen.';
$string['holidaymodeenabled'] = "Urlaubsmodus aktivieren";
$string['holidaymodeenabled:description'] = "Urlaubsmodus: Supporter bekommen bis zu einem bestimmten Datum keine Tickets.";
$string['invalidmail'] = 'Bitte tragen Sie eine richtige E-Mail Adresse ein.';
$string['issue'] = 'Anfrage';
$string['issue:assigned'] = 'Sie wurden folgendem Ticket zugewiesen:';
$string['issue:countassigned'] = 'verfolgte Tickets';
$string['issue:countclosed'] = 'geschlossene Tickets';
$string['issue:countcurrent'] = 'offene Tickets';
$string['issue:countother'] = 'andere Tickets';
$string['issue_assign'] = 'Zuordnen';
$string['issue_assign_me'] = 'Mir zuweisen';
$string['issue_assign_nextlevel'] = 'Dieses Ticket dem Plattform-Support zuweisen';
$string['issue_assign_nextlevel:error'] = 'Entschuldigung, das Ticket konnte nicht dem Plattform Support Team zugewiesen werden.';
$string['issue_assign_nextlevel:post'] = '<p>Wir freuen uns, Ihnen mitteilen zu können, dass Ihre Support-Anfrage an das {$a->sitename} Support-Team weitergeleitet wurde!</p>
 <p>Sie werden in Kürze eine Antwort auf Ihre Frage erhalten. Bitte haben Sie Verständnis dafür, dass die Beantwortung mancher Fragen länger dauert und es einige Tage dauern kann, bis wir Ihnen eine Lösung anbieten können.</p>
 Sie erhalten diese E-Mail, weil Sie das Team von {$a->sitename} über eine Support-Anfrage um Hilfe gebeten haben. Sie finden alle Ihre Anfragen unter {$a->supportforumname} auf {$a->sitename}.
 <p>Wir wünschen Ihnen eine tolle Lernerfahrung!</p>
 <p>Ihr {$a->sitename} Team </p>';
$string['issue_assigned:subject'] = 'Supportanfrage zugeordnet';
$string['issue_close'] = 'Anfrage schließen';
$string['issue_closed:post'] = 'Dieses Ticket wurde von <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> geschlossen. Falls Sie weitere Unterstützung benötigen, fordern Sie bitte wieder das Plattform Support Team an!';
$string['issue_closed:subject'] = 'Anfrage wurde geschlossen';
$string['issue_reopen'] = 'Anfrage wieder öffnen';
$string['issue_responsibles:post'] = '
    <p>
        Die Verantwortung für dieses Ticket liegt bei: {$a->responsibles}!
    </p>
    <p>
        Die Manager/innen der Schule können das Problem an den {$a->sitename}-Support weiterleiten, indem sie die Schaltfläche "Dieses Ticket dem {$a->sitename}-Support zuweisen" anklicken (für Manager/innen rechts oben sichtbar).
    </p>
';
$string['issue_responsibles:subject'] = 'Supportanfrage zugeordnet';
$string['issue_revoke'] = 'Ticket vom höheren Supportlevel zurücknehmen';
$string['issue_revoke:error'] = 'Entschuldigung, dieses Ticket konnte vom höheren Supportlevel nicht zurückgeholt werden!';
$string['issue_revoke:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> hat dieses Ticket vom höheren Supportlevel zurückgenommen';
$string['issue_revoke:subject'] = 'Ticket storniert';
$string['issue_unwatch'] = 'Nicht mehr beobachten';
$string['issue_watch'] = 'Beobachten';
$string['issuereceived'] = '<p>Danke, dass Sie sich gemeldet haben. Ihre Supportanfrage ist eingelangt.</p>
<p>Sie werden in Kürze eine Antwort auf Ihre Frage erhalten. Bitte haben Sie Verständnis dafür, dass die Lösung einiger Probleme mehr Zeit beansprucht und es einige Tage dauern kann, bis wir Ihnen eine Lösung anbieten können.</p>
<p>Sie erhalten diese E-Mail, weil Sie das Team über eine Support-Anfrage um Hilfe gebeten haben. Sie finden alle Ihre Anfragen im <a href="{$a->wwwroot}/mod/forum/view.php?id={$a->cmid}">Support-Forum</a> auf {$a ->Sitename}.';
$string['issuereceived:subject'] = 'Ihre Supportanfrage wird bearbeitet';
$string['issues'] = 'Anfragen';
$string['issues:assigned'] = 'Abonniert';
$string['issues:assigned:none'] = 'Es sind keine weiteren Anfragen abonniert worden.';
$string['issues:closed'] = 'Geschlossen';
$string['issues:current'] = 'Meine Verantwortung';
$string['issues:current:none'] = 'Gönn dir ne Pause - es ist alles erledigt!';
$string['issues:other'] = 'Andere Anfragen';
$string['issues:show'] = 'Helpdesk-Anfragen anzeigen';
$string['label:2ndlevel'] = 'Plattform Support Team';
$string['level'] = 'Ebene';
$string['level:first'] = '1st Level, ein Kurs';
$string['level:second'] = '2nd Level, die Plattform';
$string['messageprovider:helpdesk_issue'] = 'Helpdesk-Benachrichtigungen zu Anfragen';
$string['migrate'] = 'Von eduSupport migrieren';
$string['migrate:alreadydone'] = 'Die Daten von local_edusupport wurden am {$a} migriert.';
$string['migrate:apply'] = 'Jetzt migrieren';
$string['migrate:clidryrun'] = 'Es wurde nichts geändert. Mit --run werden die Daten migriert.';
$string['migrate:count:adhoctasks'] = 'Wartende Erinnerungen und E-Mails';
$string['migrate:count:issues'] = 'Anfragen';
$string['migrate:count:preferences'] = 'Benachrichtigungseinstellungen der Nutzer/innen';
$string['migrate:count:settings'] = 'Einstellungen';
$string['migrate:count:subscriptions'] = 'Abonnements von Anfragen';
$string['migrate:count:supporters'] = 'Supporter/innen';
$string['migrate:count:supportforums'] = 'Supportforen';
$string['migrate:description'] = 'Damit werden alle Daten des Plugins local_edusupport in Helpdesk kopiert: Supportforen, Anfragen, Supporter/innen, Abonnements, Einstellungen, wartende Erinnerungen und die Benachrichtigungseinstellungen der Nutzer/innen. Die Rolle des Supportteams und der Gastnutzer für Tickets werden übernommen, damit alle Rollenzuweisungen und Forumsbeiträge unverändert bleiben. Danach kennt local_edusupport kein Supportforum mehr und bleibt deshalb untätig; seine Anfragen, Supporter/innen und Abonnements bleiben als Sicherung unangetastet. Logeinträge und bereits versandte Benachrichtigungen behalten ihren Bezug zu local_edusupport.';
$string['migrate:done'] = 'Migration abgeschlossen: {$a} Datensätze wurden von local_edusupport übernommen. Bitte deinstallieren Sie local_edusupport jetzt.';
$string['migrate:nosource'] = 'Auf dieser Website gibt es keine Tabellen von local_edusupport, daher gibt es nichts zu migrieren.';
$string['migrate:notempty'] = 'Helpdesk enthält bereits Daten. Die Migration behält die IDs der Datensätze bei und läuft daher nur, solange Helpdesk keine Supportforen, Anfragen, Supporter/innen oder Abonnements enthält.';
$string['migrate:records'] = 'Datensätze';
$string['migrate:sourcetooold'] = 'Bitte aktualisieren Sie local_edusupport vor der Migration auf Version 2.8.0 (2026091001) oder neuer.';
$string['migrate:uninstall'] = 'local_edusupport deinstallieren';
$string['migrate:uninstallhint'] = 'Solange local_edusupport installiert ist, zeigt es seinen eigenen Hilfe-Button neben jenem von Helpdesk an. Deinstallieren Sie es, sobald Sie die migrierten Daten geprüft haben. Beim Deinstallieren werden auch seine Tabellen entfernt, die bis dahin als Sicherung dienen.';
$string['migrate:what'] = 'Daten';
$string['missing_permission'] = 'Fehlende Erlaubnis!';
$string['no_such_issue'] = 'Dies ist kein offenes Ticket! Sie können die <a href="{$a->todiscussionurl}"><u>Diskussion direkt im Forum</u></a> aufrufen oder zurück zur <a href="{$a->toissuesurl}"><u>Übersicht der offenen Tickets</u></a> wechseln.';
$string['none'] = 'nichts ausgewählt';
$string['overview'] = 'Alle Support-User';
$string['overview:description'] = 'Alle, die auf dieser Plattform Support leisten: 1st Level wird je Kurs zugewiesen, 2nd Level ist das plattformweite Team.';
$string['phonefield'] = 'Telefonfeld verbergen';
$string['phonefield:description'] = 'Telefonfeld verbergen';
$string['pluginname'] = 'Helpdesk';
$string['possiblemanagers'] = 'Mögliche Manager';
$string['postmailinfolink'] = 'Dies ist die Kopie einer Nachricht, die in {$a->coursename} gepostet wurde.

Klicken Sie hier, um zu antworten: {$a->replylink}';
$string['postto2ndlevel'] = 'Plattform Support Team';
$string['postto2ndlevel:description'] = 'Direkt an den {$a->sitename}-Support weiterleiten!';
$string['predefined_subjects'] = 'Definieren Sie hier vorgegebene Support-Betreffe';
$string['predefined_subjects:description'] = 'Beim Einreichen einer Support-Anfrage können Sie eine Liste von Betreffen voreinstellen, aus der Sie anstelle eines Texteingabefeldes auswählen können. Lassen Sie das Feld leer, wenn Sie die Texteingabe verwenden möchten. Ein Betreff pro Zeile, wenn Sie vordefinierte Betreffe angeben möchten.';
$string['predefined_subjects_prefix'] = 'Aktviere Betreff Prefix';
$string['predefined_subjects_prefix:description'] = 'Aktviere Betreff Prefix (Prefix kann in den Sprachpaketen geändert werden  subject_prefix e.g. Other:)';
$string['prepage'] = "prepage content";
$string['prepage:description'] = "content displayed before form e.g. faq";
$string['priority'] = 'setze Priorität';
$string['prioritylvl'] = 'Prioritäten erlauben';
$string['prioritylvl:description'] = 'ermöglicht es in der Taskliste Prioritäten zu setzen';
$string['prioritylvl:high'] = 'hohe Priorität';
$string['prioritylvl:low'] = 'niedrige Priorität';
$string['prioritylvl:mid'] = 'mittlere Priorität';
$string['privacy:export:dedicated'] = 'Supportforen, in denen Sie fest zuständig sind';
$string['privacy:export:issues'] = 'Tickets, für die Sie zuständig sind';
$string['privacy:export:subscriptions'] = 'Tickets, denen Sie folgen';
$string['privacy:export:supporter'] = 'Wo Sie Support leisten';
$string['privacy:metadata:helpdesk:accountmanager'] = 'Der für das Ticket zuständige Accountmanager';
$string['privacy:metadata:helpdesk:autoassign'] = 'Ob Anfragen automatisch an die Person weitergegeben werden können';
$string['privacy:metadata:helpdesk:courseid'] = 'Der Kurs, in dem die Person Support leistet';
$string['privacy:metadata:helpdesk:currentsupporter'] = 'Die Person, der das Ticket derzeit zugewiesen ist';
$string['privacy:metadata:helpdesk:dedicatedsupporter'] = 'Die Person, die Anfragen aus diesem Supportforum zuerst erhält';
$string['privacy:metadata:helpdesk:discussionid'] = 'Die Forumsdiskussion des Tickets';
$string['privacy:metadata:helpdesk:email'] = 'Die mit der Anfrage angegebene E-Mail-Adresse';
$string['privacy:metadata:helpdesk:forumid'] = 'Das als Supportforum verwendete Forum';
$string['privacy:metadata:helpdesk:guesttickets'] = 'Die E-Mail-Adresse, an die eine nicht angemeldete Person die Antworten auf ihre Anfrage erhalten möchte. Sie gehört zu keinem Nutzerkonto.';
$string['privacy:metadata:helpdesk:holidaymode'] = 'Bis wann die Person abwesend ist';
$string['privacy:metadata:helpdesk:issueid'] = 'Das Ticket';
$string['privacy:metadata:helpdesk:issues'] = 'Supporttickets und wer sie bearbeitet';
$string['privacy:metadata:helpdesk:priority'] = 'Die Priorität des Tickets';
$string['privacy:metadata:helpdesk:status'] = 'Der Status des Tickets';
$string['privacy:metadata:helpdesk:subscr'] = 'Tickets, denen eine Person folgt';
$string['privacy:metadata:helpdesk:supporters'] = 'Personen, die Support leisten, für einen Kurs oder die ganze Plattform';
$string['privacy:metadata:helpdesk:supportforums'] = 'Foren für Supportanfragen';
$string['privacy:metadata:helpdesk:supportlevel'] = 'Die selbst gewählte Bezeichnung der Supportrolle';
$string['privacy:metadata:helpdesk:timecreated'] = 'Wann das Ticket erstellt wurde';
$string['privacy:metadata:helpdesk:timemodified'] = 'Wann das Ticket zuletzt geändert wurde';
$string['privacy:metadata:helpdesk:userid'] = 'Die Person';
$string['rolename'] = 'Rollenname';
$string['rolename:description'] = 'Rollenname für den 1st Level Support (z.B. teacher statt editingtecher oder eine eigens erstellte Rolle)';
$string['scope'] = 'Betreut';
$string['scope:platform'] = 'Die gesamte Plattform';
$string['screenshot'] = 'Screenshot anhängen';
$string['screenshot:invalid'] = 'Der Screenshot ist kein Bild, das angehängt werden kann (PNG, JPEG, GIF oder WebP).';
$string['screenshot:toobig'] = 'Der Screenshot ist zu groß. Es können höchstens {$a} angehängt werden.';
$string['screenshot:upload:failed'] = 'Vorbereitung der Datei fehlgeschlagen!';
$string['screenshot:upload:successful'] = 'Datei erfolgreich für Übertragung vorbereitet!';
$string['seedfirstlevel'] = '1st Level Support aus Kursrechten befüllen';
$string['seedfirstlevel:apply'] = '{$a} Personen zuweisen';
$string['seedfirstlevel:assigned'] = 'Zugewiesen';
$string['seedfirstlevel:description'] = 'Früher galt als 1st Level Support, wer den Supportkurs bearbeiten durfte. Heute wird das je Kurs ausdrücklich zugewiesen. Diese Funktion trägt die Personen ein, die nach der alten Regel gezählt hätten und die neue erfüllen: eingeschrieben, darf eine Diskussion beginnen und verborgene Aktivitäten sehen. Es wird ausschließlich ergänzt, eine bewusst gesetzte Zuweisung verschwindet also nie, und ein zweiter Durchlauf ändert nichts.';
$string['seedfirstlevel:done'] = '{$a} Personen wurden zugewiesen.';
$string['seedfirstlevel:eligible'] = 'Geeignet';
$string['seedfirstlevel:nocourses'] = 'Bisher enthält kein Kurs ein Supportforum.';
$string['seedfirstlevel:nothingtodo'] = 'Alle geeigneten Personen sind bereits zugewiesen.';
$string['seedfirstlevel:toadd'] = 'Würden ergänzt';
$string['select_subject'] = 'Bitte wählen Sie einen Betreff für die Anfrage';
$string['send'] = 'Senden';
$string['sendissueclosed'] = 'Eine E-Mail an den User schicken, wenn der Support-Ticketstatus auf "Erledigt" gestetzt wurde';
$string['sendissueclosed:description'] = 'Zusätztlich zum Eintrag im Support-Forum eine E-Mail an den/die Benutzer/in schicken, wenn das Support-Ticket auf Status "Erledigt" gesetzt wurde';
$string['sendmsgonset2ndlvl'] = 'Sende Nachricht an Nutzer/in wenn ein Ticket einem Support User zugewiesen wird';
$string['sendmsgonset2ndlvl:description'] = 'Sende eine E-Mail an den/die Nutzer/in wenn ein Support-User zugewiesen wird oder geändert wird.';
$string['sendoriginalrequest'] = 'Die ursprüngliche Supportanfrage an den/die Benutzer/in senden';
$string['sendoriginalrequest:description'] = 'Den Forumsbeitrag der Supportanfrage an den Benutzer, der um Unterstützung gebeten hat senden';
$string['sendrequestreceived'] = 'Senden einer E-Mail zur Bestätigung, dass die Supportanfrage eingegangen ist';
$string['sendrequestreceived:description'] = 'Benachrichtigen Sie den/die Benutzer/in ausschließlich per E-Mail (und nicht im Support-Forum), dass die Anfrage eingegangen ist. Dies dient nur der Information des Benutzers und ist für die Dokumentation des Verlaufs der Anfrage im Support-Forum nicht relevant.';
$string['sendsupporterassignments'] = 'Senden von Support-Benutzerzuweisungen an den Benutzer';
$string['sendsupporterassignments:description'] = 'Benachrichtigen Sie den Benutzer per E-Mail, wenn ein Support-User der Anfrage zugewiesen wurde. Jedes Mal, wenn ein neuer Benutzer zugewiesen wird, wird eine E-Mail gesendet';
$string['setaccountmanager'] = 'Setze Account Manager';
$string['showresponsibles'] = 'Ansprechpersonen der anfragenden Person anzeigen';
$string['showresponsibles:description'] = 'Nach dem Abschicken einer Supportanfrage anzeigen, wer sich darum kümmert - namentlich, im Bestätigungsdialog und in einem automatischen Beitrag im Ticket. Abschalten, wenn die Ansprechpersonen eines Kurses ungenannt bleiben sollen. Die Supporter werden in jedem Fall über neue Tickets benachrichtigt.';
$string['spamprotection:exception'] = 'Die maximale Anzahl an Anfragen wurde leider überschritten. Bitte versuchen Sie es in ein paar Minuten noch einmal.';
$string['spamprotection:limit'] = 'Spamschutz > Limit';
$string['spamprotection:limit:description'] = 'Wie viele Anfragen innerhalb des Zeitraums höchstens erstellt werden können.';
$string['spamprotection:threshold'] = 'Spamschutz > Minuten';
$string['spamprotection:threshold:description'] = 'Der Zeitraum, der für den Spamschutz herangezogen wird.';
$string['startedby'] = 'Gestartet von';
$string['status'] = 'Status';
$string['status:awaitingsupportaction'] = 'Erwarte Bearbeitung durch Supporter:in';
$string['status:awaitinguserreply'] = 'Erwarte Antwort des/der Benutzer:in';
$string['status:closed'] = 'Abgeschlossen';
$string['status:notstarted'] = 'Noch nicht gestartet';
$string['status:ongoing'] = 'In Bearbeitung';
$string['subject'] = 'Betreff';
$string['subject_missing'] = 'Bitte geben Sie einen stichwortartigen Titel an, der das Problem beschreibt!';
$string['subject_prefix'] = 'Supportanfrage zu folgendem Thema: ';
$string['supportadded'] = "Supportuser hinzugefgügt";
$string['supportchanged'] = "Supportuser geändert";
$string['supportcourse'] = 'Supportkurs';
$string['supportdeleted'] = "Supportuser gelöscht";
$string['supporters'] = 'Supportmitarbeiter/innen';
$string['supportforum:central:disable'] = 'deaktivieren';
$string['supportforum:central:enable'] = 'aktivieren';
$string['supportforum:choose'] = 'Foren für Helpdesk auswählen';
$string['supportforum:disable'] = 'deaktivieren';
$string['supportforum:enable'] = 'aktivieren';
$string['supportlevel'] = 'Supportlevel';
$string['targetforum'] = 'Supportforum';
$string['timebeforereminder'] = 'Zeit bevor Erinnerung gesendet wird';
$string['to_group'] = 'An';
$string['toggle'] = 'Kurssupportforum';
$string['toggle:central'] = 'Zentrales Supportforum';
$string['tooltiptext'] = 'Kontakt- und Supportformular';
$string['trackhost'] = 'Hostnamen angeben';
$string['trackhost:description'] = 'Große Moodle-Sites nutzen möglicherweise eine Architektur mit mehreren Webhosts. Schalten Sie diese Option ein, damit der Hostname des aktiven Webhosts bei Problemen erfasst wird.';
$string['userid'] = 'UserID';
$string['userlinks'] = 'Userlinks';
$string['userlinks:description'] = 'zeige Userlinks in Taskliste';
$string['webhost'] = 'Host';
$string['weburl'] = 'URL';
