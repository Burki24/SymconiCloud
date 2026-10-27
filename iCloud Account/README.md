# iCloud Konto

Das Konto ist die gemeinsame Verbindung zu Apple für Geräte, WoIst-Karte,
Anwesenheit, Fotos und Kontakte. Hier werden die Zugangsdaten einmal eingegeben;
die anderen Module verwenden diese Verbindung ohne eigene Anmeldung.
Voraussetzung: Symcon 9.0, PHP 8.5 mit `curl` und `bcmath`, globaler Apple-Account
sowie ein vertrauenswürdiges Gerät. Eine zusätzliche I/O-Instanz ist nicht nötig.

## Ersteinrichtung

1. **iCloud Konto** als Instanz anlegen und **Konto aktivieren** einschalten.
2. Apple-Account und reguläres Passwort eingeben. Kein anwendungsspezifisches
   Passwort für CalDAV/CardDAV verwenden.
3. **Übernehmen** wählen und danach **Anmelden** anklicken. Die Rückmeldung im
   Formular zeigt das Ergebnis und einen gegebenenfalls erforderlichen nächsten Schritt.
4. Falls Apple einen Bestätigungscode verlangt, diesen am vertrauenswürdigen
   Gerät ablesen und im Formular unter **Zwei-Faktor-Authentifizierung** bestätigen.
5. Nach erfolgreicher Anmeldung den
   [Gerätekonfigurator](../iCloud%20Device%20Configurator/README.md) oder die
   gewünschten anderen Module anlegen und mit diesem Konto verbinden.

Fotos und Kontakte sind zunächst ausgeschaltet. Sie können später unabhängig
voneinander aktiviert werden; für Geräte, Karte und Anwesenheit bleiben sie aus.

## Konfiguration

| Eigenschaft | Standard | Bedeutung |
| --- | --- | --- |
| `Enabled` | false | Konto und regelmäßige Geräteabfrage aktivieren |
| `PauseRequests` | false | Alle Apple-Anfragen pausieren, Sitzung und vorhandene Caches erhalten |
| `AllowAuthentication` | true | Neue Anmeldungen, Code-Prüfungen und Code-Anforderungen auf diesem System erlauben; bestehende Sitzungen werden auch bei false weiterverwendet |
| `Username` | leer | Apple-Account-Anmeldename |
| `Password` | leer | Reguläres Apple-Passwort, kein anwendungsspezifisches Passwort |
| `RefreshInterval` | 300 | Sekunden zwischen Geräteabfragen, 60 bis 86400 |
| `EnableContacts` | false | Lesenden Kontaktzugriff einschalten |
| `ContactsRefreshInterval` | 3600 | Sekunden zwischen Kontaktabfragen, 900 bis 604800 |
| `EnablePhotos` | false | Lesenden Fotozugriff einschalten |
| `PhotoCacheProfile` | `compact` | Ressourcenprofil: Kompakt, Standard, Groß oder Eigene Grenzen; siehe Fotoanleitung |
| `PhotoCacheImages` | 50 | Eigene maximale Bildanzahl, nur beim Profil Eigene Grenzen wirksam |
| `PhotoCacheMiB` | 100 | Eigenes Bilddateibudget in MiB, nur beim Profil Eigene Grenzen wirksam |

Einstellungen speichern, **Anmelden** wählen und den sechsstelligen Code des vertrauenswürdigen Gerätes bestätigen. **Neuen Code anfordern** sendet eine neue Anfrage für die laufende Bestätigung. Nach zehn Minuten ist eine neue Anmeldung erforderlich. Alternative Verfahren mit SMS oder Hardware-Sicherheitsschlüsseln sind nicht enthalten.

Direkt über **Anmelden** erscheint das Ergebnis der letzten Anmeldeaktion:
erfolgreiche Anmeldung, Weiterverwendung einer vorhandenen Sitzung, benötigter
Bestätigungscode oder eine konkrete Ablehnung. Bei einer Wartefrist werden die
noch verbleibenden Sekunden zum Zeitpunkt des Klicks angezeigt. Die Rückmeldung
bleibt beim Neuaufbau des Formulars erhalten und wird nicht von regelmäßigen
Geräteabfragen überschrieben. Passwörter, Codes und technische Apple-Antworten
werden darin nicht ausgegeben.

