# Entwicklungsstand

## Lesbare Integer-Statusanzeigen: MCP-Nachweis und Korrektur (2026-10-23)

Der Benutzer meldete Rohwerte wie `-1` anstelle lesbarer Texte bei den neuen
Gerätestatusvariablen. Die MCP-Prüfung auf dem Windows-Testsystem bestätigte
die Ursache: Bei einer Integer-Variable mit nativer Wertanzeige verwirft Symcon
`OPTIONS`. Laut offiziellem SDK gilt dieser Parameter nur für Boolean und
String; Integer benötigen `INTERVALS_ACTIVE` und konstante Beschriftungen in
`INTERVALS`. Die bisherige synthetische Prüfung hatte lediglich die falsche
Optionsstruktur bestätigt und wird durch diesen dokumentbasierten Vertrag ersetzt.

Zusätzlich enthielt der Kontocache zunächst noch keine Zusatzfelder. Ein
lesender Abruf über die bestehende Sitzung bestätigte danach die normalisierten
Kennzeichnungen und die Übernahme von Nein-/Ja-Zuständen in Gerätevariablen.
Eine neue Anmeldung oder aktive Neuortung war nicht nötig.

Die Korrektur liegt zentral in `VariablePresentationHelper::IntegerOptionsPresentation`.
Die Geräteinstanz verwendet diese neue Funktion. Integer-Zustände erhalten
je Wert ein exaktes Intervall mit
`ConstantActive=true` und `ConstantValue=Caption`. Boolean-/String-Optionen
und die bisherige `OptionsPresentation`-API bleiben unverändert. Variablentyp,
Idents und gespeicherte Werte werden nicht geändert; eine zusätzliche
Modulimplementierung der Darstellung ist unnötig.

Eine temporäre, anschließend gelöschte Integer-Testvariable auf dem echten
Symcon-System bestätigte per MCP und `GetValueFormatted` ohne Warnungen:
`-1` → **Nicht verfügbar**, `0` → **Nein**, `1` → **Ja**. Ein nicht definierter
Wert `2` blieb unverändert als **2** sichtbar. Die native Intervallstruktur
akzeptiert für Farben `ColorValue`; der in der abgerufenen SDK-Tabelle genannte
Schlüssel `Color` wurde mit einer Warnung abgewiesen. Der Helper und die
Regressionen verwenden deshalb den tatsächlich bestätigten Vertrag.

Die neue Helper-Datei wird über das vorhandene Abonnement verteilt. Tests des
erzeugten Pakets erfolgen in einer isolierten Kopie; im normalen Modul-Arbeitsbaum
bleiben keine temporären Helper-Dateien oder Testmetadaten zurück, die einen
späteren Pull blockieren könnten. Zuerst die zentrale Korrektur freigeben und
verteilen lassen, dann den regulären Helper-Sync im Modul pullen und die
Geräteinstanzkonfiguration erneut übernehmen. Die echten Gerätevariablen
erhielten in dieser Prüfung keine dauerhaften benutzerdefinierten Darstellungen.

Die vollständigen Helper- und Modulsuiten unter PHP 8.5.10, Paket-/Manifestprüfung,
php-cs-fixer im Prüfmodus und der StylePHP-JSON-Check des Moduls bestanden.
Auch der bisherige Modulstand einschließlich seiner unveränderten Tests bestand
mit dem neuen Helper-Paket in der isolierten Umgebung: Der automatische Sync
kann somit vor der Umstellung des Moduls auf die neue API geprüft werden.

## Zusätzliche Geräteangaben: MCP-Befund und Umsetzung (2026-10-23)

Auf dem angeschlossenen Windows-Testsystem wurde per MCP eine lesende
Geräteabfrage über die vorhandene Kontositzung erfolgreich ausgeführt. Die
kurzzeitige Diagnose erfasste ausschließlich Feldnamen, Datentypen und deren
Häufigkeit, keine Feldwerte, Gerätenamen, IDs, Koordinaten oder Zugangsdaten.
Die acht Statusfelder `lowPowerMode`, `locationEnabled`, `locationCapable`,
`isLocating`, `lostModeEnabled`, `lostModeCapable`, `activationLocked` und
`fmlyShare` waren in der geprüften Antwort jeweils als Boolean vorhanden;
`rawDeviceModel` war ein String. Bei vorhandenen Positionen lieferte Apple
zusätzlich `isInaccurate` und `locationFinished` als Boolean. Dieser Befund ist
eine Momentaufnahme der angeschlossenen Geräte, keine allgemeine
Verfügbarkeitsgarantie für alle Apple-Geräte.

Die temporäre Diagnoseänderung an AppleClient wurde nach dem Abruf vollständig
zurückgesetzt; die identische ursprüngliche SHA-256-Prüfsumme und das Entfernen
der temporären Datei wurden per MCP bestätigt. Es erfolgten weder eine neue
Apple-Anmeldung noch Gerätebefehle oder eine aktive Neuortung.

Der lokale Implementierungsstand übernimmt diese Angaben über eine feste
Allowlist. Acht optionale Gerätekennzeichnungen liegen im Kontocache unter
`details`, die Standortkennzeichnungen unter `location.isInaccurate` und
`location.finished`, die technische Modellkennung unter `modelIdentifier`.
Die Geräteinstanz ergänzt zehn ausschließlich lesbare Integer-Statusvariablen
mit **-1 = Nicht verfügbar**, **0 = Nein**, **1 = Ja** sowie `ModelIdentifier`
als Text mit **Nicht verfügbar** bei fehlender Angabe. Die Darstellung verwendet
den vorhandenen zentralen `VariablePresentationHelper::OptionsPresentation`;
Helper-Dateien und Verteilungspaket werden nicht geändert.

Fehlende oder ungültige Angaben, Kontofehler, entfernte Geräte und Wechsel der
Gerätezuordnung lassen keine alten optionalen Werte stehen. Alte Kontocaches
bleiben kompatibel und liefern zunächst unbekannte Zusatzangaben. Bestehende
Variablen, Idents, Adressregeln und Abfrageintervalle bleiben erhalten. Es gibt
keine zusätzlichen Gerätetimer oder HTTP-Abfragen. Nach dem Update die
Geräteinstanzkonfiguration übernehmen; neue Apple-Felder erscheinen mit dem
nächsten erfolgreichen Kontoabruf.

Synthetische Regressionen prüfen den Weg vom Apple-Transport über die
Normalisierung und den Kontodatenfluss bis zu den Gerätevariablen, echte
Boolean-Werte gegenüber fehlenden/ungültigen Werten, alte Caches, Wiederaufnahme,
Instanzlebenszyklus, stabile Variablen-IDs und ausschließlich lesbare native
Darstellungen. Die vollständige Offline-Suite unter PHP 8.5.10 einschließlich
der neuen Verträge und der bestehenden Karten-/Adressprüfungen, die offizielle
StylePHP-JSON-Prüfung und php-cs-fixer im Prüfmodus liefen erfolgreich.
Die MCP-Prüfung belegt die gelieferten Quellfelder und die
Wiederherstellung der Diagnose; die neuen Gerätevariablen wurden damit noch
nicht auf dem laufenden System installiert oder praktisch abgenommen.

## Einheitliche kompakte Adressen (2026-10-23)

Der zentrale ReverseGeocodingHelper normalisiert beide Anbieter auf dieselben
strukturierten Felder und formatiert Straße/Hausnummer, PLZ/Ort und Land.
Freitext aus Nominatims `display_name` bzw. Geoapifys `formatted` wird nicht
mehr übernommen oder zerlegt. Bei Gebäuden/Straßen entfallen zusätzliche
Verwaltungsebenen. Gebietsangaben enthalten höchstens einen Ortsteil; bei
fehlendem Ort werden nur zulässige Dorf-/Gemeinde-/Gebietsangaben verwendet.
Leerwerte, doppelte Angaben und überzählige Leerzeichen werden bereinigt.

Die bestehenden Grenzen bleiben unverändert: Gebäude bis einschließlich 20 m,
Straße bis 100 m, Gebiet bis 1000 m, darüber oder bei unbekannter Genauigkeit
nur Ort bzw. gröbere Angaben. Die Modultexte für Ungefähr und unbekannte
Genauigkeit bleiben unverändert. Liste, Popup und Gerätevariable lesen die
einheitliche serverseitige Darstellung; eine lokale UI-Formatierung gibt es nicht.

Cache-Schlüssel verwenden `compact-v1` statt `detail-v1`. Frühere formatierte
Strings werden nicht als neue Treffer ausgegeben, sondern unter den bestehenden
Timer-, Cache- und Quotenregeln neu aufgelöst. Der Geoapify-Verbrauchszähler
bleibt bestehen, auch bei bereits ausgeschöpftem Budget. Das vorhandene
ReverseGeocodingHelper-Bundle und dessen Abhängigkeit bleiben der Verteilungsweg.

Gezielte synthetische Tests vergleichen beide Anbieter über alle Detailstufen,
Genauigkeitsgrenzen, fehlende/doppelte Bestandteile, ländliche Orte und die
Cacheumstellung. Die Modulprüfung vergleicht Kachel-/IPSView-Zustand und echte
Gerätevariablen beider Quellen. Es werden keine privaten Standortdaten oder
echten Anbieterabfragen für diese Prüfungen verwendet.

Die vollständigen Offline-Suiten liefen mit PHP 8.5.10 erfolgreich. Die lokale
Chromium-Prüfung mit synthetischen Daten bestätigt die kompakte Adresse in
Liste und Popup der Kachelausgabe sowie einer verschachtelten IPSView-HTTP-
Testeinbettung. Genauigkeits- und Theme-Wechsel erhalten Zoom, geografischen
Mittelpunkt, Geräteauswahl, Popup und Dokument. Echte Mausradaktionen bestätigen
Kartenzoom und Listenscrollen in beiden Richtungen; kompakte Größen bis
320 × 240 Pixel bleiben innerhalb ihrer Fläche. Der neue Formatierungsstand
wurde im Rahmen dieser lokalen Prüfungen vom Agenten nicht auf einem laufenden
Symcon-System oder in der nativen IPSView-App installiert oder abgenommen.

