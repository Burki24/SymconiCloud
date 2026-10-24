# iCloud Account / iCloud Konto

Das Kontomodul verwaltet die Apple-Anmeldung, Zwei-Faktor-Bestätigung, Sitzung und gemeinsame WoIst-Geräteabfrage. Es ist ein Splitter ohne zusätzliche I/O-Instanz. Voraussetzung: Symcon 9.0, PHP 8.5 mit `curl` und `bcmath`, globaler Apple-Account sowie ein vertrauenswürdiges Gerät.

## Konfiguration

| Eigenschaft | Standard | Bedeutung |
| --- | --- | --- |
| `Enabled` | false | Konto und regelmäßige Geräteabfrage aktivieren |
| `Username` | leer | Apple-Account-Anmeldename |
| `Password` | leer | Reguläres Apple-Passwort, kein anwendungsspezifisches Passwort |
| `RefreshInterval` | 300 | Sekunden zwischen Geräteabfragen, 60 bis 86400 |

Einstellungen speichern, **Anmelden** wählen und den sechsstelligen Code des vertrauenswürdigen Gerätes bestätigen. **Neuen Code anfordern** sendet eine neue Anfrage für die laufende Bestätigung. Nach zehn Minuten ist eine neue Anmeldung erforderlich. Alternative Verfahren mit SMS oder Hardware-Sicherheitsschlüsseln sind nicht enthalten.

Die Sitzung bleibt nach einem Symcon-Neustart erhalten. Eine Änderung der Zugangsdaten oder das Deaktivieren löscht die lokale Sitzung und den Gerätecache. **Lokale Sitzung löschen** meldet nur diese Instanz lokal ab; der Apple-Account wird nicht gesperrt und Geräte werden nicht verändert. Erneutes Aktivieren allein startet keine Anmeldung.

## Status und Daten

Das Modul besitzt keine Statusvariablen. Formular und Instanzstatus unterscheiden angemeldet, abgemeldet/deaktiviert, erforderliche 2FA-Bestätigung, abgelaufene Sitzung, ungültige Konfiguration, fehlende PHP-Erweiterungen und Abfragefehler. Bei einer Apple-Sperrfrist werden vorübergehend keine weiteren Anfragen gesendet. Ungültige Codes können innerhalb der laufenden Bestätigung korrigiert werden.

Kinder erhalten nur normalisierte Gerätedaten. Bei Abfragefehlern bleibt der letzte Cache erhalten, wird aber als nicht aktuell verfügbar gekennzeichnet. Die Abfrage liest vorhandene Daten und fordert keine aktive Neuortung an.

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
| `ICAC_Login(int $InstanceID)` | bool | Anmeldung starten; bei benötigtem Code zunächst false, nach vollständig erfolgreichem Abruf true |
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