Ein von Apple ausdrücklich als gültig bestätigter Code wird auch dann
weiterverarbeitet, wenn Apple dafür HTTP 409 zurückgibt. Erst wenn anschließend
die Vertrauensbestätigung, die Kontositzung und der Geräteabruf erfolgreich sind,
zeigt das Modul die Anmeldung als erfolgreich an. Uneindeutige Antworten und
Apples Sicherheitsablehnungen bleiben Fehler.

Bei **HTTP 202** ist der Authentifizierungsschritt noch nicht abgeschlossen.
Das Modul zeigt dafür einen eigenen Hinweis und startet weder eine automatische
Wiederholung noch eine neue Passwortanmeldung. Dieser Status allein bestätigt
weder einen falschen Code noch eine erfolgreiche Anmeldung. Vor einem weiteren
Versuch das Konto in einer iCloud-Browsersitzung prüfen; die bestehenden
Wartefristen und Versuchslimits gelten weiterhin.

Die Sitzung bleibt nach einem Symcon-Neustart erhalten. Eine Änderung der Zugangsdaten oder das Deaktivieren löscht die lokale Sitzung und den Gerätecache. **Lokale Sitzung löschen** meldet nur diese Instanz lokal ab; der Apple-Account wird nicht gesperrt und Geräte werden nicht verändert. Erneutes Aktivieren allein startet keine Anmeldung.

## Lesender Kontaktzugriff

Unter **iCloud Kontakte (optional)** den Zugriff aktivieren und die Konfiguration
übernehmen. **Kontakte aktualisieren** liest das iCloud-Adressbuch mit derselben
Kontositzung. Ein eigener Timer aktualisiert es standardmäßig stündlich;
manuelle Versuche benötigen mindestens 60 Sekunden Abstand. Weder der Timer
noch die Lesefunktionen starten eine Passwortanmeldung.

Die Instanz [iCloud Kontakte](../iCloud%20Contacts/README.md) zeigt den gemeinsamen
Cache als Empfängerverzeichnis. Der Kontaktzugriff beachtet Pause, Schutzsperre
und Wartefristen des Kontos. Eine nur auf Kontakte beschränkte Zugriffsablehnung
lässt Geräte und Fotos weiter nutzbar. Eine abgelehnte gemeinsame Sitzung
stoppt dagegen auch die anderen Abfragen.

`ICAC_GetContacts($InstanceID, $Query, $Offset, $Limit)` liest eine begrenzte
JSON-Seite aus dem Cache; `ICAC_GetContact($InstanceID, $ContactID)` sucht exakt
nach einer Kennung. `ICAC_RefreshContacts($InstanceID)` aktualisiert ausdrücklich
den gemeinsamen Kontaktbestand. Parameter und Rückgabefelder entsprechen der
Kontakte-Anleitung. Bei Fehlern bleibt der vorherige vollständige Cache erhalten
und wird als veraltet gekennzeichnet. Ausschalten des Kontaktzugriffs oder
Löschen der Kontositzung entfernt ihn.

## Kontoschutz und Abfragepause

Für eine Unterbrechung **Apple-Abfragen pausieren (Sitzung erhalten)** aktivieren
und die Konfiguration übernehmen. Diese Pause stoppt auch manuelle Anmeldungen,
Fotoabfragen und Geräteaktionen. Lokale Cachelesevorgänge bleiben möglich;
vorhandene Daten werden dadurch nicht aktualisiert. Zum Fortsetzen die Pause
deaktivieren und übernehmen. Anders als beim Deaktivieren des Kontos bleiben
Sitzung, Cookies, Vertrauensdaten und Gerätecache erhalten.

Pro Kontoinstanz gelten feste, rollierende Grenzen:

| Vorgang | Grenze |
| --- | --- |
| Passwort-Anmeldung | 3 Versuche je 30 Minuten, mindestens 60 Sekunden Abstand |
| Prüfung eines sechsstelligen Bestätigungscodes | 5 Versuche je 10 Minuten |
| Code-Anforderung einschließlich erster Zustellung bei der Anmeldung | 3 Versuche je 10 Minuten, mindestens 60 Sekunden Abstand |

