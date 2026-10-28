# ADR 0012: Mehrere zugewiesene Konten in WoIst

- Status: Angenommen
- Datum: 27.10.2026

## Entscheidung

WoIst bleibt ein Modul. Die bisherige physische Verbindung zu einem Konto
bleibt erhalten und verwendet weiterhin den offiziellen Parent/Child-Datenfluss.
`AdditionalAccounts` ergänzt optional eine begrenzte Liste explizit zugewiesener
Kontoinstanzen. Deren Modul-GUID wird vor dem Zugriff geprüft. Da eine Instanz
nur einen physischen Parent besitzt, werden zusätzliche Konten ausschließlich
über ihre vorhandenen öffentlichen `ICAC_GetDevices`-/`ICAC_RefreshDevices`-APIs
angesprochen. Keine Zugangsdaten, Sessions, Rohtransporte, eigene Anmeldung oder
automatische Umverbindung. Der Cache wird alle fünf Sekunden gelesen; Apple
kontaktiert nur das Konto nach dessen Regeln oder einer ausdrücklichen
Aktualisierungsaktion. Es entstehen keine versteckten Relay-Instanzen.

Die fachliche Kontosammlung bleibt librarylokal. Vorhandene zentrale
ConfigurationForm-, DataFlow-, ParentConnection-, OpenFreeMap-, Theme-,
Responsive-, HTTP- und IPSView-Helper werden weiterverwendet. Die allgemeine
Karten-Sitzungsspeicherung wird zentral um optionale begrenzte `filter`- und
`scroll`-Felder erweitert. Dort wird keine iCloud-Logik eingeführt.

## Kompatibilität und Isolation

Der Default ist eine leere Zusatzliste. Bereits gespeicherte Einstellungen,
Variablen-IDs und Gerätekennungen des physischen Parents bleiben unverändert.
Zusätzliche Geräte erhalten einen stabilen Schlüssel aus Konto-ID und Hash der
ursprünglichen Gerätekennung. Namen sind keine technischen Identitäten.
Verfügbarkeit wird pro Konto/Gerät ausgewertet; ein Fehler löscht oder
deaktiviert keine gesunden Konten. Die Kontoliste im Browser enthält nur
Instanz-ID, Anzeigename, Verfügbarkeit und Cachezeitpunkt.

Der Kontofilter begrenzt Marker und Liste, nicht die Zugriffsberechtigung.
Die gesamte Karte ist für alle ausgewählten Daten freigegeben. Änderungen der
Kontozuordnung erneuern den IPSView-Token; gewöhnliche Datenupdates verändern
weder Token noch HTML-Variable. Zusätzliche Kontoänderungen beeinflussen den
bisherigen nativen Sitzungsschlüssel nicht. Der Filter darf die Kamera nicht
neu einpassen oder eine vorübergehend ausgeblendete Auswahl löschen.

## Nachweise und Verteilung

Ergänzung vom 28.10.2026: `ShowAllDevices` (Default true) und `SelectedDevices`
begrenzen die Geräteanzeige einer WoIst-Instanz. Gespeichert werden konto- und
gerätebezogene Hashschlüssel vor der Umformung der zusätzlichen Geräte-IDs.
Damit bleibt die Konfiguration beim Wechsel zwischen Parent- und Zusatzkonto
stabil. Native und IPSView-Payloads enthalten dieselbe eingeschränkte Liste;
der vorhandene Kontofilter verfeinert diese. Eine leere Auswahl bedeutet leer,
nicht alle. Gespeicherte unbekannte Schlüssel werden im Formular erhalten und
berechtigen nicht zum Lesen unzugewiesener Konten. Das Formular liest nur lokale
zugewiesene Kontocaches. Die Auswahl ist keine Zugriffssperre, ändert keinen
Kontotoken und beeinträchtigt die bestehende Adressversorgung von Geräteinstanzen
nicht. Sie verwendet die bestehenden zentralen Formular-/Debug-/Karten-Helper;
die iCloud-spezifische Zuordnung verbleibt in der Library.

Regressionen prüfen doppelte Quell-/Gerätekennungen, Cachezugriffe ohne Refresh,
Teilfehler, ungültige Quelltypen/-listen, Wiederherstellung, erhaltene Properties
und IPSView-Variablen-IDs sowie Tokenwechsel nur bei geänderter Kontozuordnung.
Die Browser-Vertragstests prüfen Auswahl, Kamera, Filter und Scrollposition
einschließlich iframe-Neuaufbau in beiden Ausgaben mit dem echten zentralen
Speicher-Helper. Dieser Test schlägt mit dem alten Helper-Paket absichtlich fehl.

Das vollständige aktuelle Helper-Paket wird in einer isolierten Umgebung mit
den vorhandenen Metadaten- und Bundle-Generatoren geprüft. Zuerst den zentralen
Helper freigeben und automatisch verteilen lassen; danach den regulären
Consumer-Stand pullen und die Moduländerung veröffentlichen. Lokale vorläufige
Helper-Kopien verbleiben nicht im Modul-Arbeitsbaum.