### Erfolgreiche Benutzer-Praxisabnahme (2026-10-23)

Nach der Aktualisierung hat der Benutzer die kompakte Adressdarstellung für
Nominatim und Geoapify erfolgreich geprüft und als passend bestätigt. Beide
Anbieter liefern die gewünschte einheitliche, kurze Darstellung ohne die zuvor
störende zusätzliche Verwaltungshierarchie.

Dies ist ein vom Benutzer bestätigter Praxisnachweis, ergänzend zu den oben
dokumentierten synthetischen Prüfungen. Die bestehenden Genauigkeitsregeln
bleiben unverändert. Für diesen Dokumentationseintrag wurde keine zusätzliche
Live-Prüfung durch den Agenten durchgeführt; private Adressen, Koordinaten,
Gerätekennungen und Zugangsdaten werden nicht übernommen.

## Geoapify als zweiter Adressanbieter (2026-10-22)

WoIst verwendet `AddressProvider=nominatim` als kompatible Vorgabe. Geoapify
wird ausdrücklich mit `AddressProvider=geoapify` ausgewählt und benötigt die
maskiert eingegebene Stringproperty `GeoapifyAPIKey`. Es gibt keinen automatischen
Anbieterwechsel. Der zentrale ReverseGeocodingHelper besitzt Validierung,
festen HTTPS-Endpunkt, begrenzten Transport, Detailfilterung, Cache und Budget;
WoIst steuert Auswahl, Übersetzung und serialisierten Hintergrundtimer.

`GeoapifyDailyLimit` erlaubt 0 bis 1.000.000 HTTP-Versuche je Instanz und UTC-Tag;
Vorgabe 2.500, 0 sperrt neue Anfragen. Das persistente Attribut
`GeoapifyDailyUsage` wird vor HTTP geschrieben und zählt auch Fehler.
Neustart, Schlüsselwechsel und Adresscacheleerung setzen den Zähler nicht zurück.
Andere Instanzen, Systeme und Anwendungen mit demselben Key müssen ihre Budgets
auf das gemeinsame Anbieter-Kontingent abstimmen. Cachetreffer sind budgetfrei.
Bei ausgeschöpftem Budget wartet ausstehende Arbeit auf den nächsten UTC-Tag.
Beschädigte Zähler sperren neue Abfragen konservativ; HTTP-Fehler nutzen die
bestehende fünfminütige Wartezeit. Die Karte bleibt währenddessen bedienbar.

Die bisherigen Detailstufen (Gebäude bis einschließlich 20 m, Straße bis 100 m,
Gebiet bis 1000 m, sonst Ort) gelten unverändert. Unbekannte Genauigkeit erlaubt
nur den Ort. API-Anfragen verwenden building/street/locality/city; gröbere
Antworten werden zusätzlich lokal gefiltert. Schlüssel-Fingerabdruck, Sprache,
Position und Detail trennen Caches. Karten-HTML, Zustände und Adresscaches
enthalten keine API-Keys. Die bestehende Gerätevariable liest denselben Cache.
Kachel und IPSView zeigen feste Geoapify-/OSM-Quellenlinks und bei Bedarf den
Tageslimit-Hinweis, ohne das Kartendokument zu ersetzen.

Tests verwenden synthetische Schlüssel, Geräte und Anbieterantworten. Es wurde
kein Konto angelegt, kein echter Geoapify-Aufruf ausgeführt und kein laufendes
Symcon-System verändert. Die Einbindung nutzt das vorhandene Helper-Abonnement
einschließlich PersistentJsonCacheHelper; neue Kartenressourcen sind nicht nötig.

Die gezielten Integrationsprüfungen belegen die Übernahme in die Gerätevariable,
Maskierung im Formular, Cache-/Budgettrennung, Fehlerzählung, UTC-Tageswechsel,
Wiederaufnahme-Timer, Limit 0 und Schutz vor Key-Ausgabe in HTML/JSON. Die lokale
Chromium-Prüfung mit 24 synthetischen Geräten und ersetzten Kartenstilen prüft
Kachelausgabe und verschachtelte IPSView-HTTP-Testeinbettung: Quellenlinks und
Tageslimit-Meldung, Adress-/Theme-Wechsel ohne Dokumentwechsel, Erhalt von
Mittelpunkt, Zoom, Auswahl und Popup sowie echte Mausradaktionen für Kartenzoom
und Listenscrollen in beiden Richtungen. Kleine Ansichten bis 320 × 240 Pixel
bleiben innerhalb ihrer Fläche; die Budgetmeldung ist dort begrenzt und scrollbar.
Die echte Symcon-Kachel bzw. native IPSView-App mit diesem neuen Stand sowie ein
echter Geoapify-Key sind noch nicht praktisch abgenommen.

## Genauigkeitsabhängige Adressdetails (2026-10-22)

Die zentrale Richtlinie `ReverseGeocodingPrecision` im ReverseGeocodingHelper
erlaubt Gebäude bis einschließlich 20 m, Straßen bis 100 m, Gebiete bis 1000 m
und darüber nur Ortsangaben. Fehlende, ungültige oder nicht positive Genauigkeit
wird ausdrücklich als unbekannt behandelt und erlaubt ebenfalls nur den Ort.
Diese Grenzen bestimmen die Anzeige und garantieren keine richtige Hausnummer.
WoIst verwendet die zentrale Detailstufe für Cachelesen und Hintergrundabfragen;
Nominatim erhält `zoom` und `layer=address`. Strukturierte Antworten werden
zusätzlich auf erlaubte Felder beschränkt. Die Cache-Schlüssel sind nach Detail
getrennt und versioniert; alte und zu präzise Treffer werden nicht übernommen.

Liste und Popup zeigen gröbere Treffer als farblich hervorgehobenen, übersetzten
Ungefähr-Hinweis. `ICWI_GetDeviceAddress` liefert denselben Hinweis als Präfix
für die bestehende Gerätevariable. Beim Wechsel der Genauigkeit wird der
Zustand neu aufgebaut; ein währenddessen eintreffendes Gebäude-Ergebnis kann
keine gröbere aktuelle Position bedienen. Die vorhandenen Timer, Sperren und
Benachrichtigungen bleiben erhalten. Zusätzliche Geräteabfragen gibt es nicht.

Gezielte Helper-, Modul- und JavaScript-Verträge prüfen Grenzwerte einschließlich
20/20,01 m, unbekannte Genauigkeit, Cachetrennung, veraltete Schlüssel,
Detailfilterung und Genauigkeitswechsel während einer laufenden Abfrage.
Die lokale Chromium-Prüfung verwendet 24 synthetische Geräte und ersetzte
Kartenstile. Native Kachelausgabe und verschachtelte IPSView-HTTP-Testeinbettung
behalten Mittelpunkt, Zoom, Auswahl, Popup und Dokument bei Adressupdates und
Theme-Wechseln; der IPSView-Pfad empfängt die Änderung über seinen HTTP-Poll.
Echte Mausradaktionen bestätigen Zoom und Listenscrollen in beiden Richtungen,
ohne gegenseitige Beeinflussung. Hinweise wurden in Hell/Dunkel visuell geprüft.
Die vollständigen Offline-Suiten liefen mit PHP 8.5.10 erfolgreich; Manifest,
Übersetzungen und Abhängigkeitsbundles wurden zusätzlich validiert.
Eine Prüfung des neuen Standes im echten IPSView-Client bzw. der nativen App
erfordert dessen Installation und bleibt ein gesonderter Praxisnachweis.

## Adresse als Gerätevariable (2026-10-22)

`iCloud Device` ergänzt die lesbare Stringvariable `Address` mit der zentralen
mehrzeiligen `TextPresentation`. Die bisherige Reihenfolge und Identität der
Gerätevariablen bleiben erhalten. `AddressSource=0` wählt genau eine WoIst-Karte
mit aktivierter Adressauflösung am selben physischen Konto; eine ausdrücklich
gesetzte Quelle wird auf Modultyp und Kontoverbindung geprüft. Bei mehreren
automatischen Kandidaten wird keine beliebige Karte gewählt. Das Formular
verwendet den dokumentierten `SelectInstance`-Vertrag mit `validModules`.

Die neue cachelesende Funktion `ICWI_GetDeviceAddress` liefert eine Adresse nur
bei authentifiziertem Inventar, passender Geräte-ID und identischen aktuellen
Koordinaten. WoIst aktualisiert nach seiner Veröffentlichung die verbundenen
Geräteinstanzen über deren bestehendes `ICID_Update`; der Rücklesepfad erzeugt
keine weitere Veröffentlichung. Kein neuer Abfragetimer, HTTP-Client oder
Adresscache wurde eingeführt. HTTP und Cache bleiben in den vorhandenen
zentralen `ReverseGeocodingHelper`-/`PersistentJsonCacheHelper`-Bausteinen;
Auswahl und Zuordnung von iCloud-Modulinstanzen bleiben modulspezifisch.

Fehlende Position, Konto-/Inventarfehler, Identitätswechsel und nicht passende
Quellen leeren die Adresse. Neue Koordinaten übernehmen keine Adresse von der
vorherigen Position. Gelöschte oder umverbundene Quellen werden beim nächsten
Geräteupdate erneut bewertet. Eine ungültige optionale Quelle schaltet die
übrigen Gerätedaten nicht ab. Native App-/Kachelbedienung und das Verhalten
der Karte bei Adressankunft verwenden unverändert die bestehenden Pfade.