Auch fehlgeschlagene Anmeldeversuche zählen. Neustart, Abmeldung, Änderung der
Zugangsdaten und Löschen des Diagnoseverlaufs setzen diese Grenzen nicht zurück.
Bei bereits angemeldeter Sitzung startet **Anmelden** keinen weiteren
Passwortversuch. Während einer laufenden Bestätigung wird keine neue
Passwortanmeldung gestartet. Timer führen grundsätzlich keine Passwortanmeldung aus.

Nach einer Authentifizierungs- oder Zugriffsablehnung werden weitere Apple-Anfragen
bis zur manuellen Prüfung gestoppt. HTTP 423 gilt konservativ als Sperrantwort;
dieser Status allein beweist keine bestimmte Apple-Sicherheitsentscheidung.
Den Apple-Account auf einem vertrauenswürdigen Gerät prüfen und gegebenenfalls
entsperren. Anschließend **Nach Prüfung des Apple-Accounts wieder freigeben**
bestätigen. Dieser Schritt sendet keine Apple-Anfrage und umgeht weder Wartefristen
noch Versuchslimits. Eine abgelaufene Sitzung benötigt danach eine ausdrückliche
Anmeldung; eine noch gültige Sitzung kann weiterverwendet werden.

Alternativ übernimmt **Anmelden** bei einer lokalen Schutzsperre beide Schritte:
Nach Prüfung des Apple-Kontos die Rückfrage zum Freigeben und Starten eines
Anmeldeversuchs bestätigen. Abbrechen belässt die Schutzsperre unverändert und
sendet nichts an Apple. Eine lokale Schutzsperre ist kein Nachweis einer
Apple-Kontosperre. Die kombinierte Aktion respektiert weiterhin Pause,
Wartefristen und Versuchslimits; wiederholte Klicks erlauben keine zusätzlichen
Passwortversuche. Während einer Abfragepause wird die Sperre nicht freigegeben.

HTTP 429 bleibt eine zeitlich begrenzte Drosselung nach Apples `Retry-After`
(60 Sekunden bis 24 Stunden). Netzwerk- und Serverfehler erhalten eine getrennte,
anwachsende Wartezeit von zunächst 60 Sekunden bis höchstens einer Stunde.
Fehlende Foto-Webfreigaben stoppen nur den Fotozugriff; eine Ablehnung des
gemeinsamen Sitzungstokens betrifft auch Geräteabfragen. Nicht vorübergehende
Fehler bei der Sitzungserneuerung beenden weitere automatische Erneuerungen.

Die Grenzen koordinieren keine anderen Kontoinstanzen, Symcon-Server oder
externen iCloud-Anbindungen. Apple entscheidet über Kontosperren; das Modul
reduziert vermeidbare Wiederholungen, kann Sperren aber nicht ausschließen.

### Apple-Sicherheitsprüfung und Kontosperre unterscheiden

Eine abgelehnte Anmeldung allein beweist kein falsches Passwort. Das Modul
unterscheidet eine abgelaufene Sitzung, eine allgemeine Zugriffsablehnung,
eine Sperrantwort, eine zusätzliche Kontoprüfung und ausstehende
iCloud-Nutzungsbedingungen. Bekannte Apple-Fehlercodes werden vor der
Auswertung einzelner Dienste geprüft: Meldet beispielsweise Fotos eine
Kontosperre mit `-20209`, werden auch Geräte- und Kontaktabfragen gestoppt.
Eine normale, nur auf Fotos oder Kontakte beschränkte Zugriffsablehnung bleibt
auf diesen Dienst beschränkt. Eine Sperrantwort ohne bekannten Dienstcode
belegt den genauen Sperrgrund nicht.

Bei einer Sicherheitsprüfung iCloud im Browser öffnen und die von Apple
angeforderten Schritte durchführen. Je nach Fall genügen eine PIN und das
bestehende Passwort; eine Passwortänderung ist nicht immer erforderlich.
Das Modul fordert keinen Passwortwechsel aufgrund einer bloßen HTTP-Ablehnung.
Eine zusätzliche Kontoprüfung (HTTP 412 im Anmeldeablauf) und neue
Nutzungsbedingungen stoppen weitere Anfragen bis zur manuellen Prüfung.
Das Modul stimmt Bedingungen nicht automatisch zu und wiederholt keine
Passwortanmeldung zur Umgehung einer solchen Prüfung.

