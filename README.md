# SymconiCloud

SymconiCloud verbindet Apple-iCloud-Dienste mit IP-Symcon. Die Library bietet Apple-Anmeldung mit Zwei-Faktor-Bestätigung, einen Gerätekonfigurator und **iCloud Gerät** zur Anzeige der von Apple gelieferten Geräte-, Akku- und Standortdaten.

**[WoIst](iCloud%20WoIst/README.md)** zeigt die verfügbaren Positionen aller Geräte eines Kontos gemeinsam auf einer Karte in der Kachelvisualisierung oder als IPSView-WebContent. Beide Ausgaben besitzen einen Designer; IPSView kann das Kachel-Design übernehmen. Kalender und iCloud Drive sind nicht enthalten.

**[iCloud Anwesenheit](iCloud%20Presence/README.md)** wertet pro Instanz mehrere
ausgewählte Geräte in gemeinsamen, frei konfigurierbaren Kreiszonen aus. Gesamt-
und Geräteanwesenheit unterscheiden
**Unbekannt**, **Abwesend** und **Anwesend** bezogen auf eine Referenzzone.
Entfernung, Aufenthaltsdauer und GPS-Stabilisierung nutzen denselben Kontocache.

**[iCloud Fotos](iCloud%20Photos/README.md)** zeigt ein Album oder Favoriten als Einzelbild oder Diashow in Kachel und IPSView. Ein gemeinsamer Kontocache mit konfigurierbaren Ressourcenprofilen lädt Vorschauen bedarfsgerecht.

**[iCloud Kontakte](iCloud%20Contacts/README.md)** stellt ein lesendes, durchsuchbares
Empfängerverzeichnis mit Telefonnummern und E-Mail-Adressen aus dem gemeinsamen
Kontocache bereit. Die vorhandene Sitzung und der Kontoschutz werden verwendet.
Ein Nachrichtenversand ist darin nicht enthalten.

## Voraussetzungen

- IP-Symcon 9.0 mit PHP 8.5 und den PHP-Erweiterungen `curl` und `bcmath`.
- Apple-Account mit aktiviertem „Wo ist?“ und Zugriff auf ein vertrauenswürdiges Gerät für den sechsstelligen Bestätigungscode.
- Das reguläre Apple-Account-Passwort; anwendungsspezifische CalDAV-Passwörter funktionieren hier nicht.
- HTTPS-Verbindung zu Apple. Unterstützt wird der globale iCloud-Dienst; der chinesische iCloud-Dienst und Hardware-Sicherheitsschlüssel/SMS als alternative 2FA-Verfahren sind derzeit nicht implementiert.

Die Anbindung verwendet Apples Web-Endpunkte. Apple stellt dafür keine zugesicherte öffentliche WoIst-Entwicklerschnittstelle bereit; Änderungen bei Apple können erneute Anmeldung oder Anpassungen erfordern. Die Library befindet sich in der Testphase.

## Installation und Einrichtung

1. `https://github.com/Burki24/SymconiCloud` in der Modulverwaltung als Bibliothek hinzufügen. Die erste Entwicklung liegt auf `dev`.
2. [iCloud Account](iCloud%20Account/README.md) als **iCloud Konto** anlegen, aktivieren, Zugangsdaten speichern und **Anmelden** wählen.
3. Den Code vom vertrauenswürdigen Apple-Gerät unter **Zwei-Faktor-Authentifizierung** eingeben und bestätigen. Nach erfolgreicher Anmeldung wird die Geräteliste abgerufen.
4. [iCloud Device Configurator](iCloud%20Device%20Configurator/README.md) anlegen und mit diesem Konto verbinden.
5. Geräte über den Konfigurator als [iCloud Device](iCloud%20Device/README.md) anlegen.
6. Für die Kartenansicht **iCloud WoIst Karte** anlegen, mit dem Konto verbinden und in der Kachelvisualisierung hinzufügen. Einzelne Geräteinstanzen sind für die Karte nicht erforderlich.
7. Für Anwesenheit im Konfigurator unter **Anwesenheitsgruppen** eine Gruppe
   anlegen, Geräte auswählen, gemeinsame Zonen definieren und genau eine Referenzzone markieren. Karten- und
   Geräteinstanzen sind dafür nicht erforderlich.

Ein Konto kann mehrere Geräte bedienen; mehrere Apple-Konten verwenden getrennte Kontoinstanzen. Das Konto speichert Sitzung und Gerätecache über Symcon-Neustarts hinweg. Passwörter und Sitzungstokens gehören zu den vertraulichen Instanzdaten; Symcon-Konfiguration und Backups entsprechend schützen.

## Verhalten

WoIst kann Gerätepositionen optional über einen eigenen Nominatim-Server in
Adressen auflösen und diese in Geräteliste und Popup anzeigen. Verbundene
iCloud-Geräteinstanzen können dieselbe Adresse als Variable **Adresse** aus
dem vorhandenen Cache übernehmen. Die Funktion
ist standardmäßig aus; Serveradresse, Sprache, Datenschutz und Cache sind in
der [WoIst-Anleitung](iCloud%20WoIst/README.md#optionale-adressauflösung-mit-nominatim)
beschrieben.

Das Konto aktualisiert die Geräteliste nach erfolgreicher Anmeldung standardmäßig alle 300 Sekunden. Das Öffnen von Formularen oder das Lesen von Gerätevariablen startet keine Apple-Anmeldung. Nach abgelaufener Sitzung meldet das Konto den erforderlichen Anmeldeschritt; es führt keine automatischen Passwort-Anmeldeschleifen aus.

Die Abfrage fordert keine aktive Neuortung an. Apple kann deshalb ältere oder keine Standortdaten liefern. Gerätevariablen zeigen die Datenverfügbarkeit und den Standortzeitpunkt gesondert an. Die Geräteinstanz kann über **Handy suchen (Ton abspielen)** ausdrücklich einen Suchton-Auftrag senden. Im Formular lässt sich außerdem der [Verloren-Modus nach Bestätigung aktivieren und deaktivieren](iCloud%20Device/README.md#verloren-modus). Datenupdates lösen diese Aktionen nicht aus. Ein Löschen des Geräts wird nicht angeboten.

[Änderungen](CHANGELOG.md)

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](LICENSE).
