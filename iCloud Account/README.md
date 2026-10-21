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

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