### Dasselbe Konto auf Test- und Produktivsystem

Alle Module eines Systems möglichst mit derselben Kontoinstanz verbinden.
Für ein zusätzliches System steht **Neue Anmeldungen und Bestätigungen auf
diesem System erlauben** zur Verfügung:

1. Das Zweitsystem bei Bedarf ausdrücklich anmelden und die Sitzung prüfen.
2. Anschließend diese Option ausschalten und **Übernehmen** wählen.
3. Die bestehende Sitzung bleibt erhalten. Reguläre Abfragen und die einmalige
   Token-Erneuerung bleiben möglich; neue Passwortanmeldungen, Code-Prüfungen
   und Code-Anforderungen werden vor jedem Apple-Zugriff lokal abgewiesen.
4. Wird die Sitzung abgewiesen, bleibt die Schutzsperre bestehen. Nur auf dem
   System, auf dem bewusst angemeldet werden soll, die Option wieder einschalten.
5. Ein ungenutztes Testsystem zusätzlich über **Apple-Abfragen pausieren
   (Sitzung erhalten)** pausieren. Diese Pause stoppt auch reguläre Abfragen.

Die neue Option ist standardmäßig eingeschaltet; bestehende Installationen
ändern ihr Anmeldeverhalten durch das Update nicht. Sie koordiniert keine
Sperren oder Versuchszähler über getrennte Server und reduziert keine laufenden
Datenabfragen. Eine automatische serverübergreifende Koordination würde einen
gemeinsamen Vermittler benötigen. Das Modul richtet dafür keine neue Verbindung
ein und überträgt keine Zugangsdaten zwischen Systemen.

## Datensparsame Kontodiagnose

Das Formular zeigt die letzten 50 Einträge mit Zeitpunkt, fester Anfrageart,
HTTP-Status, festem Ergebnisschlüssel und verbleibender Wartezeit. HTTP-Status 0
kennzeichnet lokale Ergebnisse oder einen Transportfehler ohne HTTP-Antwort.
Der begrenzte Verlauf bleibt über Neustarts erhalten und lässt sich getrennt
löschen. Die Einträge werden zusätzlich über den zentralen `DebugHelper` in den
Symcon-Debugkanal geschrieben, sofern dieser aktiviert ist.

Daneben zeigt **Kontosicherheit (letzte 200 Ereignisse, 30 Tage)** einen
eigenen persistenten Verlauf für Anmeldungen, Bestätigungen, Token-Erneuerungen,
Pausen, Freigaben und Fehler. Erfolgreiche Geräte-, Kontakt- und Fotoabfragen
verdrängen diese Ereignisse nicht; wiederholte lokale Abweisungen regulärer
Abfragen während einer Pause oder Schutzsperre ebenfalls nicht. Im Formular
werden Ergebnisse verständlich auf Deutsch erläutert. Der zusätzliche
**Apple-Fehlercode** enthält ausschließlich bekannte Codes (`-20209`,
`-20101`), niemals freie Antworttexte oder unbekannte Kennungen. `-20101`
kennzeichnet die Ablehnung eines Anmeldeversuchs, beweist aber kein tatsächlich
falsches Passwort.

Der Verlauf zeigt höchstens 200 Ereignisse aus den letzten 30 Tagen; ältere
gespeicherte Einträge werden beim nächsten Diagnoseeintrag bereinigt. Noch
vorhandene sicherheitsrelevante Einträge aus dem bisherigen 50er-Verlauf werden
beim ersten neuen Eintrag übernommen. Bereits verdrängte Ereignisse lassen
sich nicht nachträglich rekonstruieren. Das Öffnen des Formulars löst keinen
Apple-Zugriff aus.

**Diagnoseverlauf löschen** löscht weiterhin nur den bisherigen 50er-Verlauf.
**Sicherheitsverlauf löschen** ist separat zu bestätigen. Beide Aktionen
lassen Sitzung, Schutzsperren, Wartezeiten und Versuchszähler unverändert.
Für eine lesende Auswertung stehen `ICAC_GetAccountDiagnostics($InstanceID)`
und `ICAC_GetAccountSecurityDiagnostics($InstanceID)` zur Verfügung. Der neue
Verlauf ergänzt die bisherigen Felder um `appleCode`; Ergebnisschlüssel bleiben
in der JSON-Ausgabe technisch eindeutig. Die bisherigen fünf Felder der
50er-Diagnose bleiben unverändert.

