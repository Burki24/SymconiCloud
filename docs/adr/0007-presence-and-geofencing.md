# ADR 0007: Eigenständige Anwesenheit und zentraler GeofenceHelper

Datum: 2026-10-23

Status: Umgesetzt und per MCP geprüft. Die Ein-Gerät-Aufteilung wird durch
[ADR 0008](0008-shared-zone-presence-groups.md) ersetzt.

## Kontext

Gerätevariablen beschreiben Apple-Daten, WoIst stellt sie auf einer Karte dar.
Anwesenheit benötigt eine eigene Konfiguration und einen zeitlichen Zustand:
Zonen, eine Referenzzone, Datenqualität und bestätigte Zonenwechsel.
Fehlende Ortung darf nicht mit Abwesenheit gleichgesetzt werden.

## Entscheidung

`iCloud Presence` (Alias **iCloud Anwesenheit**, Präfix **ICPR**) ist ein
Gerätemodul direkt unter dem vorhandenen Kontosplitter. Eine Instanz wertet ein
Gerät aus. Der bestehende Geräte-Konfigurator erhält eine getrennte Liste für
diesen Instanztyp. Es gibt keine weitere I/O-Instanz oder Apple-Anmeldung.

Die anbieterunabhängige Kreisgeometrie, Qualitätsfilter, Hysterese,
Bestätigungslogik und serialisierbare Zustandsauswertung liegen auf ausdrücklichen
Wunsch zentral in `Symcon_ModuleHelper/src/GeofenceHelper.php`. Der Helper hat
keine Symcon-, Apple-, Netzwerk- oder Timerabhängigkeit. Das Modul übernimmt nur
Kontozuordnung, Konfiguration, Speicherung, Übersetzung und Variablen.

Ein bestätigter Referenztreffer bedeutet Anwesend, eine andere bestätigte Zone
oder sicher außerhalb Abwesend. Unbrauchbare Daten ergeben Unbekannt. Innerhalb
des Unsicherheitsbereichs bleibt eine bekannte Zuordnung erhalten. Die erste
klare Position wird unmittelbar übernommen; nachfolgende Wechsel benötigen eine
weitere Position mit späterem Zeitstempel und abgelaufener Bestätigungszeit.

Der Zustand wird mit Konto, Geräte-ID, Zonen und Einstellungen verknüpft.
Neustarts allein setzen den Aufenthalt nicht zurück; Quell- und
Konfigurationswechsel sowie Beobachtungslücken tun dies. Nach Datenverlust darf
derselbe alte Cachetreffer keine Anwesenheit wiederherstellen. Entfernung ist
Luftlinie zum Referenzmittelpunkt; Aufenthaltsdauer beschreibt beobachtete Zeit.

## Folgen

Die neue Instanz braucht weder Geräteinstanz noch Karte oder Adressdienst.
Ein lokaler 15-Sekunden-Timer prüft Alter und Dauer durch lesenden Cachezugriff.
Er erzeugt keine neuen GPS-Messungen und bestätigt keine Wechsel durch bloßes
Wiederholen derselben Daten. Die Konto-Abfragefrequenz begrenzt weiterhin die
Reaktionszeit. Außerhalb oder bei Unbekannt sind Dauer und Beginn 0;
Verfügbarkeitsvariablen unterscheiden fehlende Werte von gültigen Nullwerten.

Der Helper wird ausschließlich über das vorhandene Abonnement verteilt. Lokale
Paketprüfungen erfolgen in isolierten Kopien; ein vorläufiger Vendor-Stand bleibt
nicht im normalen Arbeitsbaum zurück. Die neue Subscription muss vor dem
zentralen Verteilungslauf auf `SymconiCloud/dev` stehen. Vor dem fachlichen
Modulcommit muss das regulär erzeugte Paket integriert und gepullt sein.