Die gezielte Integration unter `tests/device-address.php` verwendet die echten
Modulinstanzen und zentralen Caches mit synthetischem Nominatim-Transport. Sie
prüft automatische Übernahme nach Hintergrundauflösung, bereits gefüllte
Caches, native Stringdarstellung ohne Action, Quellenmehrdeutigkeit, explizite
Auswahl, Kontoisolation, Positions-/Identitätswechsel, Ausfälle und gelöschte
Quellen ohne zusätzlichen Apple-/Nominatim-Abruf. Der gemeinsame CI-Einstieg
führt diese Prüfung einschließlich der bisherigen Geocoding-Verträge aus.
Nachweise unter PHP 8.5.10: vollständige Offline-Suite einschließlich PHP-/JS-
Kartenverträgen, SDK-/Struktur-/Hash-/LF-Prüfung, offizielle StylePHP-JSON-Prüfung
und php-cs-fixer mit den offiziellen Regeln im Prüfmodus erfolgreich.
Die Live-Abnahme der neuen Gerätevariable nach Benutzerupdate bleibt offen;
der vorherige Pi-Nachweis betrifft die bereits bestätigte Adressanzeige in WoIst.

## Optionale Nominatim-Adressauflösung (2026-10-22)

- WoIst besitzt eine standardmäßig deaktivierte Adressauflösung mit eigener
  HTTP(S)-Serverbasis und Sprache. Der konfigurierte Dienst muss bereits laufen;
  der Nominatim-Fork enthält nur den Servercode. Die Implementierungsprüfungen
  erfolgten ohne Serverinstallation oder Abfrage realer Gerätepositionen;
  der anschließende Installations- und Praxisnachweis ist unten getrennt erfasst.
- `ReverseGeocodingHelper` liegt kanonisch in Symcon_ModuleHelper und nutzt den
  bestehenden `PersistentJsonCacheHelper`. Beide Dateien sind unverändert
  eingebunden, manifestiert und über die neue Subscription automatisch verteilbar.
- Formulare, native HTML-Ausgabe und authentifizierte Zustandsabrufe lesen nur
  den Cache. Der separate Symcon-Timer verarbeitet maximal eine offene Position
  pro Aufruf; er stoppt bei vollständig gefülltem Cache, ungültiger Konfiguration,
  fehlender Anmeldung oder Serverwartezeit. Aktualisierungen nach Ablauf einer
  Wartezeit/TTL starten offene Auflösungen erneut.
- Offline-Nachweise: echte Modul-/Konto-Datenflussintegration, Cachepersistenz,
  Positionswechsel einschließlich Änderung während HTTP, identische Positionen,
  Providerwechsel, Deaktivierung während HTTP, gesperrter öffentlicher Endpunkt,
  unbekannte Positionen, TTL, Fehlerwartezeit und Cachebegrenzung. Der reale
  HTTP-Client wurde gegen einen lokalen synthetischen Dienst geprüft, einschließlich
  User-Agent, fehlender Credentials/Cookies, Redirect-Sperre und Antwortlimit.
- Lokale Browserprobe: gerenderte Kachel und verschachtelte IPSView-HTTP-Ausgabe
  mit 24 synthetischen Geräten und lokal ersetztem Kartenstil. Adressankunft
  aktualisiert Liste und offenes Popup bei unverändertem Zoom, Mittelpunkt und
  gewähltem Gerät. Echte Mausradaktionen zoomen in beide Richtungen; Listen-Scroll
  wurde jeweils mit 440 → 220 Pixeln gemessen, ohne gleichzeitigen Kartenzoom.
  Schmale Ausgabe bei 430 Pixeln ohne horizontalen Überstand, keine JS-Fehler.
- PHP 8.5.10: Modul- und Helper-Suiten, StylePHP/php-cs-fixer, Manifest-, Bundle-
  und isolierte Verteilungsprüfungen erfolgreich. Die Adressanzeige mit eigenem
  Nominatim-Server wurde anschließend vom Benutzer bestätigt (siehe unten).
  Eine getrennte vollständige Live-Abnahme der Adressfunktion in tatsächlicher
  Kachelvisualisierung und nativer IPSView-App bleibt offen; die lokale
  Testeinbettung ersetzt diesen Nachweis nicht.

## Zurückgestellt: regelmäßige Nominatim-Datenupdates (2026-10-22)

Auf Benutzerwunsch wird die Einrichtung regelmäßiger OSM-Datenupdates auf einen
späteren Arbeitsschritt verschoben. Aktuell hat die Weiterentwicklung des Moduls
mit Geoapify als zweitem Weg zur Adressauflösung Vorrang. Es wurde kein
OSM-Update-Timer eingerichtet oder aktiviert.

Der vorhandene Import mit `--no-updates` ist nicht für Replikationsupdates
geeignet. Als spätere Umsetzung ist ein einmaliger Import in eine separate
Datenbank ohne diese Option vorgeschlagen. Nach Prüfung und kontrollierter
Umschaltung könnten regionale Geofabrik-Änderungen für Brandenburg einschließlich
Berlin regelmäßig in kleinen Paketen übernommen werden. Die bisherige Datenbank
soll während des Neuaufbaus verfügbar bleiben und zunächst als Rückfall dienen.

Vor Umsetzung Speicherbedarf und freie Kapazität für beide Datenbanken prüfen.
Die bestehenden CPU-, RAM-, Swap- und I/O-Limits müssen Symcon weiter Vorrang
geben. Update-Läufe serialisieren, Datenrückstand und Fehler überwachen und
die erste Aktualisierung unter Beobachtung durchführen. Der bisherige
Freiplatz-Guard darf nicht unverändert verwendet werden, da er den gemeinsamen
Nominatim-PostgreSQL-Cluster stoppt; die neue Überwachung soll gezielt den
Schreibauftrag begrenzen. Auf der gemeinsamen microSD ist trotz Limits keine
garantiert unveränderte Symcon-Latenz zugesagt. Zeitplan, Grenzwerte und ein
möglicher SSD-Einsatz werden erst bei Wiederaufnahme festgelegt.

## Nominatim auf Raspberry Pi: Installation und Praxisnachweis am 22.10.2026

Der Benutzer hat Nominatim ohne Docker auf demselben Raspberry Pi eingerichtet,
auf dem bereits Symcon läuft. Die folgenden Ergebnisse beruhen auf seinen
Terminalausgaben und der abschließenden Bestätigung samt Screenshot der
Adressanzeige. Der Agent hat die Installation angeleitet, aber keine eigene
SSH-/MCP-Prüfung oder Änderung am laufenden System vorgenommen. Private Hostnamen,
Netzadressen, Gerätekennungen, Adressen, Koordinaten und Screenshots werden
nicht in diesem Nachweis gespeichert. Die Loopback-URL unten ist generisch.

### Plattform und Dienstaufteilung

- Raspberry Pi 5 Model B, 4 GiB RAM, Debian 12 Bookworm, aarch64/64 Bit;
  Root-Dateisystem auf einer microSD mit rund 59 GiB und rund 8,2 GiB Systemswap.
- Nominatim 5.3.3 aus PyPI in `/srv/nominatim/venv`, mit
  `--system-site-packages` für das über Debian bereitgestellte PyICU.
  `pip check` meldete keine defekten Abhängigkeiten.
- PostgreSQL 15.19, PostGIS 3.3 und osm2pgsql 1.8.0. Eigener Cluster
  `15/nominatim` auf Port 5433; `listen_addresses = ''`, Verbindung nur über
  den lokalen Unix-Socket. Der bei der Paketinstallation neu angelegte Cluster
  `15/main` wurde gestoppt und auf manuellen Start gesetzt; das ist keine
  allgemeine Anweisung, vorhandene PostgreSQL-Cluster anzuhalten.
- Dienstbenutzer `nominatim`, Projektverzeichnis `/srv/nominatim/project` und
  Downloadverzeichnis `/srv/nominatim/data`. Die gleichnamige Datenbankrolle
  besitzt die für den Import benötigten Superuserrechte ausschließlich im
  getrennten Cluster. Die API läuft als `www-data` mit der vom Import
  eingerichteten lesenden Datenbankrolle und Peer-Authentifizierung.
- Projektkonfiguration: `NOMINATIM_DATABASE_DSN` verwendet die Datenbank
  `nominatim`, den Socket `/var/run/postgresql` und Port 5433;
  `NOMINATIM_DATABASE_WEBUSER=www-data`, `NOMINATIM_IMPORT_STYLE=address`,
  `NOMINATIM_DEFAULT_LANGUAGE=de` und `NOMINATIM_API_POOL_SIZE=2`.

### Ressourcenlimits zum Schutz von Symcon

Der cgroup-v2-Speichercontroller wurde über `cgroup_enable=memory` in der
tatsächlich verwendeten `/boot/firmware/cmdline.txt` aktiviert und nach einem
Neustart durch `memory` in `cgroup.controllers` bestätigt; Symcon war aktiv.
Die Limits stehen in `/etc/systemd/system/nominatim.slice`. PostgreSQL,
Importdienst und API werden gemeinsam dieser Slice zugeordnet, sodass die
Grenzen für deren gesamten Verbrauch gelten.

| Einstellung | Konfigurierter Wert / Bedeutung |
| --- | --- |
| `CPUQuota` | `100%`: insgesamt höchstens die Rechenzeit eines CPU-Kerns |
| `CPUWeight` | `10` |
| `MemoryHigh` | `2200M` |
| `MemoryMax` | `2600M` = 2.726.297.600 Byte |
| `MemorySwapMax` | `512M` = 536.870.912 Byte, nur für diese Slice |
| `IOWeight` | `10` |
| `IOReadBandwidthMax` | `/dev/mmcblk0 8M`, effektiv 8.000.000 Byte/s |
| `IOWriteBandwidthMax` | `/dev/mmcblk0 2M`, effektiv 2.000.000 Byte/s |
| Accounting | `CPUAccounting`, `MemoryAccounting`, `IOAccounting` aktiviert |
| Dienstpriorität | `Nice=15`, `IOSchedulingClass=idle` für PostgreSQL, Import und API |