Passwörter, Bestätigungscodes, Cookies, Tokens, Anmeldenamen, Geräte-/Albumkennungen,
URLs und Antwortinhalte werden nicht protokolliert. Ein unlesbarer Zustand der
Versuchszähler stoppt neue Anfragen, statt die Limits stillschweigend zurückzusetzen.

| Status | Bedeutung |
| --- | --- |
| 206 | Apple-Drosselung bzw. bestehende Apple-Wartefrist |
| 207 | Abfragepause, Sitzung erhalten |
| 208 | Anfragen gestoppt, manuelle Kontoprüfung erforderlich |
| 209 | Lokales Limit für Authentifizierungsversuche erreicht |
| 210 | Wartezeit nach Netzwerk- oder Serverfehler |
| 211 | Neue Anmeldung lokal ausgeschaltet, keine angemeldete Sitzung vorhanden |

## Status und Daten

Das Modul besitzt keine Statusvariablen. Formular und Instanzstatus unterscheiden angemeldet, abgemeldet/deaktiviert, erforderliche 2FA-Bestätigung, abgelaufene Sitzung, ungültige Konfiguration, fehlende PHP-Erweiterungen und Abfragefehler. Bei einer Apple-Sperrfrist werden vorübergehend keine weiteren Anfragen gesendet. Ungültige Codes können innerhalb der laufenden Bestätigung korrigiert werden.

Kinder erhalten nur normalisierte Daten der ausdrücklich aktivierten Dienste,
keine Zugangsdaten oder Sitzungstokens. Bei Geräteabfragefehlern bleibt der
letzte Gerätecache erhalten, wird aber als nicht aktuell verfügbar
gekennzeichnet. Die Geräteabfrage liest vorhandene Daten und fordert keine
aktive Neuortung an.

Die ausdrücklich ausgelöste Geräteaktion **Handy suchen** verwendet denselben
HTTPS-Transport, dieselbe Sitzung und dieselbe Anfragesperre. Sie akzeptiert nur
Geräte aus dem Cache dieses Kontos. Es gibt keinen frei konfigurierbaren
HTTP-Befehl. Suchton-Aufträge werden nicht automatisch wiederholt; eine reine
Ton-Ablehnung macht einen ansonsten verwendbaren Standortcache nicht ungültig.
Schlägt dagegen die Authentifizierung fehl, wird die Sitzung als abgelaufen
gekennzeichnet. Kinder erhalten ausschließlich das bereinigte Aktionsergebnis.

