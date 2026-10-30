# SymconiCloud

[![Symcon](https://img.shields.io/badge/Symcon-PHPModul-brown.svg)](https://www.symcon.de)
[![Symcon Version](https://img.shields.io/badge/Symcon%20Version-9.0%2B-blue.svg)](library.json)
[![License](https://img.shields.io/badge/License-PolyForm%20Noncommercial%201.0.0-brightgreen.svg)](LICENSE)
[![Check Style](https://github.com/Burki24/SymconiCloud/actions/workflows/style.yml/badge.svg?branch=dev)](https://github.com/Burki24/SymconiCloud/actions/workflows/style.yml?query=branch%3Adev)
[![Tests](https://github.com/Burki24/SymconiCloud/actions/workflows/tests.yml/badge.svg?branch=dev)](https://github.com/Burki24/SymconiCloud/actions/workflows/tests.yml?query=branch%3Adev)

SymconiCloud verbindet Apple-iCloud-Dienste mit IP-Symcon. Die Library bietet Apple-Anmeldung mit Zwei-Faktor-Bestätigung, einen Gerätekonfigurator und **iCloud Gerät** zur Anzeige der von Apple gelieferten Geräte-, Akku- und Standortdaten.

**[WoIst](iCloud%20WoIst/README.md)** zeigt die Gerätepositionen eines oder mehrerer ausgewählter iCloud-Konten gemeinsam auf einer Karte mit Kontofilter und optionaler Geräteauswahl in der Kachelvisualisierung oder als IPSView-WebContent. Beide Ausgaben besitzen einen Designer; IPSView kann das Kachel-Design übernehmen. Kalender und iCloud Drive sind nicht enthalten.

**[iCloud Anwesenheit](iCloud%20Presence/README.md)** wertet pro Instanz mehrere
ausgewählte Geräte in gemeinsamen, frei konfigurierbaren Kreiszonen aus. Gesamt-
und Geräteanwesenheit unterscheiden
**Unbekannt**, **Abwesend** und **Anwesend** bezogen auf eine Referenzzone.
Entfernung, Aufenthaltsdauer und GPS-Stabilisierung nutzen denselben Kontocache.
Ausstehende Zonenwechsel werden je Gerät und in der Gruppensumme sichtbar; eine
konfigurierte Bestätigungszeit benötigt eine weitere neuere Apple-Position.

**[iCloud Fotos](iCloud%20Photos/README.md)** zeigt ein Album oder Favoriten als Einzelbild oder Diashow in Kachel und IPSView. Ein gemeinsamer Kontocache mit konfigurierbaren Ressourcenprofilen lädt Vorschauen bedarfsgerecht.

**[iCloud Kontakte](iCloud%20Contacts/README.md)** stellt ein lesendes, durchsuchbares
Empfängerverzeichnis mit Telefonnummern und E-Mail-Adressen aus dem gemeinsamen
Kontocache bereit. Die vorhandene Sitzung und der Kontoschutz werden verwendet.
Ein Nachrichtenversand ist darin nicht enthalten.

## Welches Modul brauche ich?

| Modul | Aufgabe | Eigene Geräteinstanzen nötig? |
| --- | --- | --- |
| [iCloud Konto](iCloud%20Account/README.md) | Einmalige Anmeldung, gemeinsame Datenabfrage und Kontoschutz | Nein; Ausgangspunkt für alle anderen Module |
| [iCloud Geräte Konfigurator](iCloud%20Device%20Configurator/README.md) | Geräteinstanzen und Anwesenheitsgruppen anlegen | Nein |
| [iCloud Gerät](iCloud%20Device/README.md) | Akku-/Standortvariablen und Aktionen für genau ein Gerät | Dies ist die Geräteinstanz; je gewünschtem Gerät eine |
| [iCloud WoIst Karte](iCloud%20WoIst/README.md) | Gerätepositionen ausgewählter Konten auf einer Karte mit Konto- und Geräteauswahl, optional mit Adressen | Nein |
| [iCloud Anwesenheit](iCloud%20Presence/README.md) | Mehrere ausgewählte Geräte, gemeinsame Zonen und Gesamtanwesenheit | Nein |
| [iCloud Fotos](iCloud%20Photos/README.md) | Album oder Diashow in Kachel und IPSView | Nein |
| [iCloud Kontakte](iCloud%20Contacts/README.md) | Empfängerverzeichnis und bestätigte Übernahme in Benachrichtigungsmodule | Nein |

**Nur die benötigten Module anlegen.** Für eine Karte oder Anwesenheitsgruppe
muss nicht für jedes Handy zusätzlich eine Geräteinstanz erstellt werden.
Alle gewünschten Module werden mit demselben iCloud-Konto verbunden.
Diese Datenverbindung ist unabhängig davon, in welchem Ordner die Instanzen
im Symcon-Objektbaum liegen.

## Voraussetzungen

- IP-Symcon 9.0 mit PHP 8.5 und den PHP-Erweiterungen `curl` und `bcmath`.
- Apple-Account mit aktiviertem „Wo ist?“ und Zugriff auf ein vertrauenswürdiges Gerät für den sechsstelligen Bestätigungscode.
- Das reguläre Apple-Account-Passwort; anwendungsspezifische CalDAV-Passwörter funktionieren hier nicht.
- HTTPS-Verbindung zu Apple. Unterstützt wird der globale iCloud-Dienst; der chinesische iCloud-Dienst und Hardware-Sicherheitsschlüssel/SMS als alternative 2FA-Verfahren sind derzeit nicht implementiert.

Für die automatische Aufbereitung hochgeladener Gerätebilder der Anwesenheit
wird zusätzlich PHP GD verwendet; vorhandene EXIF-Unterstützung berücksichtigt
die Ausrichtung von JPEG-Bildern. Der Fototransport benötigt OpenSSL mit
AES-256-GCM. Diese optionalen Funktionen und ihre Grenzen sind in den jeweiligen
Modulanleitungen beschrieben.

Die Anbindung verwendet Apples Web-Endpunkte. Apple stellt dafür keine zugesicherte öffentliche WoIst-Entwicklerschnittstelle bereit; Änderungen bei Apple können erneute Anmeldung oder Anpassungen erfordern. Die Library befindet sich in der Testphase.

## Installation und Einrichtung

1. `https://github.com/Burki24/SymconiCloud` in der Modulverwaltung als Bibliothek hinzufügen. Die erste Entwicklung liegt auf `dev`.
2. **[iCloud Konto](iCloud%20Account/README.md)** anlegen, aktivieren, Zugangsdaten speichern und **Anmelden** wählen.
3. Den Code vom vertrauenswürdigen Apple-Gerät unter **Zwei-Faktor-Authentifizierung** eingeben und bestätigen. Nach erfolgreicher Anmeldung wird die Geräteliste abgerufen.
4. Bei Bedarf den **[iCloud Geräte Konfigurator](iCloud%20Device%20Configurator/README.md)** anlegen und mit diesem Konto verbinden.
5. Für einzelne Akku-/Standortvariablen und Geräteaktionen die gewünschten **[iCloud-Geräteinstanzen](iCloud%20Device/README.md)** über den Konfigurator anlegen.
6. Für die Kartenansicht **iCloud WoIst Karte** anlegen, mit dem Konto verbinden und in der Kachelvisualisierung hinzufügen. Einzelne Geräteinstanzen sind für die Karte nicht erforderlich.
7. Für Anwesenheit im Konfigurator unter **Anwesenheitsgruppen** eine Gruppe
   anlegen, Geräte auswählen, gemeinsame Zonen definieren und genau eine Referenzzone markieren. Karten- und
   Geräteinstanzen sind dafür nicht erforderlich.
8. Optional den [Fotozugriff](iCloud%20Photos/README.md) oder
   [Kontaktzugriff](iCloud%20Contacts/README.md) im Konto aktivieren und die
   gewünschte Foto- beziehungsweise Kontaktinstanz mit diesem Konto verbinden.

Ein Konto kann mehrere Geräte bedienen; mehrere Apple-Konten verwenden getrennte Kontoinstanzen. Das Konto speichert Sitzung und Gerätecache über Symcon-Neustarts hinweg. Passwörter und Sitzungstokens gehören zu den vertraulichen Instanzdaten; Symcon-Konfiguration und Backups entsprechend schützen.

## Verhalten

WoIst kann Gerätepositionen optional über einen eigenen Nominatim-Server oder
Geoapify in Adressen auflösen und diese in Geräteliste und Popup anzeigen. Verbundene
iCloud-Geräteinstanzen können dieselbe Adresse als Variable **Adresse** aus
dem vorhandenen Cache übernehmen. Die Funktion
ist standardmäßig aus; Serveradresse, Sprache, Datenschutz und Cache sind in
der [WoIst-Anleitung](iCloud%20WoIst/README.md#optionale-adressauflösung)
beschrieben.

Das Konto aktualisiert die Geräteliste nach erfolgreicher Anmeldung standardmäßig alle 300 Sekunden. Das Öffnen von Formularen oder das Lesen von Gerätevariablen startet keine Apple-Anmeldung. Nach abgelaufener Sitzung meldet das Konto den erforderlichen Anmeldeschritt; es führt keine automatischen Passwort-Anmeldeschleifen aus.

Optional verkürzt **Schnellere Standortupdates** im Konto den Geräteabruf auf
60 Sekunden und fordert höchstens einmal pro Minute eine frische Apple-Position
an. Kontoschutz und GPS-Wechselbestätigung bleiben erhalten; eine sofortige
Ortung ist nicht garantiert. Der Modus kann den Geräteakku stärker beanspruchen.

Die Abfrage fordert keine aktive Neuortung an. Apple kann deshalb ältere oder keine Standortdaten liefern. Gerätevariablen zeigen die Datenverfügbarkeit und den Standortzeitpunkt gesondert an. Die Geräteinstanz kann über **Handy suchen (Ton abspielen)** ausdrücklich einen Suchton-Auftrag senden. Im Formular lässt sich außerdem der [Verloren-Modus nach Bestätigung aktivieren und deaktivieren](iCloud%20Device/README.md#verloren-modus). Datenupdates lösen diese Aktionen nicht aus. Ein Löschen des Geräts wird nicht angeboten.

## Entwicklung und automatische Versionierung

Auf `dev` erzeugt die CI nach erfolgreichen Tests die Versionsnummer sowie
Build und Datum aus den gepushten Quellcommits. Der anschließende Bot-Commit
ändert ausschließlich `library.json` und löst erneut Tests und Stilprüfung aus.
Die Versionsfelder werden nicht von Hand gepflegt. **Vor weiteren lokalen
Änderungen den Bot-Commit pullen**, damit der Arbeitsstand aktuell bleibt.
Der Ablauf und die Einrichtung sind in
[Automatische Versionierung](docs/automatic-versioning.md) beschrieben.

Die Dokumentationsschaltflächen aller sieben Module führen während der
Beta-Vorbereitung auf die aktuellen Anleitungen im Entwicklungszweig `dev`.

[Änderungen](CHANGELOG.md)

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](LICENSE).
