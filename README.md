# SymconiCloud

SymconiCloud verbindet Apple-iCloud-Dienste mit IP-Symcon. Der erste Dienst ist **WoIst**: Apple-Anmeldung mit Zwei-Faktor-Bestätigung, lesender Abruf der verfügbaren Geräte und Anzeige der von Apple gelieferten Geräte-, Akku- und Standortdaten.

Die Library ist für spätere Dienste erweiterbar. Aktuell sind ausschließlich die hier beschriebenen WoIst-Funktionen enthalten. Kalender, Kontakte, Fotos und iCloud Drive sind nicht enthalten.

## Voraussetzungen

- IP-Symcon 9.0 mit PHP 8.5 und den PHP-Erweiterungen `curl` und `bcmath`.
- Apple-Account mit aktiviertem „Wo ist?“ und Zugriff auf ein vertrauenswürdiges Gerät für den sechsstelligen Bestätigungscode.
- Das reguläre Apple-Account-Passwort; anwendungsspezifische CalDAV-Passwörter funktionieren hier nicht.
- HTTPS-Verbindung zu Apple. Unterstützt wird der globale iCloud-Dienst; der chinesische iCloud-Dienst und Hardware-Sicherheitsschlüssel/SMS als alternative 2FA-Verfahren sind derzeit nicht implementiert.

Die Anbindung verwendet Apples Web-Endpunkte. Apple stellt dafür keine zugesicherte öffentliche WoIst-Entwicklerschnittstelle bereit; Änderungen bei Apple können erneute Anmeldung oder Anpassungen erfordern. Dieser erste Entwicklungsstand ist noch nicht mit einem echten Apple-Konto abgenommen.

## Installation und Einrichtung

1. `https://github.com/Burki24/SymconiCloud` in der Modulverwaltung als Bibliothek hinzufügen. Die erste Entwicklung liegt auf `dev`.
2. [iCloud Account](iCloud%20Account/README.md) als **iCloud Konto** anlegen, aktivieren, Zugangsdaten speichern und **Anmelden** wählen.
3. Den Code vom vertrauenswürdigen Apple-Gerät unter **Zwei-Faktor-Authentifizierung** eingeben und bestätigen. Nach erfolgreicher Anmeldung wird die Geräteliste abgerufen.
4. [iCloud WoIst Configurator](iCloud%20WoIst%20Configurator/README.md) anlegen und mit diesem Konto verbinden.
5. Geräte über den Konfigurator als [iCloud WoIst Device](iCloud%20WoIst%20Device/README.md) anlegen.

Ein Konto kann mehrere Geräte bedienen; mehrere Apple-Konten verwenden getrennte Kontoinstanzen. Das Konto speichert Sitzung und Gerätecache über Symcon-Neustarts hinweg. Passwörter und Sitzungstokens gehören zu den vertraulichen Instanzdaten; Symcon-Konfiguration und Backups entsprechend schützen.

## Verhalten

Das Konto aktualisiert die Geräteliste nach erfolgreicher Anmeldung standardmäßig alle 300 Sekunden. Das Öffnen von Formularen oder das Lesen von Gerätevariablen startet keine Apple-Anmeldung. Nach abgelaufener Sitzung meldet das Konto den erforderlichen Anmeldeschritt; es führt keine automatischen Passwort-Anmeldeschleifen aus.

Die Abfrage fordert keine aktive Neuortung an. Apple kann deshalb ältere oder keine Standortdaten liefern. Gerätevariablen zeigen die Datenverfügbarkeit und den Standortzeitpunkt gesondert an. Tonwiedergabe, Verloren-Modus, Sperren und Löschen sind nicht verfügbar.

[Änderungen](CHANGELOG.md)

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](LICENSE).
