# ADR 0008: Anwesenheitsgruppe mit gemeinsamen Zonen

Datum: 2026-10-23

Status: Beschlossen und lokal umgesetzt; offline und in einer temporären
nativen MCP-Testbibliothek geprüft. Reguläre Verteilung noch ausstehend.

## Entscheidung

Eine `iCloud Presence`-Instanz wertet mehrere ausgewählte Geräte eines Kontos
aus. Sie besitzt eine Geräte-Liste und eine gemeinsame Zonenliste mit genau
einer Referenzzone. Ein eigener Zonenanbieter ist für diesen Umfang nicht nötig.
Diese Entscheidung ersetzt die Ein-Gerät-pro-Instanz-Aufteilung aus ADR 0007.

Pro Gerät bleiben GPS-Zustand, Bestätigung eines Wechsels, Entfernung und
Aufenthaltsdauer getrennt. Darüber steht die Gesamtanwesenheit: mindestens ein
bestätigt anwesendes Gerät ergibt Anwesend; ausschließlich bestätigte Abwesenheit
aller ausgewählten Geräte ergibt Abwesend; sonst gilt Unbekannt. Eine leere
Auswahl ist unbekannt. Die allgemeine Aggregation liegt im bestehenden zentralen
`GeofenceHelper`; die kontospezifische Auswahl und Variablenverwaltung im Modul.

Das Konto wird einmal je Gruppenaktualisierung aus seinem Cache gelesen. Die
physische Verbindung zum bestehenden Kontosplitter bleibt bestehen. Andere
Apple-Konten werden durch eigene Gruppen ausgewertet. Es gibt keine zusätzliche
Anmeldung, HTTP-Verbindung, aktive Neuortung oder neue I/O-Instanz.

## Konfiguration und Zustände

`Devices` enthält bis zu 64 eindeutige `deviceID`-Einträge mit optionalem
Anzeigenamen `name`. Der Anzeigename und die Listenreihenfolge sind keine
Identitätsschlüssel. Variablen verwenden deterministische Geräte-Idents und
bleiben beim Umordnen und Umbenennen erhalten.

Der gemeinsame Zustandsfingerabdruck enthält Konto, normalisierte Zonen,
Referenzzone und GPS-Einstellungen. Änderungen daran verwerfen die Beobachtung.
Die Geräteauswahl gehört nicht zu diesem Fingerabdruck: Hinzufügen oder
Umordnen setzt die Aufenthalte unveränderter Geräte nicht zurück. Entfernte
Geräte werden aus den aktiven Zuständen und Summen entfernt; ihre Variablen
werden unbekannt, nicht verfügbar und ausgeblendet. Objekt-IDs und Archivdaten
bleiben für eine spätere erneute Auswahl erhalten.

## Übernahme des Einzelgeräts

`Migrate(string $JSONData): string` übernimmt eine bekannte alte `DeviceID` nur
dann als erste Listenzeile, wenn in der Persistenz noch kein `Devices` existiert.
Eine ausdrücklich leere neue Liste wird nicht aufgefüllt. Zonen, GPS-Einstellungen
und übrige Persistenz bleiben erhalten. Die alte Property bleibt als verborgener
Migrationshinweis registriert und steuert die neue Auswahl nicht mehr.

Beim ersten Anwenden werden die alten elf Gerätevariablen auf die Geräte-Idents
umbenannt; ihre Objekt-IDs, Werte und Archive bleiben zugeordnet. Neue Gruppenwerte
werden getrennt angelegt. Damit bleiben Verweise auf alte Objekt-IDs auf das
ursprüngliche Gerät bezogen. Alte feste Feld-Idents sind kein kompatibler
Gruppenvertrag und müssen in Skripten angepasst werden. Ein persistenter Marker
macht die Variablenmigration wiederholbar. Bei unverändertem bekanntem
Quellfingerabdruck wird auch der bisherige Aufenthalt übernommen.

Der Konfigurator bietet eine leere Anwesenheitsgruppe für das Konto und zeigt
vorhandene Gruppen anhand ihrer Kontoverbindung. Bestehende Gruppen werden
weder automatisch zusammengeführt noch gelöscht.

## Verteilung und Nachweis

Der erweiterte `GeofenceHelper` muss vor dem fachlichen Modulupdate regulär
verteilt werden. Das vorhandene Abonnement genügt. Testpakete werden in
isolierten Kopien erzeugt, ohne Änderungen an den normalen Consumer-Helpern.

Offline-Regressionen decken Einzelgeräte, Gruppen, Mischzustände, Zeitmessung,
Quelle, Auswahländerungen und Migration ab. Der MCP-Nachweis verwendet eine
temporäre Bibliothek mit eigener Modul-ID und eigenem Präfix, nativem Lebenszyklus,
Properties, Attributen, Variablen, Formular und generierten Funktionsaufrufen.
Nur Zeit und Kontocache sind synthetische Testgrenzen. Testinstanzen, Dateien
und Registrierung wurden entfernt; die installierte Library wurde nicht neu
geladen und vorhandene Instanzen blieben unverändert.