`Slice=nominatim.slice` ist für PostgreSQL im Drop-in
`/etc/systemd/system/postgresql@15-nominatim.service.d/resources.conf`
hinterlegt. Die wirksamen cgroup-Werte für RAM, Swap, CPU und Datenträger wurden
vor dem Import ausgelesen und bestätigt. Die Grenzen reduzieren die Belastung;
sie garantieren wegen des gemeinsamen Datenträgers keine störungsfreie
Symcon-Latenz.

Die separate PostgreSQL-Konfiguration begrenzt zusätzlich `shared_buffers` auf
128 MB, `effective_cache_size` auf 512 MB (Planerannahme, keine RAM-Reservierung),
`work_mem` auf 4 MB, `maintenance_work_mem` auf 64 MB und `autovacuum_work_mem`
auf 32 MB. `max_connections=20`, `autovacuum_max_workers=1`,
`max_parallel_workers_per_gather=0`, `max_parallel_maintenance_workers=0` und
`max_wal_size=512MB`. Autovacuum und die PostgreSQL-Dauerhaftigkeit wurden
nicht abgeschaltet; der globale Symcon-Betrieb erhielt keine neuen Limits.

### Import und Freiplatzschutz

- Importquelle: Geofabrik `brandenburg-latest.osm.pbf`, einschließlich Berlin.
  Die Datei-Prüfsumme wurde vor dem Import erfolgreich geprüft.
- `nominatim-import.service` lief als `nominatim` im Projektverzeichnis mit
  `OMP_NUM_THREADS=1` und folgendem Aufruf:

  ```text
  nominatim import --osm-file /srv/nominatim/data/brandenburg-latest.osm.pbf --threads 1 --osm2pgsql-cache 128 --reverse-only --no-updates
  ```

- `ExecStartPre` prüft über `/usr/local/sbin/nominatim-import-guard preflight`
  die Prüfsumme und mindestens 20 GiB freien Platz. Der Import benötigt über
  `Requires`/`After` PostgreSQL und `nominatim-disk-watch.timer`; die Überwachung
  muss deshalb nicht separat von Hand gestartet werden.
- Der Timer startet nach einer Sekunde und prüft ungefähr alle fünf Sekunden.
  Unter 8 GiB freiem Platz würde der Guard Import und ausschließlich dessen
  PostgreSQL-Cluster stoppen. Dieser Abbruchpfad wurde im erfolgreichen Lauf
  nicht ausgelöst und nicht gesondert praktisch getestet.
- Importbeginn 11:41:10, erfolgreiche Abschlussmeldung 18:00:19;
  gemeldete Dauer 22.748,94 Sekunden, rund 6 Stunden 19 Minuten.
  Rank 30 endete um 17:42:36; Interpolationen, ein separater Rank-0-Nachlauf
  und Abschlussarbeiten folgten. Eine Rank-ETA ist keine Gesamtimport-ETA.
- Fehlende optionale Wikipedia-/Wikidata-Zusatzdateien führten zum vorgesehenen
  Standard-Ranking, nicht zu einem Importfehler. Der Importdienst wurde von
  systemd erfolgreich deaktiviert; die Freiplatzüberwachung danach gestoppt.
- `--no-updates` entfernt Strukturen für laufende OSM-Datenupdates. Der
  Datenbestand wird in diesem Setup nicht per Replikation aktualisiert;
  eine spätere Aktualisierung oder Gebietserweiterung benötigt einen geplanten
  Neuimport. Apple-Standortaktualisierungen bleiben davon unabhängig.

### HTTP-Dienst und SymconiCloud-Anbindung

`nominatim-api.service` verwendet die vorhandene virtuelle Umgebung mit Falcon
und Gunicorn (Installationsvorgabe 25.1.0), `--worker-class asgi`, einen Worker,
16 Worker-Verbindungen und `--timeout 120`. Arbeitsverzeichnis ist das
Nominatim-Projekt, Dienstbenutzer `www-data`, `PrivateTmp=yes` und
`PYTHONDONTWRITEBYTECODE=1`. Der Dienst benötigt den getrennten
PostgreSQL-Cluster und verwendet dieselben Slice-/Prioritätsgrenzen.
`Restart=on-failure` mit zehn Sekunden Abstand ist konfiguriert; der Dienst
wurde für automatischen Start über `multi-user.target` eingerichtet.

Gunicorn stellte HTTP zunächst ausschließlich auf `127.0.0.1:8088` bereit.
Für das auf demselben Pi laufende Symcon genügt dieser lokale Zugang; für das
getrennte Testsystem wurde anschließend eine zusätzliche LAN-Bindung ergänzt
(siehe unten). nginx oder Änderungen an Symcons Webports sind dafür nicht
erforderlich. Die Prüfpunkte sind `/status` und `/reverse` mit `format=jsonv2`,
`addressdetails=1` und `accept-language=de`. Der Benutzer bestätigte die
funktionierende Adressanzeige in WoIst.

In **WoIst → Adressauflösung (Nominatim)** ist
`EnableAddressResolution=true`, `NominatimServer=http://127.0.0.1:8088` und
`AddressLanguage=de` vorgesehen; danach Konfiguration übernehmen. `/reverse`
wird nicht in die Basis-URL eingetragen, sondern vom zentralen Helper ergänzt.
Loopback funktioniert, weil die Auflösung serverseitig auf demselben Pi läuft;
bei einem anderen Nominatim-Rechner wäre dessen erreichbare Basis-URL nötig.