Die bestätigte **Verloren-Modus-Aktivierung und -Deaktivierung** der Geräteinstanz nutzt dieselben
Kontogrenzen, Sitzung und Sperrfristen. Zusätzlich werden eine ausdrückliche
Bestätigung, die von Apple gemeldete Unterstützung und die begrenzten
Kontakt-/Codefelder geprüft. Bereits aktivierte Geräte erhalten keinen weiteren
Aktivierungsauftrag. Eine Deaktivierung ist auch bei noch nicht bestätigtem
Aktivierungsstatus möglich und sendet ausschließlich feste Ausschaltwerte.
Der jeweilige Auftrag wird genau einmal gesendet, ohne automatische Wiederholung
oder erneute Anmeldung. Kontaktangaben und Gerätecode werden nicht im Konto
gespeichert. Eine angenommene Anfrage verändert den Gerätestatus im Cache
nicht; dieser folgt der nächsten regulären Geräteabfrage. Bedienung und
Auswirkungen sind in der [Geräteanleitung](../iCloud%20Device/README.md#verloren-modus)
beschrieben.

## Öffentliche Funktionen

| Funktion | Rückgabe | Bedeutung |
| --- | --- | --- |
| `ICAC_Login(int $InstanceID)` | bool | Vorhandene Anmeldung nutzen oder begrenzt eine neue starten; bei benötigtem Code zunächst false, sonst true nach erfolgreicher Anmeldung und Geräteabfrage |
| `ICAC_LoginFromForm(int $InstanceID, bool $ConfirmedRecovery)` | bool | Formularaktion mit Ergebnisanzeige; nur bei `true` die vorhandene lokale Schutzsperre vor dem begrenzten Anmeldeversuch ausdrücklich freigeben; keine Umgehung von Pause oder Limits |
| `ICAC_ResumeAccountRequests(int $InstanceID)` | void | Lokale Schutzsperre nach Kontoprüfung freigeben; keine Anmeldung und kein Zurücksetzen von Limits |
| `ICAC_GetAccountDiagnostics(int $InstanceID)` | string | Bereinigten Diagnoseverlauf als JSON lesen |
| `ICAC_ClearAccountDiagnostics(int $InstanceID)` | void | Nur den Diagnoseverlauf löschen |
| `ICAC_VerifyCode(int $InstanceID, string $Code)` | bool | Sechsstelligen Code bestätigen und Geräteliste abrufen |
| `ICAC_ResendCode(int $InstanceID)` | bool | Code für die laufende Bestätigung erneut anfordern |
| `ICAC_RefreshDevices(int $InstanceID)` | bool | Geräteliste aktualisieren; keine Passwort-Anmeldung starten |
| `ICAC_GetDevices(int $InstanceID)` | string | JSON des lokalen Gerätecaches ohne Netzwerkzugriff |
| `ICAC_Logout(int $InstanceID)` | void | Lokale Sitzung und Cache entfernen |

Der JSON-Cache enthält `success`, `state`, `updatedAt` (Unix-Sekunden), `devices` und `error`. Geräte enthalten `id`, `name`, `model`, `deviceClass`, `deviceStatus`, `batteryLevel` (0 bis 1 oder null), `batteryStatus` und `location` (Koordinaten, Genauigkeit, Unix-Zeitpunkt, Alterskennzeichnung oder null). Zugangsdaten, Cookies und Tokens werden nicht ausgegeben. Die Geräte-ID zusammen mit der Kontoverbindung bestimmt die Gerätezuordnung.

Zusätzlich enthält jedes Gerät `modelIdentifier` (technische Modellkennung
oder null) und `details` mit den optionalen booleschen Feldern `lowPowerMode`,
`locationEnabled`, `locationCapable`, `isLocating`, `lostModeEnabled`,
`lostModeCapable`, `activationLocked` und `fmlyShare`. Fehlende oder ungültige
Werte sind null, nicht false. Bei vorhandenem Standort ergänzen
`location.isInaccurate` und `location.finished` Apples Kennzeichnungen, ebenfalls
als Boolean oder null. Bestehende Cachefelder und Abfrageintervalle bleiben
unverändert. Rohantworten, Befehlsdaten und zusätzliche Kennungen werden nicht
an Kinder weitergereicht.

## Optionaler Fotozugriff

Unter **iCloud Fotos (optional)** wird der lesende Fotozugriff ausdrücklich
aktiviert und das gemeinsame Ressourcenprofil gewählt: **Kompakt** (Standard:
50 Bilder/100 MiB), **Standard** (250/500 MiB), **Groß** (1000/2 GiB) oder
**Eigene Grenzen** (`PhotoCacheImages` und `PhotoCacheMiB`). Es gilt jeweils die
zuerst erreichte Grenze; das Konto entfernt länger nicht verwendete Vorschauen.
Diese Grenzen gelten gemeinsam für alle verbundenen Foto-Widgets.

Nach dem Übernehmen **Fotozugriff prüfen und Alben laden** wählen. Bestehende
Sitzungen entdecken den Fotodienst mit ihrem Sitzungstoken; ein Passwort-Login
wird dabei nicht automatisch gestartet. Fehlender Foto-Webzugriff verhindert
keine WoIst-Geräteabfrage. Die [Fotoanleitung](../iCloud%20Photos/README.md)
beschreibt Einrichtung, Apple-Freigaben, Datenschutz und Cachegrenzen.

`ICAC_RefreshPhotoAlbums($InstanceID)` aktualisiert den Albumkatalog lesend;
`ICAC_GetPhotoAlbums($InstanceID)` liest ausschließlich dessen lokalen Stand,
Zugriffsstatus und Cacheauslastung. Zugangsdaten und signierte Bildadressen
werden nicht ausgegeben. Abmelden, deaktivierter Fotozugriff und geänderte
Zugangsdaten entfernen auch lokale Fotodaten.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