Der vorhandene `ReverseGeocodingHelper` mit `PersistentJsonCacheHelper` bleibt
die zentrale Implementierung für HTTP und Cache. `WoIstGeocoding` steuert
lediglich Opt-in, Hintergrundtimer und Veröffentlichung der Ergebnisse. Der
Cache- und Aktualisierungsvertrag steht in
[`iCloud WoIst/README.md`](iCloud%20WoIst/README.md#optionale-adressauflösung-mit-nominatim).
Nominatim löst Adressen auf; die Kartendarstellung bleibt bei OpenFreeMap.

### Zusätzliche LAN-Bindung und Testsystem (2026-10-22)

Das getrennte Symcon-Testsystem verwendet denselben Nominatim-Dienst auf dem
Pi. Die anfängliche Diagnose zeigte nur einen Listener auf `127.0.0.1:8088`:
Der lokale `/status`-Abruf lieferte HTTP 200 mit `OK`, der Abruf über die
LAN-Adresse scheiterte beim Verbindungsaufbau.

In der bestehenden `ExecStart`-Zeile von
`/etc/systemd/system/nominatim-api.service` wurde die Bindung erweitert:

```text
--bind 127.0.0.1:8088 --bind <Pi-LAN-IP>:8088
```

`<Pi-LAN-IP>` bezeichnet hier bewusst einen Platzhalter. Der lokale Listener
bleibt bestehen; nur die zusätzliche Pi-LAN-Adresse wird gebunden. Die übrigen
Startparameter und Ressourcenlimits bleiben unverändert. Nach Sicherung der
Dienstdatei wurden `systemctl daemon-reload` und der Neustart ausschließlich
von `nominatim-api.service` vorgesehen; Symcon und PostgreSQL benötigen dafür
keinen Neustart. Der Datenbankzugang bleibt lokal über den Unix-Socket.

Die WoIst-Konfiguration unterscheidet die beiden Systeme:

| System | Nominatim-Basis-URL |
| --- | --- |
| Symcon auf dem Pi | `http://127.0.0.1:8088` |
| Getrenntes Symcon-Testsystem | `http://<Pi-LAN-IP>:8088` |

Adressauflösung aktivieren und die Konfiguration übernehmen; `/reverse` wird
weiterhin vom zentralen Helper ergänzt. Die Anfragen erfolgen serverseitig
vom jeweiligen Symcon-System, nicht vom Browser oder IPSView-Client. Beide
Systeme verwenden ihre vorhandenen instanzlokalen Helper-Caches; eine zweite
Nominatim-Installation oder ein weiterer OSM-Import ist nicht erforderlich.

Der Benutzer bestätigte nach der Bindungsänderung den erfolgreichen Zugriff
auch vom Testsystem. Dies ist ein Benutzer-Praxisnachweis; ein zusätzlicher
unabhängiger MCP-Test wurde für die LAN-Erweiterung nicht durchgeführt.
Reale interne Adressen und Hostnamen werden nicht im Repository festgehalten.

### Nachweise und verbleibende Grenzen

- `nominatim admin --check-database`: Verbindung, Versionsabgleich, Inhalt,
  Tokenizer, Indexierungsstatus sowie vollständige und gültige Indizes jeweils
  `OK`; optionale externe Daten erwartungsgemäß `not applicable`.
- `nominatim reverse` für einen öffentlichen Referenzpunkt in Berlin lieferte
  eine passende Adresse. Der Benutzer bestätigte nach HTTP-Einrichtung und
  Modulkonfiguration zusätzlich die Adresse in der realen WoIst-Geräteliste.
- Abschlussmessung: `pg_database_size` meldete `1474 MB` (rund 1,44 GiB),
  Root-Dateisystem rund 25 GiB frei, RAM rund 2,9 GiB verfügbar, Systemswap
  rund 50 MiB belegt und `systemctl is-active symcon` meldete `active`.
- Kein unabhängiger neuer MCP-Nachweis für diese Installation. Autostart nach
  Neustart, Ausfall-/Wiederanlauf der API, Freiplatz-Abbruch, spätere Neuimporte
  sowie die neue Adressanzeige im Popup und getrennt in beiden echten
  Visualisierungsclients wurden in diesem Lauf nicht zusätzlich abgenommen.
  Die bisherigen Offline-Nachweise für Kamera-/Auswahlerhalt gelten weiterhin;
  der Screenshot allein bestätigt diese Interaktionen nicht erneut.

Zielplattform: Symcon 9.0 / PHP 8.5. Entwicklungsbranch: dev. Lizenz: PolyForm Noncommercial License 1.0.0. Version 0.1/build 0/date 0 bezeichnet ausschließlich den unveröffentlichten Ausgangsstand, keinen Release.

## Prüfung

- `php tests/run.php`: Protokoll-, Konto- und Geräte-/Konfiguratorverhalten sowie Struktur und JSON.
- `php tests/validate_structure.php`: Library, offizielle Modultypen, GUID-Datenfluss, Module, Helper-Integrität, LF und CI.
- `php tests/sdk-contracts.php`: dokumentbasierte Formularaktionen (Callback, Parameter, Scope) und Configurator-Erstellungsvorgaben (bekanntes Modul, Konfigurationsobjekt, Property-Typen und tatsächliche Geräteanlage), einschließlich Negativfällen. Der Test ist in `tests/run.php` und damit im gemeinsamen CI-Testlauf enthalten. Unterstützt werden die einfachen Funktionsaufrufe, die dieses Projekt als Button-Skripte verwendet; dies ist kein allgemeiner PHP-/Symcon-Konsolenvalidator.
- `php tests/map.php`, `node tests/map-ui.js` und `node tests/map-transport.js` und `node tests/map-loader.js`: WoIst-Backend-/Frontend-Verträge einschließlich SDK-Zustandsnachrichten, Cache-Isolation, Aktualität, leeren/ungültigen Positionen, Providerwechsel und sicherer Textdarstellung. Node 22 wird für den CI-Testlauf eingerichtet.
- `php .style/json-check.php`: offizielle StylePHP-JSON-Prüfung.
- `php-cs-fixer fix --config=.style/.php-cs-fixer.php --allow-risky=yes --dry-run --diff`: offizielle StylePHP-Regeln im Prüfmodus.
- Gemeinsame CI: Burki24/Symcon_ModuleCI v1.0.0 mit PHP 8.5 sowie curl, bcmath, dom.

`.style` und `tests/stubs` sind offizielle Git-Submodule. `libs/helper` ist ein unverändertes, per SHA-256 manifestiertes Teilbundle der zentralen Helper; `.helper-sync.json` beschreibt die gewünschten Quellen. Die Registrierung des neuen Consumers im zentralen Repository ist ein eigener Auftrag; es wurde dort nichts verändert. Git erhält die Helper-Bytes unverändert, während projektspezifische Dateien UTF-8/LF verwenden.

## Offene Praxisabnahme

Lokale Prüfung am 21.10.2026 mit PHP 8.5.10: Gesamtsuite einschließlich Syntaxprüfung, Strukturprüfung, offizielle StylePHP-JSON-Prüfung und php-cs-fixer 3.95.27 mit offiziellen Regeln im Prüfmodus erfolgreich. php-cs-fixer gibt einen unverbindlichen Hinweis auf die fehlende optionale composer.json aus; die Prüfung selbst endet erfolgreich. Die GitHub-CI wurde noch nicht ausgeführt.

Vom Benutzer am 21.10.2026 praktisch bestätigt: Apple-Anmeldung, Konfigurator, Geräteanlage und Anzeige der Gerätevariablen. Screenshots und private Geräte-/Standortdaten werden nicht versioniert. Noch offen sind unter anderem Cookie-Vertrauen nach echtem Serverneustart, Mehrkonten-Anlage und vollständige Darstellung in schmaler/breiter Konsole. Der Entwicklungsstand ist kein als produktionsbereit geprüfter Release.

Eine spätere Praxisabnahme sollte einen autorisierten Testaccount verwenden und keine Passwörter, Codes, Cookies, Gerätedaten oder Standorte im Repository speichern. Änderungen an laufenden Symcon-Systemen erfolgen nur separat beauftragt.

WoIst wurde mit synthetischen Daten und lokal ersetzten Kartenkacheln in Chromium auf Desktop- und Mobilbreite geprüft. Dabei wurden keine Apple-Konten und keine öffentlichen Kartenserver angesprochen. HTML-SDK-Betrieb und Kartenzugriff in der realen Symcon-Visualisierung sind noch praktisch abzunehmen. Details und die Recherche zu iCloud-/MapKit-Möglichkeiten stehen in ADR 0003.

## WoIst-Ausgaben und Designer

Tile und IPSView verwenden dieselben lokalen Leaflet-/Modulassets und den zentralen `IPSViewHTMLPageHelper` einschließlich `HelperTranslationHelper`. Die gemeinsame HTML-Variablenverwaltung und sichere Seitenerzeugung werden unverändert aus dem kanonischen Helper-Bundle eingebunden. Map-spezifische Einstellungen und die Zuordnung der Standortfarben bleiben in `libs/WoIstDesign.php`; ein neuer allgemeiner Designer-Helper ist dafür nicht erforderlich. ECharts-spezifische Topic-Gruppierung und Gateway-/WebSocket-Transport werden nicht übernommen.

IPSView nutzt eine optionale, vom Helper verwaltete WebContent-Stringvariable `IPSViewWoIst` als stabiles HTML-Dokument. Daten-Broadcasts und Update-/Refresh-Aktionen schreiben ausschließlich einen bereinigten IPSView-Zustandscache; sie verändern die HTML-Variable nicht. Die geöffnete Seite fragt alle fünf Sekunden einen instanzspezifischen, token-geschützten POST-WebHook ab. Es erfolgen weder Kontoabfragen noch Apple-Anfragen im WebHook. Der bestehende `HttpResponseHelper` liefert HTTP-Antworten mit No-Store und Nosniff; ein neuer allgemeiner Helper wurde nicht eingeführt.

Der Zugriffstoken wird nur in der IPSView-Einbindung eingebettet, per Fragment an die echte HTTP(S)-Kartenseite übergeben und vor deren Netzwerkzugriffen aus der Seitenadresse entfernt. Die öffentlich gelieferte Kartenseite enthält keine Tokens, Geräte, Koordinaten oder privaten Anbieterparameter; erst `X-WoIst-Token` autorisiert den Zustandsabruf. Native Tile-Nachrichten, `GetState()` und HTTP-Zustandsantworten enthalten ihn nicht. Deaktivierung und Kontowechsel widerrufen den bisherigen Zugang. CORS ohne Cookies erlaubt auch opaque WebViews; solche Seiten brauchen eine explizite `IPSViewServerURL`, da weder Referrer noch fremde Elternfenster als Token-Ziel herangezogen werden. Die HTML-Variable enthält einen lesenden Bearer-Zugang und ist deshalb vertraulich.

Die Seite hält die Leaflet-Karte und vorhandene Marker am Leben. Aktualisierungen verändern Position, Stil und Popup-Inhalt ohne automatisches Verschieben oder Zoomen. Auswahl bleibt bei gelisteten Geräten auch ohne Position erhalten; ein entferntes Gerät verliert seine Auswahl. Verbindungsfehler zeigen einen Hinweis, behalten Daten und Ansicht und lösen begrenzte Wiederholungen aus. Details stehen in ADR 0005. Änderungen der Konfiguration und ausdrücklich angeforderte HTML-Regeneration dürfen weiterhin die Seite ersetzen.

Formular-Verträge prüfen jetzt die tatsächlich erzeugten Formulare einschließlich Designer-`onChange` und Helper-`MESSAGE:`-Rückgaben. Die Kopieraktion ist ausdrücklich ein Snapshot des gespeicherten Kachel-Designs; sie übernimmt keine ungespeicherten Formulareingaben. Ein realer IPSView-Laufzeittest bleibt offen; die Browserprüfung verwendet synthetische HTML-Ausgaben und lokal ersetzte Kartenkacheln.

Der zentrale HTML-Renderer wurde im selben Arbeitspaket in `Symcon_ModuleHelper` gegen rekursive Platzhalterersetzung korrigiert und mit Tests abgesichert. Das lokale Bundle enthält die byteidentische, noch nicht commitete Quelle; `source_sha` bezeichnet weiterhin den bisherigen Helper-HEAD, der SHA-256-Wert die tatsächlich gelieferten Bytes. Nach dem Benutzer-Commit kann der reguläre Helper-Sync die Provenienz auf den neuen Commit bringen. Keine Version wurde manuell erhöht und kein Consumer-Sync ausgelöst.

Lokaler Nachweis für ADR 0005: Gesamtsuite unter PHP 8.5.10 einschließlich zweier Node-Vertragstests erfolgreich. Chromium-Prüfung mit echtem lokalem Leaflet und synthetischem Fetch-Endpoint: genau ein HTML-Dokument/eine Karteninstanz, exakter Mittelpunkt und Zoom sowie ausgewähltes bewegtes Popup über Updates und Netzfehler/Wiederverbindung erhalten. Positionsverlust erhält die Auswahl, Entfernung des Geräts hebt sie auf. Zusätzliche HTTP-SAPI-Prüfung des echten Handlers mit synthetischer Instanz bestätigt 200/403/405/204, JSON, No-Store, Nosniff und CORS ohne Credentials. Native Symcon-Hook-Weiterleitung und reale IPSView-WebViews bleiben praktisch abzunehmen.

## HTTP(S)-Einbindung der IPSView-Karte

ADR 0006 ergänzt den stabilen Zustandsabruf um eine echte HTTP(S)-Kartenseite unter dem instanzbezogenen Hook-Pfad `/page`. Die WebContent-Variable enthält nur noch einen Loader mit iframe, dessen Ziel über die explizite Server-Adresse oder die eigene HTTP(S)-Seitenadresse aufgelöst wird. Es werden weder Referrer noch fremde Elternfenster zur Zielermittlung verwendet.

Der öffentliche GET liefert nur eine leere Seitenschale mit lokalen Assets. Private Kartenanbieterparameter und Gerätedaten werden ausschließlich durch den geschützten POST geliefert. Das Fragment mit dem Bearer wird vor Karten- und Datenanfragen aus der URL entfernt; wenn das im eingebetteten Kontext nicht möglich ist, startet keine authentifizierte Abfrage. Die Seitenschale setzt `Referrer-Policy: origin`, sodass die Kachelrequests einen echten HTTP(S)-Ursprung melden. Eine Aufhebung externer OSM-Sperren wird nicht zugesagt. Es wurde kein zusätzlicher Helper entwickelt und kein kanonischer Helper verändert.

Nach dem Update ist einmaliges Übernehmen der Instanz nötig, damit die vorhandene HTML-Variable den neuen Loader erhält. Bestehende Tokens und der idempotente Datenabruf bleiben erhalten. Datenupdates ersetzen weder iframe noch Kartenseite. Die native Tile-Karte bleibt beim HTML-SDK.

Lokale Verifikation zu ADR 0006 unter PHP 8.5.10: Gesamtsuite mit drei Node-Vertragstests, Struktur, JSON und php-cs-fixer erfolgreich. Chromium lud den echten Loader als opaque data-Dokument und die echte öffentliche Kartenseite als HTTP(S)-iframe. Abgefangene Kachelrequests enthielten den tatsächlichen Symcon-Ursprung als Referer, keine Token im URL/Referer/Header; der Token erschien ausschließlich im Zustandsheader. Über Updates und Ausfall/Wiederherstellung blieb es bei einem HTTP-Seitenabruf und derselben Leaflet-Karte mit exakt gleichem Mittelpunkt, Zoom, Auswahl und Popup. Desktop-/Mobilansicht ohne horizontales Überlaufen geprüft. Alle Karten- und Serverantworten waren synthetisch; öffentliche OSM-Server und Apple wurden nicht angesprochen. Ein HTTP-SAPI-Test des echten Handlers bestätigte zusätzlich die öffentlichen Seitenheader und den privaten POST-Zugang.

## WoIst-Kachellayout

Native Titelbehandlung entspricht dem vorhandenen ECharts-Vertrag: `options.tileHeaderVisible` und 58 Pixel reservierter Titelbereich, wenn die native Kachelüberschrift sichtbar ist. `ObjectIsHiddenTitle` wird aus `IPS_GetObject` gelesen; das Feld ist laut aktueller offizieller Dokumentation erst ab Symcon 9.1 vorhanden. Unter 9.0 ohne Feld gilt der sichere Standard sichtbar. IPSView erhält durchgehend `false`, auch im Zustands-POST. Es wird keine zusätzliche manuelle Titel-Property benötigt.

Der bestehende ResponsiveVisualizationHelper liefert unverändert den Breitencontainer. Das fachliche Layout nutzt eine vollständige Höhenkette und einen schrumpfbaren Flex-/Grid-Bereich. Breite Kacheln zeigen Karte und scrollende Liste auf gleicher Höhe; schmale Kacheln teilen den übrigen Platz in zwei Zeilen. Ausgeblendete Listen geben beide Achsen frei. Höhe0 bedeutet automatisch; vorhandene Höhenwerte bleiben nur als CSS-Fallback erhalten. Die Kachelgröße hat Vorrang vor alten 480-Pixel-Einstellungen.

Leaflets paralleles Window-Resize wird mit `trackResize:false` abgeschaltet. Ein gemeinsamer ResizeObserver-/Fallback-Handler hält Mittelpunkt und Zoom fest, invalidiert die Größe und setzt dieselbe Ansicht ohne Animation mit `reset:true` zurück. Dadurch werden Pixelrundungen vermieden; initiales automatisches Einpassen bleibt von Größenänderungen unberührt. Reine Datenaktualisierungen lösen keine unnötige Größeninvalidierung aus.

Nachweise: PHP-Verträge für sichtbaren/ausgeblendeten nativen Titel, Default-Autohöhe und IPSView ohne nativen Titelabstand; Node-Verträge für Titelabstand und exakten Ansichtsrestore. Chromium mit echtem Leaflet und synthetischen Kacheln geprüft bei 1900×1050,1200×700,640×600,390×844,480×320 und 320×240 sowie 25 synthetischen Geräten: keine Dokumentüberläufe, gleiche Karten-/Listenhöhe bei breiten Kacheln, gestapelte Bereiche bei schmalen Kacheln, interner Listenscroll und Freigabe der Listenfläche bei Ausblendung. Die native Titelüberlagerung ist im Test als Host-Bereich berücksichtigt; die Live-Symcon-Konsole bleibt praktisch abzunehmen.

## Zentraler Layoutvertrag

Die Erkennung der nativen Titel-Sichtbarkeit, der reservierte Titelabstand und der höhenfüllende Flex-Rahmen liegen kanonisch im `ResponsiveVisualizationHelper` des `Symcon_ModuleHelper`. WoIst nutzt `ResponsiveVisualizationLayoutOptions(!$ipsView)` und `ResponsiveVisualizationCSS('#woist-app', 'woist-map', true)`. Lokale SDK-Objektabfragen und 58-Pixel-Konstanten wurden entfernt. Der Browser überträgt nur noch den gelieferten numerischen `tileHeaderInset` nach `--symc-tile-header-inset`; das ist eine Datenbindung ohne eigene Abstandsentscheidung.

Die Aufteilung von Karte und Geräteliste, deren Standortdarstellung und Leaflet-spezifische Ansichtserhaltung bleiben fachlich in WoIst. Der bestehende zweiargumentige Helper-CSS-Aufruf bleibt rückwärtskompatibel; der volle Rahmen wird ausdrücklich aktiviert. Der Consumer enthält unveränderte kanonische Helper-Bytes mit aktualisiertem SHA-256. Die Änderungen im Helper sind lokal und noch nicht committed; source_sha bezeichnet den bisherigen HEAD. Versionierung und Verteilung bleiben bei der vorhandenen Automation nach Benutzer-Commit.

Zentralisierung ist auch im Referenz-Consumer SymconECharts durchgeführt: alle 29 Diagrammmodule beziehen native Titel-Sichtbarkeit und Inset aus derselben Helper-Quelle. Chart-spezifische Ränder bleiben lokal. Das produktiv geladene BarCategory-Minify-Bundle wurde neu gebaut. Die kanonischen Helper-Tests, WoIst-Integration und synthetischen Browserprüfungen sichern Sichtbarkeit, Höhenfüllung, responsives Verhalten sowie unveränderte Ansicht bei Datenupdates ab.

## OpenFreeMap

OpenFreeMapHelper aus Symcon_ModuleHelper besitzt die unabhängigen Kartenprofile, übersetzten Formulare, Farb-/Sichtbarkeits-/Sprachtransformationen und den persistenten MapLibre-Controller. Leaflet bleibt für Geräte, Auswahl und Ansicht zuständig. MapLibre 5.24.0 und MapLibre-GL-Leaflet 0.1.4 sind unverändert und mit Lizenzen/Hashes im Helper-Bundle enthalten. Der Renderer wird über den öffentlichen gzip-Asset-Endpunkt geliefert; Inline-Ausgabe überschreitet Symcons 1-MB-Buffer. Die öffentliche Kartenseite enthält weiterhin keine privaten Stil-URLs oder Kontodaten.

Live per MCP geprüft: beide Designer-Menüs registriert; native Kachel und IPSView-HTTP-Seite rendern OpenFreeMap. Bei Wechsel dunkel → hell, eigener Wasserfarbe und Cache-Update bleiben Leaflet-Ansicht, Geräteauswahl, Popup und ein einzelnes GL-Canvas erhalten. Temporäre Profileinstellungen wurden wiederhergestellt.

## Praxisnachweis: native Live-Pushs am 22.10.2026

Die WoIst-Karte wurde auf dem vom Benutzer freigegebenen angeschlossenen Symcon-System in der echten Kachelvisualisierung geprüft. Der Nachweis erfolgte im nativen `/visu/`-iframe der Kachel, nicht in der statischen MCP-Kachelvorschau und nicht über den IPSView-Pollingtransport. Damit ist die zuvor noch offene Praxisabnahme des normalen nativen Live-Push-Pfads für diesen Stand erbracht.

Prüfablauf und Ergebnis:

- Ein Gerät ausgewählt, Popup geöffnet, Zoom verändert und den Kartenausschnitt per Tastatur verschoben. Der Vergleichszustand wurde nach Abschluss der Kartenanimation erfasst; Zwischenstände während einer laufenden Verschiebeanimation sind kein belastbarer Beleg für einen Ansichtsverlust.
- Eine vorübergehende Änderung von `TileCurrentColor` und anschließendes `ApplyChanges`/`ICWI_Update` aus dem vorhandenen Kontocache machten den Eingang des nativen Updates im bereits geöffneten iframe sichtbar. Danach wurde die ursprüngliche Farbe wiederhergestellt und der Cache erneut ohne Designänderung veröffentlicht.
- Nach den nativen Pushs blieben Zoom, Kartenausschnitt, Geräteauswahl und geöffnetes Popup erhalten. Die iframe-Adresse blieb unverändert und es existierte weiterhin genau ein GL-Canvas; ein Seitenneuladen wurde nicht beobachtet.
- Browserkonsole und Symcon-Protokoll meldeten im Prüfzeitraum keine Fehler; der Instanzstatus blieb aktiv. Die vollständige Instanzkonfiguration wurde abschließend mit dem Ausgangsstand verglichen und war wiederhergestellt.

Für diesen Test wurden weder Apple-Anmeldung noch ein ausdrücklicher Geräteabruf bei Apple ausgelöst; die Testupdates nutzten den bestehenden Kontocache. Private Geräte-, Standort- und Zugangsdaten sowie Screenshots bleiben außerhalb der versionierten Dokumentation. Der Nachweis gilt für die geprüfte laufende Browser-Sitzung; Wiederverbindung, Serverneustart, Kontowechsel und entfernte Geräte wurden damit nicht zusätzlich abgenommen.

Die vorausgehenden MCP-Prüfungen bestätigen zusätzlich Hell-/Dunkeldarstellung und lokale Bedienung in beiden Kachel-Themes sowie den Erhalt von Ansicht, Auswahl und Popup bei Cache-Updates und Themewechsel in IPSView. Browserprüfungen verwenden den zentral dokumentierten Ablauf aus `../SymconDevelopment/standards/SYMCON_ACCESS.md`: leeren Tab erstellen, navigieren, Ladeabschluss prüfen und erst danach Accessibility-/DOM-Abfragen ausführen. Die statische MCP-Kachelvorschau allein weist keine nativen Live-Pushs nach.

## Wiederverbindung: Korrektur und Praxisnachweis am 22.10.2026

Eine gezielte TCP-Unterbrechung ausschließlich für die Testansichten zeigte zunächst den Unterschied zwischen Daten-Push und Wiederverbindung: Der native Host ersetzt seinen Kachel-iframe und verliert damit den bisherigen JavaScript-Zustand; die IPSView-Seite bleibt geöffnet und setzt ihren Pollingtransport fort. Der native Zustand ging vor der Korrektur verloren, obwohl die normalen Live-Pushs bereits erfolgreich geprüft waren.

`SymconOpenFreeMap.createViewState` aus dem zentralen OpenFreeMapHelper speichert ausschließlich validierten Kartenmittelpunkt, Zoom, Auswahl-ID und Popupstatus im tabgebundenen `sessionStorage`. Der Schlüssel trennt Modulinstanz und Kontoverbindung sowie native Visualisierung und Benutzer. Versionierung, vier Stunden Ablaufzeit, Größenprüfung, ungültige Daten und verweigerter Speicherzugriff sind berücksichtigt. Tokens, Passwörter, Gerätenamen und vollständige Geräteinventare werden nicht gespeichert. Die native WoIst-Kachel stellt die Kamera vor dem ersten automatischen Einpassen wieder her und gleicht die Auswahl mit dem aktuellen Inventar ab. Bei zunächst nicht verfügbarem Cache wartet die Auswahlwiederherstellung auf ein gültiges Inventar; entfernte Geräte werden nicht wieder ausgewählt.

Nach der Korrektur per MCP in der echten Kachelvisualisierung erneut geprüft: Gerät ausgewählt, Popup geöffnet, Zoom verändert und Karte verschoben; anschließend bestehende TCP-Verbindungen über einen temporären Loopback-Relay getrennt und neue Verbindungen zeitweise abgewiesen. Die native Visualisierung zeigte die Unterbrechung und verband sich automatisch wieder. Der neu aufgebaute iframe stellte Mittelpunkt und Zoom wieder her; Geräteauswahl und Popup blieben erhalten, mit einem GL-Canvas. Der gleichzeitig geprüfte IPSView-Pollingtransport zeigte seinen Warnhinweis, nahm den Abruf nach Wiederverbindung wieder auf und erhielt ebenfalls Mittelpunkt, Zoom, Auswahl und Popup. Die Rücksetzung interner Leaflet-Pane-Offsets bei der Größenänderung durch den Hinweis ist kein Verlust des geografischen Kartenmittelpunkts.

Der Test änderte keine dauerhafte Instanzkonfiguration und löste weder Apple-Anmeldung noch einen ausdrücklichen Apple-Geräteabruf aus. Der temporäre Relay und seine Steuerdateien werden nach dem Test entfernt; private Screenshots bleiben außerhalb der Versionierung. Neue Tabs, verweigerter Sitzungsspeicher und abgelaufene Speicherstände beginnen weiterhin ohne gespeicherte Ansicht; ein server- oder geräteübergreifender Ansichtsabgleich ist nicht Bestandteil dieser Korrektur.

## Wiederverbindung nach Helper-Verteilung: erneuter MCP-Nachweis am 22.10.2026

Der auf dem freigegebenen angeschlossenen Symcon-System geladene OpenFreeMapHelper-Stand wurde gegen die auf `dev` verteilte Kartenressource geprüft: OpenFreeMapHelper 1.0.1 aus HelperBundle 3.20.7, identische SHA-256-Prüfsumme. Die erneute Laufzeitprüfung verwendete die echte native Kachelvisualisierung sowie die aktuelle IPSView-HTML-Ausgabe in einer Browser-Testeinbettung mit ihrer echten HTTP-Kartenseite und ihrem echten Pollingtransport. Der vollständige IPSView-Client war nicht Gegenstand dieses erneuten Laufs.

Der abschließende zusammenhängende MCP-Browserlauf trennte über einen temporären lokalen Relay bestehende TCP-Verbindungen und wies neue Verbindungen acht Sekunden lang ab. Neun abgewiesene Verbindungen und ein fehlgeschlagener IPSView-Datenabruf belegten den Ausfall. IPSView zeigte den Transporthinweis. Nach Wiederfreigabe wurde der Retry-Abstand berücksichtigt und weitere 18 Sekunden gewartet: drei erfolgreiche IPSView-Datenabrufe und der verschwundene Hinweis belegten den Wiederanlauf. Der native Host ersetzte seinen Kachel-iframe und verband sich ebenfalls wieder.

Ergebnis in beiden Ausgaben: Zoom unverändert, geografischer Mittelpunkt innerhalb einer Vergleichstoleranz von 0,0000001 Grad unverändert, Geräteauswahl und geöffnetes Popup erhalten, weiterhin genau ein GL-Canvas und keine erfassten JavaScript-Seitenfehler. Der Nachweis betrifft die geprüfte Sitzung und Verbindungsunterbrechung; Serverneustart, neuer Tab und abgelaufene Sitzungsspeicherung wurden nicht erneut getestet.

Die vorausgehenden Timeouts wurden eingegrenzt: Playwright wartete auf einen außerhalb des sichtbaren Host-Bereichs liegenden Gerätebutton. Für den Zustand-/Transporttest wurde dessen vorhandene DOM-Klickaktion verwendet; dies ist kein Nachweis eines erfolgreichen Zeigerklicks. Ein beim absichtlichen Ausfall unbehandelter `ECONNRESET` aus `route.fetch` beendete außerdem die MCP-Testlaufzeit. Nach Behandlung der erwarteten Route-/Transportfehler und Ausführung in einem zusammenhängenden Lauf war der Test erfolgreich. Daraus ergibt sich kein belegter Fehler des Moduls oder des Symcon-MCP. Der verbindliche Ablauf steht unter `../SymconDevelopment/standards/SYMCON_ACCESS.md#mcp-wiederverbindungspruefungen`.

Browser und Relay des abschließenden Laufs wurden im `finally`-Pfad geschlossen. Es gab keine Änderungen an Modulcode oder dauerhafter Instanzkonfiguration und weder Apple-Anmeldung noch einen ausdrücklichen Apple-Geräteabruf. Private Geräte-, Standort- und Zugangsdaten werden nicht in diesem Nachweis gespeichert.

## Wiederverbindung im echten IPSView-Webclient: MCP-Nachweis am 22.10.2026

Zusätzlich zur vorherigen Testeinbettung wurde die WoIst-Karte im echten IPSView-Webclient 6.5.18 auf dem vom Benutzer ausdrücklich genannten Symcon-System geprüft. Die bestehende Ansicht wurde regulär geladen und die Seite mit der eingebundenen WoIst-HTML-Variable über ihre vorhandene Navigation geöffnet. Dieser Nachweis betrifft damit den vollständigen Client einschließlich seiner Datenabrufe und der darin verschachtelten HTTP-Kartenseite.

Ein Gerät wurde über den sichtbaren Gerätebutton ausgewählt, das Popup geöffnet und die Karte auf Zoom 11 gesetzt und verschoben. Nach Abschluss der Auswahlanimation wurden Zoom, geografischer Mittelpunkt und Geräteauswahl erfasst. Die Unterbrechungen erfolgten mit dem Offline-Modus ausschließlich im Browser-Testkontext; dauerhafte Server-/Instanzkonfiguration und andere Clients blieben unverändert.

- Erster Lauf: acht Sekunden offline, danach 18 Sekunden Wiederanlauf. Ein Kartenabruf und zwei Clientabrufe schlugen fehl; anschließend waren zwei Kartenabrufe und 38 Clientabrufe erfolgreich. Zoom, Mittelpunkt, Auswahl und Popup blieben erhalten; der Karten-iframe wurde in diesem Lauf nicht ersetzt.
- Zweiter Lauf: 17 Sekunden offline, danach 20 Sekunden Wiederanlauf. Der Transporthinweis wurde sichtbar und verschwand nach Wiederverbindung. Zwei Kartenabrufe und 51 Clientabrufe schlugen fehl; anschließend waren ein Kartenabruf und 40 Clientabrufe erfolgreich. Zoom und Auswahl waren unverändert, der Mittelpunkt innerhalb einer Toleranz von 0,0000001 Grad unverändert und das Popup weiterhin geöffnet. Genau ein GL-Canvas und keine erfassten JavaScript-Seitenfehler bestätigten den stabilen Kartenbetrieb.

Zur Messung wurde ausschließlich die Kartenreferenz im Browser erfasst. Ein vorausgehender Versuch mit ersetzter HTTP-Antwort löste Chromes Sperre für Zugriffe auf einen privateren Netzwerkadressraum aus und wurde verworfen. Der erfolgreiche Test verwendete nach regulärem Neustart des Clients unveränderte HTTP-Antworten und keine gelockerten Browser-Sicherheitsregeln.

Der Netzwerkzugriff wurde im Abschluss-/Fehlerpfad wieder freigegeben und der Testbrowser danach geschlossen. Modulcode und dauerhafte Konfiguration wurden nicht geändert; weder Apple-Anmeldung noch ein ausdrücklicher Apple-Geräteabruf wurden ausgelöst. Serverneustart, neuer Tab, abgelaufene Sitzungsspeicherung und native IPSView-Apps wurden mit diesem Lauf nicht zusätzlich geprüft. Private Serveradressen, Objekt-IDs, Geräte- und Standortdaten sowie Zugangsdaten bleiben außerhalb dieses Nachweises.

## WoIst-Designer: zentrale Farb- und Stilkonfiguration am 22.10.2026

Die Designer enthielten bisher nur modulspezifische Kartenparameter und OpenFreeMap-Profile; die bereits vorhandenen zentralen Konfigurations-Helper waren nicht eingebunden. IPSView bekam lediglich das allgemeine Symcon-Theme. WoIst nutzt jetzt `VisualizationThemeConfigurationHelper` für Kachelfarben und `IPSViewStyleConfigurationHelper` für IPSView-Stilquellen, semantische Farben, relevante native Farbfamilien, Transparenz, Typografie, Rahmen und Schatten. Beide Pakete sind in `.helper-sync.json` abonniert und unverändert mit ihren manifestierten Abhängigkeiten, Fonts und Übersetzungen eingebunden. Es war keine neue gemeinsame Helper-Implementierung erforderlich. Der bereits ausgelieferte OpenFreeMap-Stand einschließlich Sitzungsspeicherung bleibt erhalten.

Die Karte bindet die zentralen Farbrollen an ihre eigenen Bedienelemente und Popups. Kartenlayout, Marker und OpenFreeMap-Profil können weiterhin der Kachel folgen oder kopiert werden; die IPSView-Stilquelle ist unabhängig davon. Die zentrale IPSView-Typografie löst das frühere FontSize-Eingabefeld ab; dessen Property bleibt registriert. Änderungen werden über einen eigenen Stylesheet-Knoten in bestehenden Dokumenten angewendet. Ein MM_UPDATE der ausgewählten Stilmedien veröffentlicht den aufgelösten Stil erneut, ohne den IPSView-Loader zu ersetzen.

Nachweise: vollständige Offline-Suite unter PHP 8.5.10, Formular-/Erstellungsverträge, Struktur-/Hash-/LF-Prüfung, gezielte Tests für Designer-Felder, unabhängige Stile, Medienänderungen und Kameraerhalt sowie php-cs-fixer im Prüfmodus. Ein synthetischer Browserlauf bestätigte eigene Kachel-/IPSView-Hintergründe und Textfarben, Popupfarbe, Schriftgröße samt Skalierung, Hell-/Dunkelpresets und Transparenz. Zoom, geografischer Mittelpunkt und Geräteauswahl blieben beim Stilwechsel erhalten. Der Browserlauf ersetzte ausschließlich externe Kartendaten durch synthetische Daten und änderte kein angeschlossenes Symcon-System. Eine Live-Abnahme der neu ergänzten Designer steht noch aus.

## Zentrale Mausradbehandlung am 22.10.2026

Die Ausgabe verwendet die kanonische Interaktionsressource aus `VisualizationAssetHelper`, die `IPSViewHTMLPageHelper` automatisch vor dem Modulskript einbindet. Generische Scroll-/Delta-/Pointer-/Frame-Mechanik wird nicht mehr modulweise neu implementiert. Die verbindliche Prüfregel einschließlich getrenntem Webclient-/App-Nachweis steht in `SymconDevelopment/standards/TESTING.md`.

Einordnung des Praxisbefunds: Chrome- und Edge-Webclients konnten die WoIst-Karte zoomen und die Geräteliste scrollen. Die native Windows-IPSView-App 6.5.19 ignorierte dieselben echten Mausradaktionen in ihrem bisherigen Stand. Die gemeinsame Korrektur ist lokal eingebunden; die direkte native Abnahme nach Aktualisierung des Zielsystems ist noch offen. Synthetische Regressionen sichern umadressierte Ziele mit `(0, 0)`, korrigierte Zeigerposition, Delta-Modi, Ctrl-/Meta-Ausnahmen und skalierte direkte Frame-Weitergabe ab. Browserprüfungen mit überlaufenden synthetischen Geräten bestätigten normalen Kartenzoom sowie Scrollen der inneren Liste über den äußeren Loader, ohne gleichzeitigen Kartenzoom. Diese Nachweise ersetzen keine nachträgliche native App-Abnahme.

WoIst bindet den zentralen Helper auch im äußeren IPSView-Loader ein und registriert den Kartenzoom als Wheel-Region. Der normale Leaflet-Wheelhandler wird nur für IPSView deaktiviert, damit ein Ereignis nicht doppelt zoomt. Der native HTML-SDK-Pfad behält seinen Leaflet-Handler.

Abschließende Prüfungen des lokalen Stands: alle fünf vollständigen PHP-Suiten (zentraler Helper und vier Consumer) bestanden, ebenso die gezielten Wheel-/Kalender-/ECharts-Tests, der Minify-Buildvergleich und die vollständigen manifestierten Ressourcen aller vier Verbraucher. Im finalen lokalen Browserlauf änderte echtes Mausrad den Zoom von 15 auf 16 und scrollte die Liste auf 240 Pixel. An den äußeren Body umadressierte (0, 0)-Ereignisse erreichten über den Helper die innere Karte bzw. Liste: Zoom zurück auf 15, Listenscroll auf 360 Pixel, ohne Kartenzoom beim Scrollen. Styles, Hashes und LF-Zeilenenden geprüft. Die native App-Abnahme des aktualisierten Modulstands bleibt offen; es wurde kein Live-Code ausgetauscht und kein Commit oder Push ausgeführt.

## Hervorgehobene Geräteauswahl am 22.10.2026

Die vorhandene Listenwahl zentriert die Karte bereits auf die Geräteposition. Ergänzt ist eine sichtbare Markerhervorhebung: fünf Pixel zusätzlicher Radius zur Designergröße, stärkerer weißer Rand und Vordergrundposition bei Überlagerung. Aktuelle/veraltete Positionsfarben bleiben erhalten. Ein Wechsel setzt die vorige Hervorhebung zurück und schließt das alte Popup; ein Gerät ohne Position verschiebt die Karte nicht. Datenupdates und Wiederaufbau stellen die Hervorhebung wieder her, ohne die Kamera nachzuführen.

Die Kachel und IPSView verwenden denselben Modulcode sowie die vorhandenen zentralen Karten-, Transport- und Sitzungsspeicher-Helper; neue gemeinsam genutzte Mechanik war nicht erforderlich. Marker- und Geräteauswahl bleiben modulspezifisch. Regressionen und die vollständige Offline-Suite unter PHP 8.5.10 bestanden. Ein lokaler Browserlauf mit synthetischen Geräten bestätigte Listenwahl, genau einen hervorgehobenen Marker, Zentrieren, Gerätewechsel, Mausradzoom und Listenscroll sowie Erhalt von Auswahl und Kamera bei einem tatsächlichen Polling-Update mit bewegter Position. Externe Kartendaten wurden durch eine synthetische Hintergrundkarte ersetzt. Die Live-Abnahme dieses neuen Hervorhebungsstands bleibt offen; das angeschlossene System wurde nicht geändert.

## Explizite Apple-Aktualisierung in IPSView am 22.10.2026

Der bisher ausgeblendete Aktualisieren-Button ist auch in IPSView aktiv. Ein fester POST-Endpunkt `/refresh` verwendet den vorhandenen instanzbezogenen Token sowie die bestehende `RefreshDevices`-Funktion des Kontomoduls. Kontoanmeldung, Serialisierung und Wartezeit bleiben dort zentral. Normales Polling liest weiter nur den Cache. Der Token berechtigt nun auch zu dieser einzelnen ausdruecklichen Aktualisierung; beliebige Fernaktionen sind nicht freigegeben. Falsche Tokens, deaktivierte Ausgabe, unpassende Kontoverbindung, GET und abweichende Pfade werden vor dem Kontoaufruf abgewiesen.

Der Client verhindert parallele Aktualisierungen, unterbricht laufende Cache-Abfragen und nimmt danach das normale Polling wieder auf. Der explizite Aufruf hat ein Zeitlimit von 30 Sekunden und wird nicht automatisch wiederholt. Die Antwort verwendet den vorhandenen Zustandspfad ohne HTML-Neuerzeugung. Die vollstaendige Offline-Suite unter PHP 8.5.10, PHP-Style, JSON- und gezielte PHP-/JS-Transporttests bestanden. Ein lokaler MCP-Browserlauf mit synthetischen Daten bestaetigte den sichtbaren Button, Sperre waehrend des Aufrufs, aktualisierte Daten, Fehlerbehandlung, Kamera-/Auswahlerhalt sowie weiterhin funktionierenden Mausradzoom und Listenscroll. Vor dem Kameravergleich wurde die Auswahlanimation abgeschlossen. Es gab keinen echten Apple-Aufruf und keine Live-Installation; die native IPSView-Abnahme dieses neuen Stands bleibt offen.
