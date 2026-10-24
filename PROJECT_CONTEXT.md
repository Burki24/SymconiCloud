# Entwicklungsstand

## Kompakte Anwesenheitswidgets (2026-10-23)

Native HTML-Kachel und optionale IPSView-WebContent-Variable zeigen
Gesamtanwesenheit und ausgewählte Einzelgeräte, ohne vollständige Dashboardseite.
Ein optionales Bildmedium ergänzt jede `Devices`-Zeile; fehlende/ungültige Bilder
verwenden Initialen. Zonen, GPS-Zustände und bestehende Variablenkennungen bleiben.

Darstellung, Bildprüfung, Cachetransport und unabhängige Designer liegen im
librarylokalen Trait `libs/PresenceWidget.php`; Ressourcen beim Anwesenheitsmodul. Bestehende Helper liefern Theme, IPSView-Farben,
Schriften, Interaktionen und Reservierung des Kacheltitels. Das Modul liefert
bereits ausgewertete Werte, keine Koordinaten oder Apple-Kennungen. IPSView-
Datenupdates schreiben die HTML-Variable nicht neu.

Die Widget-Tests einschließlich Bildvalidierung und JavaScript-Laufzeit liegen
in SymconiCloud. Die Verlagerung entfernt kein Verhalten und keine Testabdeckung.
Die unveränderten zentralen Basishelper werden weiterhin regulär verteilt.

MCP: getrennte temporäre Library mit zwölf synthetischen Geräten (Anwesend,
Abwesend, Unbekannt) und lokalem Testbild. Kachelvorschau in Hell/Dunkel, responsive
Abmessungen, Bild/Initialen und Mausrad in beide Richtungen erfolgreich.
IPSView-Testeinbettung bei 360 × 320 Pixeln: echter Cache-Datenwechsel ohne Neuladen.
Sichtbarer Status wechselte, eingebetteter Bootstrap blieb unverändert, Auswahl und
Scrollposition blieben erhalten. Der zentrale Dunkelstil wurde ohne Dokumentwechsel
übernommen. Bei unterbrochenem Cache-Zugriff erschien ein Hinweis, während Auswahl
und Scrollposition erhalten blieben.

Grenzen: Statische Kachelvorschau bestätigt keine nativen Live-Pushs.
IPSView-Testeinbettung ersetzt weder echten Webclient noch native IPSView-App;
diese Hostprüfungen bleiben nach regulärer Installation offen. Die zusätzliche
WebContent-Vorschau des Symcon-Clients blieb im Ladezustand mit einem Fehler in
`main.dart.js`; direkte HTML-Kachel und Widget wurden getrennt davon geprüft.

Zuständigkeitskorrektur: Tatsächliche Nutzung in mindestens zwei verschiedenen
Libraries ist die Voraussetzung für Zentralisierung. Für diesen Widget-Baustein
ist nur SymconiCloud belegt; Abonnement, zentraler Quellcode und zentrale Tests
werden deshalb entfernt. Das Widget wird unmittelbar mit der Library ausgeliefert.
Der CI-Lauf 39967522183 scheiterte an `Unknown helper dependency: StatusWidgetHelper`,
weil der beim Pull ausgelagerte Manifest-Eintrag vor dem Commit nicht wieder
enthalten war. Die korrigierte lokale Zuständigkeit benötigt diesen Eintrag nicht.
Keine Commits, Pushs, Merges oder Releases durch den Agenten.

Nach der Zuständigkeitskorrektur bestehen die vollständige lokale Modulsuite,
Bild-/JavaScript-Tests, lokale Ressourcen-/Übersetzungsverträge und Stilprüfung
unter PHP 8.5.10. Das zentrale Manifest umfasst wieder 30 Helper; Bundle-,
Übersetzungs- und Helper-Prüfungen sowie die isolierte CI-Metadatenvorbereitung
mit den Sicherheits-/Verteilungstests bestehen ebenfalls.
Erneute MCP-Prüfung mit synthetischen Daten bestätigt die librarylokalen Pfade:
native Strict-Instanz aktiv, beide HTML-Ausgaben gefüllt, Kacheltitelabstand,
deutsche Designertexte und gemischte Gesamtanwesenheit korrekt. Prüfinstanz und
Library wurden entfernt; die bestehende Anwesenheitskonfiguration samt IDs und
Verbindung blieb unverändert. Die früher dokumentierten Grenzen für echte
Host-Live-Pushs und die native IPSView-App bleiben bestehen.

Nach der Prüfung wurden temporäre Instanz, Bilddatei, Library und Hook entfernt.
Der Vorher-/Nachhervergleich der bestehenden Anwesenheitsinstanz bestätigt
unveränderte Konfiguration, Verbindung und vollständige Variablen-ID-Liste.

## Installierte Zonen-GPS-Vorgaben: MCP-Prüfung und Wiederherstellung (2026-10-23)

Nach den vom Benutzer abgeschlossenen Updates wurde die bestehende
Anwesenheitsinstanz auf dem angeschlossenen Symcon-System geprüft. Die neuen
Modulfunktionen und GeofenceHelper 1.2.0 waren geladen; dessen Datei entsprach
dem SHA-256 des installierten Helper-Manifests. Die Instanz war aktiv und
verwaltete zwei Geräte mit einer gemeinsamen Referenzzone und 29 Variablen.

Der abschließende Lauf bestand mit **29 Prüfungen ohne Warnungen oder Fehler**.
Temporäre Konfigurationen und kontrollierte Standortdaten wurden an der
Auswertungsgrenze der bestehenden Instanz verwendet. Der Apple-Kontocache blieb
unverändert; zusätzliche Apple-Abfragen oder Neuortungen wurden nicht ausgelöst.
Geprüft wurden globale Übernahme trotz ruhender eigener Werte, abweichende
GPS-Vorgaben beider Zonen, wirksame Radius-/Puffergrenzen, deutsche dynamische
Formularbeschriftungen sowie getrennte Gerätewerte und Gesamtanwesenheit.

Eintritt und Austritt berücksichtigten die längere Bestätigungszeit von Herkunft
und Ziel. Wiederholte Cachepositionen bestätigten keinen Wechsel. Ungenaue Daten
führten zu Unbekannt und unterbrachen den Aufenthalt; weder spätere ungenaue
Positionen noch ein Wechsel über Unbekannt umgingen Herkunftsqualität oder
Bestätigungszeit. Eine weit entfernte Zielzone durfte ihre eigene großzügigere
Genauigkeitsgrenze nutzen. Auch eigener Grenzpuffer, Austritt aus allen Zonen,
Beginn und Dauer eines bestätigten Aufenthalts sowie sichere Ablehnung einer
ungültigen Rückkehr zu globalen Vorgaben wurden nachgewiesen.

Die Wiederherstellung erfolgte im Abschluss-/Fehlerpfad und wurde anschließend
erneut lesend geprüft: vollständige ursprüngliche Konfiguration, Kontoverbindung,
15-Sekunden-Timer, alle 29 Variablen-IDs und beide bisherigen Aufenthaltsbeginne
waren erhalten. Die beiden archivierten Anwesenheitsvariablen wurden während der
kontrollierten Testwechsel kurz von der Protokollierung ausgenommen; ihre
ursprüngliche Archivierung war danach wieder aktiv. Gesicherte Auswertungszustände
wurden zurückgespielt und über die normale Modulauswertung mit den Echtwerten
abgeglichen. Status 102, beide Geräte Anwesend und Gesamtanwesenheit Anwesend
waren wiederhergestellt. Ein nachfolgender regulärer Timerlauf aktualisierte
beide Aufenthaltsdauern weiter, ohne deren Beginn zurückzusetzen.

Beim ersten Lauf wies Symcon direkte Wiederherstellungsversuche an den
schreibgeschützten Variablen ab. Die Teststeuerung wurde auf den moduleigenen
SDK-Wertzugriff korrigiert; der danach vollständig wiederholte Lauf war
warnungsfrei. Dies erforderte keine Änderung am Modul oder an den Helpern.
Private Geräte-/Objektkennungen, Endpunkte und Koordinaten werden hier nicht
gespeichert. Reale GPS-Bewegungen und die interaktive Bedienung des neuen
Zonendialogs sind durch diesen kontrollierten Lauf nicht nachgewiesen.

## Anwesenheit: optionale GPS-Vorgaben je Zone (2026-10-23)

Zonen können im eigenen Bearbeitungsdialog **Eigene GPS-Einstellungen für diese
Zone verwenden** aktivieren: Grenzpuffer, maximale Standortungenauigkeit und
Bestätigungszeit. Standardmäßig werden weiterhin die globalen Werte übernommen.
Der zentrale GeofenceHelper löst aktive Überschreibungen auf und prüft jeden
Radius mit seinem wirksamen Puffer. Standortalter und Geschwindigkeitsprüfung
bleiben global. Die Entscheidung und der kompatible Zustandsvertrag stehen in
[ADR 0009](docs/adr/0009-zone-gps-inheritance.md).

Wechsel berücksichtigen Herkunft und Ziel sowie die längere Bestätigungszeit.
Eine ungenaue Position oder ein Wechsel über Unbekannt kann die strengeren
Austrittsregeln nicht umgehen. Auch nach Qualitätsverlust benötigt der Wechsel
in eine andere Zone eine neue bestätigende Position nach Ablauf der Zeit.
Währenddessen bleiben Anwesenheit unbekannt und Aufenthalt nicht verfügbar.
Die Priorität der Referenzzone bei Überlappungen bleibt erhalten.

Bestehende Zonen und Zustände bleiben lesbar. Ohne aktive Überschreibungen bleibt
die normalisierte Quellidentität unverändert; Variablen werden nicht neu angelegt.
Der Zeilendialog wird für jede Öffnung neu aus den aktuellen, auch ungespeicherten
globalen Werten erzeugt. Eigene Werte sind optional sichtbar, werden bei
Deaktivierung behalten und nicht angewendet. Radius-/Puffergrenzen gelten auch
beim Umschalten der Übernahme. Die dynamischen Beschriftungen werden ausdrücklich
über den Modul-Sprachkontext übersetzt. Das Zeilenfeld `id` kann die Instanz-ID
der Formularaktionen nicht überschreiben.

### Nachweis und Auslieferung

Unter PHP 8.5.10 bestanden die vollständigen Helper- und Modulsuiten mit einem
isoliert erzeugten vollständigen Consumer-Paket: 15 abonnierte Helper und 61
Helper-/Ressourcendateien. Manifest-, Abhängigkeits-, Übersetzungs-, Struktur-,
JSON-/LF- und Stilprüfungen bestanden. Die neuen Regressionen prüfen Vererbung,
wirksame Eingabegrenzen, beide Wechselrichtungen, Bestätigungszeiten, wiederholte
Cachepositionen, Herkunftsschutz nach Unbekannt, Referenzüberlappung, Dauer,
Formularaktionsverträge und unveränderte Variablen-IDs.

Der abschließende MCP-Lauf in einer temporären nativen Bibliothek bestand mit
28 Prüfungen. Nur Uhrzeit und Kontocache waren synthetisch; Erstellung,
Konfiguration, Attribute, Variablen, Übersetzung und generierte Modulaufrufe
liefen nativ. Der Lauf prüfte zwei Geräte, Gesamtanwesenheit, Ein-/Austritt,
Qualitätsverlust mit bestätigter Wiederaufnahme, Aufenthaltsdauer, globale
Übernahme, eigene Werte, ungültige Einstellungen, Referenzüberlappungen und IDs.
Die Testinstanz und Bibliothek wurden vollständig entfernt. Die bestehende
Anwesenheitsinstanz blieb aktiv; Konfiguration, Kontoverbindung und alle
29 Variablen-IDs waren unverändert. Private Kennungen und Koordinaten werden
hier nicht gespeichert. Interaktive Bedienung des neuen Dialogs im regulär
aktualisierten Client und echte GPS-Bewegungen sind damit noch nicht nachgewiesen.

Zuerst die zentrale Helper-Änderung freigeben und automatisch verteilen lassen,
dann das reguläre Paket pullen und die Moduländerung veröffentlichen. Der normale
Consumer enthält keine lokalen Helper-Testkopien. Bestehende Subscription und
Verteilungsregeln reichen aus; neue Ressourcen sind nicht erforderlich.

## Anwesenheit: Formulargrenzen und erneute Laufzeitprüfung (2026-10-23)

Die fachliche Bedingung `radius > hysteresis` bleibt im zentralen GeofenceHelper
verbindlich. Das Modul verwendet weiterhin den ConfigurationFormHelper für das
dynamische Formular. Modulbezogene Eingabegrenzen begrenzen den Radius-Editor
auf `buffer + 1` und den ganzzahligen Puffer auf einen Wert unter dem kleinsten
Radius aller Zonen. Die dokumentierten List-Ereignisse `onAdd`, `onEdit` und
`onDelete` sowie `NumberSpinner.onChange` verwenden die noch nicht übernommenen
Formularwerte. Komplexe Editor-Updates werden JSON-codiert übergeben. Die
Formularprüfung persistiert keine Properties und liest keinen Kontocache.
Bei ungültigen gespeicherten Kombinationen nennt der Fehlertext Zone und Werte;
die unabhängige Laufzeitprüfung bleibt aktiv und hält den Timer angehalten.

Gezielte Regressionen prüfen Gleichheit, die kleinste Neben-/Referenzzone,
ungespeicherte Änderungen, Warnung und Korrektur, leere bzw. fehlerhafte Listen,
JSON-Updates, unveränderte Properties/Variablen-IDs und ausbleibende Kontoaufrufe.
Die dokumentbasierten CI-Vertragsprüfungen berücksichtigen zusätzlich die drei
List-Ereignisse und die JSON-Serialisierung bekannter Formularfelder.

Die erneute lesende MCP-Prüfung der installierten Anwesenheitsgruppe ergab
Status 102 bei zwei ausgewählten Geräten, einer gemeinsamen Referenzzone und
29 Variablen. Der Benutzer hatte den Grenzpuffer auf 9 m bei 10 m Radius und
15 m maximaler Ungenauigkeit korrigiert. Der 15-Sekunden-Timer aktualisierte die
Werte; beide Entfernungen stimmten mit einer unabhängigen Großkreisberechnung
aus dem Kontocache überein. Ein zuverlässig abwesendes und ein im Grenzbereich
unbekanntes Gerät ergaben korrekt eine unbekannte Gesamtanwesenheit mit den
passenden Zählern. Bei beiden Geräten waren Aufenthalt und Beginn 0 und als
nicht verfügbar gekennzeichnet. Ein fortlaufender positiver Aufenthalt ließ
sich in dieser realen Konstellation daher nicht prüfen.

Konfiguration und alle 29 Variablen-IDs blieben während der erneuten Prüfung
unverändert. Die zuvor erfassten Einzelgeräteinstanzen waren nicht mehr vorhanden;
eine ID-Migration vom früheren Einzelgerätebestand kann dieser Lauf somit nicht
bestätigen. Die neu ergänzte Formularabsicherung wurde lokal geprüft und noch
nicht auf das angeschlossene System installiert. Ein nativer Bediennachweis
dieser neuen Grenzen bleibt bis zum regulären Modulupdate offen. Es wurden weder
Einstellungen geändert noch zusätzliche Apple-Abfragen ausgelöst.

## Anwesenheitsgruppen: mehrere Geräte und gemeinsame Zonen (2026-10-23)

Die Anwesenheitsinstanz wurde auf eine Gruppe mit mehreren Geräten desselben
Kontos umgebaut. Geräte werden einmal in `Devices` ausgewählt; Zonen,
Referenzzone und GPS-Einstellungen gelten gemeinsam. Elf Gerätevariablen sowie
je Gerät getrennte Zustände und Aufenthaltsdauern bleiben erhalten. Sieben
Gruppenvariablen zeigen Gesamtanwesenheit, Geräteanzahlen und Kontoverfügbarkeit.
Die Architektur- und Migrationsentscheidung steht in
[ADR 0008](docs/adr/0008-shared-zone-presence-groups.md).

`GeofenceHelper::AggregateGeofencePresence` bildet zentral den Gesamtstatus:
mindestens ein anwesendes Gerät genügt für Anwesend; Abwesend erfordert eine
nichtleere Auswahl ausschließlich abwesender Geräte. Sonst gilt Unbekannt.
Fehlende, veraltete oder ungenaue Geräte werden nicht als abwesend gezählt.
Die Gruppe liest den Kontocache nur einmal je Aktualisierung. Es entstehen
keine weiteren Apple-Abfragen oder Ortungsanforderungen.

Die offizielle SDK-Migration übernimmt eine alte Einzelgeräte-Auswahl nur bei
noch fehlender neuer Liste. Eine bewusst leere Liste bleibt leer. Alte
Gerätevariablen behalten ihre Objekt-IDs und werden auf Geräte-Idents umbenannt;
die Gesamtanwesenheit erhält eine eigene Variable. Ein bestehender Aufenthalt
wird bei unveränderter Quellzuordnung übernommen. Hinzufügen, Umbenennen und
Umordnen beeinflussen andere Geräte nicht. Entfernte Geräte werden aus der
Berechnung genommen; ihre Variablen bleiben unbekannt und ausgeblendet erhalten.
Die neue API `ICPR_GetDeviceVariableIdent` liefert stabile Idents für Skripte.
Der Konfigurator ordnet Anwesenheitsgruppen anhand des Kontos zu und erstellt
eine leere Gruppe, anstatt je Gerät eine neue Instanz anzubieten.

### Offline- und MCP-Nachweis

Die vollständigen Helper- und Modulsuiten unter PHP 8.5.10 bestanden mit einem
isoliert erzeugten vollständigen Helper-Paket. Die bisherigen Einzelgeräte-
Regressionen laufen weiterhin; zusätzliche Gruppenprüfungen erfassen
Mischzustände, unabhängige Bestätigung und Aufenthaltsdauer, fehlende Geräte,
Auswahländerungen, Quellenfehler, Alterung, Variablen-IDs und Migration.
Auch der bisherige committete Modulstand bestand mit dem erweiterten Helper:
Das zentrale Paket kann vor der fachlichen Moduländerung verteilt werden.

Per MCP wurde die neue Implementierung in einer eigenen temporären Bibliothek
mit nativ registriertem Modul geprüft. 25 Prüfungen bestanden ohne Warnungen
oder Fehler im erfolgreichen Lauf. Sie erfassten native Erstellung und
Konfigurationsanwendung, persistente Attribute, getrennte Variablenblöcke,
Klartextanzeige, Gesamtstatus und Anzahlen, Zonenwechsel mit Bestätigung,
unbekannte und fehlende Geräte, Aufenthaltsdauern, Reihenfolge und erneute
Auswahl, Kontofehler mit Wiederaufnahme, Standortalterung und SDK-Migration.
Das Formular enthielt sowohl gelistete als auch weiterhin ausgewählte fehlende
Geräte; die neue Gruppenvariable verwendete die deutsche Modulübersetzung.

Die MCP-Prüfung nutzte kontrollierte Zeitpunkte und synthetische Kontocachedaten.
Reale GPS-Bewegungen und der Transport vom Apple-Server waren nicht Teil dieses
Nachweises. Die installierte SymconiCloud-Library wurde nicht verändert oder
neu geladen. Konfigurationen, Kontoverbindungen und Variablen-IDs aller zuvor
vorhandenen Anwesenheitsinstanzen waren nach der Prüfung unverändert.
Testinstanzen, Testdateien und die temporäre Modul-/Library-Registrierung
wurden entfernt. Private Instanz- und Gerätekennungen oder Koordinaten werden
hier nicht gespeichert.

Ein erster Test mit manuell aufgerufenem `Create` konnte neue Attribute nicht
registrieren: Symcon erlaubt dies ausschließlich im nativen Erstellungszyklus.
Dieser abgebrochene Test wurde nicht als Erfolg gewertet. Die anschließende
Prüfung nutzte deshalb eine eigene temporäre Bibliothek mit echtem Lebenszyklus.
Ein statischer Formularcaption-Vergleich war außerdem kein Sprachvertrag der
API-Ausgabe; die Übersetzung wurde stattdessen am tatsächlich registrierten
nativen Variablennamen nachgewiesen.

### Auslieferung

Zunächst die zentrale Helper-Ergänzung über den Benutzerworkflow freigeben und
den vorhandenen automatischen Helper-Sync integrieren und pullen. Es ist keine
neue Subscription erforderlich; `libs/helper` bleibt hier frei von lokalen
Testkopien. Anschließend den tatsächlichen Integrationsstand mit
`php tests/run.php` prüfen und die fachliche Moduländerung veröffentlichen.
Die vorhandenen Anwesenheitsinstanzen wurden während der MCP-Prüfung noch nicht
auf den neuen fachlichen Stand umgestellt. Ein echter Serverneustart und die
interaktive native Formularbedienung nach dem regulären Update bleiben offen.

## iCloud Anwesenheit: erfolgreiche MCP-Prüfung (2026-10-23)

Nach dem vom Benutzer abgeschlossenen Modulupdate wurde die vorhandene
Anwesenheitsinstanz auf dem angeschlossenen Symcon-System per MCP geprüft.
Kontoverbindung, Gerätezuordnung, geladenes Anwesenheitsmodul und zentraler
`GeofenceHelper` 1.0.0 waren vorhanden. Alle elf Variablen waren angelegt und
ausschließlich lesbar; die native Integer-Darstellung zeigte Klartext.

Auf ausdrücklichen Auftrag wurden folgende Einstellungen übernommen:

| Einstellung | Gewählter Wert |
| --- | --- |
| Radius der Referenzzone | 100 m |
| Grenzpuffer (`Hysteresis`) | 25 m |
| Maximale Standortungenauigkeit (`MaxAccuracy`) | 50 m |

Die nach dem Übernehmen zurückgelesene Konfiguration entsprach den gewünschten
Änderungen; Gerätezuordnung, Zonenmittelpunkt und übrige Einstellungen blieben
erhalten. Der Radius ist größer als der Grenzpuffer. Mit der gelieferten
Standortgenauigkeit lag die Position einschließlich Unsicherheitsradius eindeutig
innerhalb der verkleinerten Eintrittsgrenze der Referenzzone.

Der Laufzeitnachweis bestätigte Status **102 / Aktiv**, **Anwesend**, die
zugehörige Referenzzone und eine verlässliche Standortbewertung. Kontodaten,
Entfernung und Aufenthaltsdauer waren als verfügbar gekennzeichnet. Die
Entfernung entsprach einer unabhängig aus den Koordinaten berechneten Luftlinie
zum Referenzmittelpunkt; die native Anzeige formatierte sie mit einer
Nachkommastelle und der Einheit Meter.

Die Aufenthaltsdauer stieg beim nächsten automatischen lokalen Auswertungslauf
um genau **15 Sekunden**. Das entsprach exakt dem Abstand der Variablenupdates;
Anwesenheit und Entfernung blieben erhalten. Der Aufenthaltsbeginn entsprach
dem Zeitstempel der akzeptierten GPS-Position, nicht dem Zeitpunkt des
Übernehmens der Konfiguration. Die Dauer wurde nativ als Stunden:Minuten:Sekunden
angezeigt. Damit sind Cache-Auswertung und fortlaufende Dauer auf dem echten
System bestätigt.

Die Prüfung verwendete ausschließlich den bestehenden Kontocache. Es erfolgten
keine zusätzliche Apple-Abfrage, Anmeldung oder aktive Neuortung und keine
Änderungen am Modul- oder Helper-Code. Reale Zonenwechsel, Standortalterung,
GPS-Sprünge und ein echter Symcon-Neustart wurden in diesem MCP-Lauf nicht
ausgelöst; die vorhandenen Offline-Nachweise dafür bleiben getrennt.
Private Objekt- und Gerätekennungen, Koordinaten und Standortzeitpunkte werden
hier nicht gespeichert.

## iCloud Anwesenheit: Zonen und GPS-Stabilisierung (2026-10-23)

Umgesetzt ist das neue Modul `iCloud Presence`, Alias **iCloud Anwesenheit**,
Präfix **ICPR**, Modul-GUID `{00438FA2-CF5C-45B0-AFF0-A5A5CE66595E}`. Eine Instanz
wertet ein Gerät direkt am bestehenden Kontosplitter aus. Der Geräte-Konfigurator
erhält eine eigene Liste **Anwesenheitsauswertungen** mit kontogebundenem
Instanzabgleich und Erstellungsvorgaben. Geräteinstanz und Karte sind dafür
nicht erforderlich. Die Architekturentscheidung steht in
[ADR 0007](docs/adr/0007-presence-and-geofencing.md).

Eine bis 64 Kreiszonen besitzen stabile IDs, Namen, Mittelpunkt und Radius;
genau eine Referenzzone wird markiert. `Presence` verwendet die bereits zentral
vorhandene native Integer-Klartextdarstellung: **-1 Unbekannt**, **0 Abwesend**,
**1 Anwesend**. Andere bestätigte Zonen zählen als abwesend von der Referenzzone.
Zusätzlich entstehen aktuelle Zone/ID, Bewertungsgrund, Luftlinienentfernung
zum Referenzmittelpunkt, Aufenthaltsbeginn und -dauer sowie eigene
Verfügbarkeits- und Bestätigungskennzeichnungen. Alle Variablen sind lesend.

Die generische Berechnung liegt ausschließlich im neuen zentralen
`GeofenceHelper`. Er verarbeitet Genauigkeitsradius, Altersgrenze, Hysterese,
zeitlich getrennte Bestätigungen und unplausible Sprünge. Referenztreffer haben
bei Überlappungen Vorrang. Grenzfälle halten bekannte Zustände, ohne vorherige
Bestätigung bleiben sie unbekannt. Fehlende, alte oder unbrauchbare Positionen
ergeben Unbekannt; ein alter wiederholter Cachetreffer darf einen unterbrochenen
Aufenthalt nicht neu bestätigen. Die Dauer zählt beobachtete Zeit innerhalb einer
bestätigten Zone. Zustände sind persistent und durch eine Identität aus Konto,
Gerät, Zonen und Einstellungen getrennt.

Vorgaben: Standortalter 900 s, maximale Unsicherheit 100 m, Grenzpuffer 25 m,
Bestätigungszeit 30 s, plausible Geschwindigkeit 300 km/h. Der lokale
15-Sekunden-Timer liest nur `GetDevices` aus dem Kontocache und prüft Alter und
Dauer. Dieselbe Position bestätigt keinen Wechsel. Der tatsächliche
Kontoabrufrhythmus und die von Apple gelieferten Zeitstempel begrenzen die
Reaktionszeit. Es gibt keine neue Anmeldung, aktive Neuortung, Adressabfrage
oder weitere Netzwerkverbindung.

### Prüfung

Unter PHP 8.5.10 bestanden die vollständige zentrale Helpersuite und die
vollständige Modulsuite einschließlich der bisherigen Karten-/Browser-Verträge.
Die neuen Regressionen prüfen Geometrie einschließlich Datumsgrenze, Ein- und
Austritt mit Bestätigung, Randfälle und Rückkehr, Genauigkeits-/Altersfehler,
Sprünge, doppelte und rückläufige Zeitstempel, serialisierten Neustartzustand,
Beobachtungslücken, lokale Timer, Variablen-IDs, Konto-/Konfigurationswechsel,
entfernte Geräte, abgeschaltete Auswertung und ungültige Konfiguration.
Formularaktionen und beide Konfigurator-Erstellungsvorgaben werden durch die
dokumentbasierten SDK-Vertragsprüfungen erfasst.

Die Manifest- und Bundle-Prüfung bestätigt den neuen Helper bytegleich mit
seiner kanonischen Quelle und ohne weitere Laufzeitabhängigkeiten. Das gesamte
abonnierte Consumer-Paket wurde über den vorhandenen Bundle-Generator erzeugt
und in isolierten Kopien geprüft. Zusätzlich bestand der bisherige committete
Modulstand mit neuer Subscription und neuem Paket: Der vorgelagerte Sync kann
somit vor der Veröffentlichung des Anwesenheitsmoduls geprüft werden.
Im normalen Arbeitsbaum wurden für diese Offline-Prüfung keine Vendor-Dateien
ausgetauscht. Zu diesem Zeitpunkt war noch nichts installiert oder veröffentlicht.
Der anschließende MCP-Nachweis am installierten Stand steht im vorangehenden
Abschnitt. Ein echter Symcon-Neustart und die native Formularbedienung wurden
auch dort nicht geprüft.

### Verteilung und Übergabe

Das neue Modul benötigt `GeofenceHelper` ab 1.0.0. Der Benutzer hat das komplette
Modulupdate inzwischen bestätigt; der MCP-Nachweis bestätigt den geladenen
Helper am laufenden System. Die folgende Reihenfolge dokumentiert die erstmalige
Einbindung der neuen Abhängigkeit. Lokale Testkopien verbleiben nicht im
normalen Arbeitsbaum, damit sie den Pull des regulären Pakets nicht blockieren.

1. In SymconiCloud zunächst ausschließlich `.helper-sync.json` über GitHub Desktop
   committen und nach `dev` pushen. Die laufenden Module nutzen den neuen Helper
   dabei noch nicht. Die strenge Integritätsprüfung meldet bis zum Eingang des
   Pakets die noch fehlende abonnierte Datei; sie wird nicht gelockert.
2. Die zentrale GeofenceHelper-Ergänzung nach dem vorhandenen Benutzerworkflow
   freigeben. Automatische Versionierung und Verteilung laufen auf
   `Symcon_ModuleHelper/main`; der Sync liest die nun vorhandene Subscription
   aus `SymconiCloud/dev` und liefert das vollständige Paket per Helper-PR.
   Falls der zentrale Stand vorher freigegeben wurde, den vorhandenen Workflow
   **Sync Helpers to Consumers** danach für `GeofenceHelper` erneut starten.
3. Nach erfolgreicher Integration des regulären Pakets in `dev` dieses in GitHub
   Desktop pullen. Die hier geprüften fachlichen Änderungen liegen außerhalb von
   `libs/helper` und überschneiden sich nicht mit dem Paket-Pull. Den tatsächlichen
   Integrationsstand mit `php tests/run.php` erneut prüfen und erst dann die
   übrigen Moduländerungen committen, pushen und zum Test installieren.

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
## Anwesenheitswidgets: IPSView-Live-Updates korrigiert (2026-10-23)

Der echte IPSView-Webclient bettet eine HTMLBox als opaque Daten-URL ein.
Der bisherige direkte Cache-POST mit eigenem Token-Header erforderte dadurch
eine CORS-Vorabfrage. Der Symcon-Webserver beantwortete OPTIONS ohne die
erforderlichen CORS-Header; die direkte HTTP-Ausgabe konnte dagegen aktualisieren.

IPSViewPresence enthält jetzt einen stabilen Loader zu einer echten HTTP(S)-
Widgetseite auf dem Symcon-Server. Deren Cache-POST ist gleichursprünglich.
Die öffentliche GET-Seite enthält weder Gerätedaten noch Zugriffstoken. Der
Loader übergibt den instanzbezogenen Token ausschließlich im URL-Fragment;
die Widgetseite übernimmt ihn in den Arbeitsspeicher und entfernt das Fragment
aus dem Verlauf. Datenabrufe bleiben authentifiziert und lesen nur den Cache.
Deaktivierte Ausgabe, falsche Tokens und abweichende Pfade werden abgewiesen.

WoIst und Anwesenheit verwenden denselben Loader unter libs/IPSViewFrame.php
und libs/visualization. Die vorhandenen zentralen Helper liefern JSON-Kodierung,
HTTP-Antworten, Seitenerstellung, Ursprungsermittlung und Mausrad-/Frame-Routing.
Der Loader bleibt wegen tatsächlicher Nutzung nur in SymconiCloud librarylokal;
Symcon_ModuleHelper und regulär verteilte Dateien unter libs/helper sind unverändert.

MCP-Nachweis auf dem freigegebenen Testsystem mit vorübergehendem Prüfstand:
temporäre echte IPSView-Webclient-Ansicht, bestehende Anwesenheitsinstanz mit
zwei Geräten und leerem Serveradressfeld, 320 × 230 Pixel. In Hell und Dunkel
aktualisierte sich die Aufenthaltsdauer mehrfach, während Geräteauswahl und
Scrollposition von 101 Pixeln erhalten blieben. Die HTML-Variable behielt sowohl
ihren Inhalt als auch ihren Aktualisierungszeitstempel. Kein Transporthinweis.
Echte Mausradaktionen bewegten die Liste in beiden Stilen von 101 auf 0 und
zurück auf 101 Pixel. Wegen eines Actionability-Fehlers der Browsersteuerung
erfolgte die Geräteauswahl per echter Tab-/Leertastenbedienung; dies wird nicht
als erfolgreicher Zeigerklick ausgegeben. Die native IPSView-App ist mit der
verfügbaren Steuerung nicht direkt erreichbar und bleibt als Praxisnachweis offen.

Temporäre Ansicht, HTML-Variable und zusätzliche Laufzeitdateien wurden entfernt,
die vorherigen Laufzeitquellen vollständig zurückgespielt. Vollständige ursprüngliche
Konfiguration, Kontoverbindung und alle bisherigen Variablen-IDs sind wiederhergestellt;
Status 102. Keine Apple-Anmeldung oder zusätzlichen Apple-Abfragen. Der korrigierte
Stand wird regulär über das Modulupdate installiert; anschließend Konfiguration
einmal übernehmen, damit vorhandene HTML-Variablen den neuen Loader erhalten.

Nachweise: Regression zunächst am fehlenden HTTP-Loader reproduziert; vollständige
Offline-Suite einschließlich WoIst, gemeinsamer Loader, private HTTP-Shell,
Token-Fragment, fehlerhafte Zugriffe und Auswahl-/Scroll-Erhalt bestanden.
Struktur-/Hash-/LF-, JSON- und vollständige PHP-CS-Fixer-Prüfung gemäß offizieller
Style-Action bestanden unter PHP 8.5.10 (Fixer 3.95.27). Keine Versionsanhebung,
Commits oder Pushs durch den Agenten.
## Native IPSView-HTMLBox: Basisadresse im zentralen Helper (2026-10-23)

Der nach dem ersten HTTP-Fix gemeldete Screenshot zeigt den Fehler des äußeren
Loaders, nicht den Warnhinweis eines bereits laufenden Cacheabrufs. Ein weiterer
Fall ließ sich im gemeinsamen Ursprungsermittler reproduzieren: opaque Daten-URL,
kein zugänglicher Parent, leere ancestorOrigins und leerer Referrer, aber eine
gültige vom Client eingefügte HTTP(S)-Basisadresse. document.baseURI wurde bisher
nicht berücksichtigt. Der zentrale VisualizationAssetHelper-Baustein prüft diese
Adresse jetzt nach dem eigenen HTTP-Ursprung und vor Parent-/Referrer-Fallbacks.
Eigene HTTP-Adressen behalten Vorrang; unsichere Schemata und Zugangsdaten werden
weiterhin abgewiesen. Der äußere Anwesenheitsloader zeigt bei Startfehlern einen
Konfigurationshinweis statt der irreführenden Ankündigung eines erneuten Abrufs.

Die Ressourcen-/Metadatenautomatisierung ordnet die Änderung korrekt dem
VisualizationAssetHelper zu. Vorhandenes Abonnement und Asset-Deklaration genügen;
der Paketvertrag prüft http-origin.js nun zusätzlich als transitive Ressource des
IPSViewHTMLPageHelper. Das vollständige Paket wurde mit der regulären Metadaten-
und Bundle-Logik ausschließlich in einer temporären Kopie erzeugt. Manifest,
Verteilungstests, beide vollständigen PHP-Suiten, Basisadressen-Regression und
der Anwesenheitsloader mit ausschließlich einer Basisadresse bestanden. Keine
lokalen Testkopien oder Hashänderungen verbleiben unter libs/helper.

MCP: vorübergehender Prüfstand auf dem freigegebenen Testsystem, echte temporäre
IPSView-Webclient-Ansicht mit leerem Serveradressfeld. Die HTMLBox enthielt ein
HTTP-Basiselement; die Widgetseite wurde geladen und die Aufenthaltsdauer änderte
sich bei erhaltenem Gerät und 101 Pixeln Listenscroll. Kein Warnhinweis. Echte
Mausradaktionen bestätigten 101 auf 0 und zurück auf 101 Pixel. Geräteauswahl per
Tab-/Leertaste wegen der bereits bekannten Eingabegrenze der Browsersteuerung.
Dieser Webclient-Nachweis und die simulierte native Basisadressen-Umgebung ersetzen
keine direkte Abnahme der nativen IPSView-App; diese ist weiterhin offen.

Prüfansicht, neue HTML-Variable und zusätzliche Laufzeitdateien wurden entfernt.
Ursprüngliche Konfiguration, Kontoverbindung und alle bisherigen Variablen-IDs
sind wiederhergestellt (Status 102), ebenso die gesicherten Modul-/Helperquellen.
Keine zusätzliche Apple-Abfrage. Erst den zentralen Helper regulär freigeben und
verteilen, das Bundle in SymconiCloud integrieren und installieren; danach die
Instanzkonfiguration übernehmen, damit gespeicherte Loader das neue Script enthalten.

## Geraetebilder vom Konsolenrechner (2026-10-24)

Anwesenheit bietet je Geraetezeile zusaetzlich zur bisherigen Medien-ID eine
Bildquelle (pictureSource: media/upload) und SelectFile (pictureUpload).
Der native Konsolendialog liest die Datei auf dem Rechner, auf dem die Konsole
geoeffnet ist; gespeichert werden Base64-Dateiinhalte in der Devices-Property,
keine Client- oder Serverpfade. Die Bildquelle bleibt ohne neue Felder auf media,
so dass bestehende Konfigurationen und Bilder kompatibel bleiben. Die Uploadspalte
ist gespeichert, aber in der Tabelle verborgen; der explizite Zeilendialog zeigt
die Dateiauswahl und dieselben dynamischen Geraeteoptionen wie bisher.

Medien und Uploads nutzen dieselbe librarylokale Rasterbildpruefung: PNG, JPEG,
GIF oder WebP, maximal 128 KiB und 1024 x 1024 Pixel. Kein SVG oder Nachladen
externer URLs; ungueltige/leere Inhalte werden als Initialen dargestellt.
Bestehende zentrale Layout-, Stil-, Interaktions- und Transporthelper bleiben
unveraendert. Keine neue zentrale Abstraktion: der konkrete Uploadadapter hat
nur eine tatsaechlich verwendende Library. Kein Helper-Update erforderlich.

Nachweise: vollstaendige Offline-Suite, Struktur/Hashes/LF, JSON und PHP-CS-Fixer
3.95.27 unter PHP 8.5.10 bestanden. Tests fuer lokalen Dateiinhalt, Groessen- und
Dimensionsgrenzen, fehlerhafte Inhalte, Quellwechsel, private HTTP-Shell,
authentifizierte IPSView-Bilder, Variablen-IDs und unveraenderte GPS-Zustaende.
Der UI-Vertrag bestaetigt Auswahl und Scrollposition auch beim Bildwechsel durch
ein Datenupdate. Keine Installation oder Konfigurationsaenderung auf einem
laufenden System; Dateidialog und neue Bilder sind nach dem Modulupdate noch in
der echten Konsole und IPSView-App abzunehmen. Die zuvor vom Benutzer bestaetigte
Mausradfunktion bleibt ein separater Praxisnachweis, kein neuer Upload-Livetest.

## Bild-Upload: konkrete Ablehnungsgruende im Zeilendialog (2026-10-24)

Der Benutzer meldete einen gespeicherten Upload mit angezeigten rund 203 kB,
waehrend das Widget weiterhin Initialen zeigte. Die bestehende Grenze von
128 KiB wurde ueberschritten; der Datei-Upload ist von der nachgelagerten
Rasterbildpruefung zu unterscheiden. Die Grenze wird nicht stillschweigend
angehoben. Es gibt keine automatische Bildkomprimierung oder Skalierung.

Der Geraetedialog zeigt den Groessen-/Formathinweis jetzt direkt neben der
Auswahl und besitzt ein eigenes Fehlerfeld. Datei- und Bildquellenwechsel
validieren den aktuellen, noch nicht gespeicherten Inhalt: Dateigroesse,
unterstuetztes Rasterformat und Abmessungen haben getrennte, uebersetzte
Korrekturhinweise. Eine gueltige Datei, das Entfernen der Datei oder der Wechsel
zum Symcon-Medium loescht den alten Hinweis. Anzeige und Formular benutzen
dieselbe librarylokale Pruefung; Konfiguration, Variablen und GPS-Auswertung
werden durch die Formularvalidierung nicht veraendert.

Regression mit synthetischem 203100-Byte-Inhalt und uebergrossen Abmessungen;
vollstaendige Offline-Suite, JSON, Struktur/Hash/LF und PHP-CS-Fixer bestanden.
Keine Installation auf einem laufenden System. Der neue Inlinehinweis ist noch
in der aktualisierten echten Konsole abzunehmen.

## Automatische Bildaufbereitung, zentraler Helper (2026-10-24)

Auf ausdruecklichen Benutzerwunsch wird RasterImageHelper schon mit dieser
ersten nutzenden Library zentral umgesetzt. Diese bewusste Ausnahme ist im
AGENTS.md des Helper-Repositories dokumentiert; die allgemeine Regel, erst bei
zwei tatsaechlich nutzenden Libraries zu zentralisieren, bleibt bestehen.

Lokale PNG/JPEG/GIF/WebP-Uploads bis 10 MiB, 8192 Pixel pro Seite und 16 Millionen
Pixel werden auf maximal 512 Pixel Kantenlaenge und 128 KiB aufbereitet. Keine
Hochskalierung oder Beschneidung; Transparenz bleibt erhalten. WebP wird bevorzugt,
JPEG/PNG dienen als Fallbacks. JPEG-EXIF-Ausrichtung wird bei verfuegbarer EXIF-
Erweiterung angewendet, Metadaten werden bei Neucodierung nicht uebernommen.
Umzuwandelnde Animationen verwenden das erste Bild. Unveraendert passende Bilder
bleiben erhalten. GD ist fuer notwendige Umwandlungen erforderlich.

Die Formularaktion ersetzt ausschliesslich den ungespeicherten SelectFile-Wert
durch das kompakte Ergebnis. Erst OK und Uebernehmen speichern die Geraetezeile.
Bereits gespeicherte groessere Uploads funktionieren ebenfalls: Die Ausgabe
verwendet den zentral aufbereiteten Inhalt. StatusWidgetPictures cached maximal
64 Ergebnisse anhand des Inhalts-Hashes und wird beim Anwenden der Konfiguration
geleert. Bilder werden bei Datenupdates nicht erneut konvertiert. Quelle, Zonen,
GPS-Zustaende und Variablen-IDs bleiben vom Formularpfad unberuehrt.

RasterImageHelper ist als neue Subscription eingetragen; keine Abhaengigkeiten
oder weiteren Ressourcen. Das vollstaendige kanonische Paket wurde mit der echten
Metadaten- und Bundle-Logik in einer isolierten temporaeren Kopie aufgebaut.
Beide kompletten PHP-Suiten, Paket-/Hash-/Ressourcenpruefung, Verteilungs-,
Metadaten- und Auto-Merge-Vertraege sowie Stilpruefungen bestanden unter PHP 8.5.10
mit GD/EXIF. Keine Testkopien verbleiben unter libs/helper; der neue Helper muss
vor der Installation ueber die regulaere Verteilung eintreffen.

MCP: Reiner In-Memory-Rechentest des zentralen Helpers auf dem angeschlossenen
Symcon mit PHP 8.5.8 und GD/EXIF: synthetisches transparentes Bild von 209131 Byte
auf 512 x 256 Pixel und 592 Byte WebP reduziert; Alpha erhalten, erneute
Aufbereitung unveraendert. Keine Quellen installiert, keine Instanzkonfiguration
geaendert. Der echte Dateidialog muss nach dem regulaeren Update abgenommen werden.

Rollout: Zuerst die neue Subscription in SymconiCloud/dev bekannt machen, danach
den zentralen Helper freigeben und automatisch verteilen. Das komplette Modul
erst nach dem eingegangenen Helper-Bundle installieren. Bis zur Verteilung ist
der neue Helper im normalen lokalen Consumer-Arbeitsbaum noch nicht vorhanden;
die Verhaltensnachweise stammen ausdruecklich aus dem isolierten Gesamtpaket.

## Praxisabnahme des automatischen Bild-Uploads (2026-10-24)

Der Benutzer hat den echten Bild-Upload nach dem Update erfolgreich geprueft
und mit "Funktioniert perfekt" bestaetigt. Das Auswaehlen eines Bildes vom
Konsolenrechner mit anschliessender automatischer Verkleinerung und Komprimierung
funktioniert im praktischen Ablauf. Damit ist die zuvor offene Praxisabnahme
des Dateidialogs und der automatischen Bildaufbereitung abgeschlossen.

Nachweisquelle ist die ausdrueckliche Benutzerbestaetigung; fuer diese Abnahme
wurde kein neuer MCP-Lauf ausgefuehrt. Die zuvor dokumentierten Offline- und
MCP-Rechentests bleiben separate technische Nachweise. Aus der Bestaetigung
werden keine zusaetzlichen Aussagen zu einzelnen Dateiformaten, Grenzfaellen
oder Clients abgeleitet.

## Zusaetzliche Anwesenheitsvariante WidgetCompact (2026-10-24)

Widget bleibt die bisherige ausfuehrliche Ausgabe. WidgetCompact zeigt je Geraet
nur Bild/Initialen, Namen und Zone; bei ungueltigen Standorten weiterhin
Unbekannt. Entfernung, Aufenthaltsdauer, Diagnosetext und die zweite Zaehlerzeile
der Gesamtanwesenheit entfallen. Kompakte Karten haben kleinere Abstaende und
Bilder (maximal 8 bzw. 36 Pixel) sowie eine schmale Gesamtanwesenheits-Kopfzeile.

Die native Instanz bleibt eine Kachel und waehlt im bestehenden Designer mit
TileWidgetVariant zwischen Widget (Default) und WidgetCompact. IPSView erhaelt
zusaetzlich IPSViewPresenceCompact; die bestehende IPSViewPresence bleibt mit
ihrer bisherigen ID und Ausgabe erhalten. Beide Varianten koennen gleichzeitig
eingebunden werden. Beide verwenden die vorhandenen Bilder und die jeweiligen
Kachel-/IPSView-Farb-, Schrift- und Hell-/Dunkelvorgaben.

Die neue Variante nutzt /compact/page als private HTTP-Shell und /compact als
authentifizierten Cache-Endpunkt unter demselben Instanzhook. Keine Geraetedaten
oder Tokens in der oeffentlichen Shell. Die Authentifizierungs-, Pfad- und
Abschaltregeln entsprechen der normalen Variante. HTML-Regenerierung rendert
beide Varianten gesondert mit den zentralen Variablen- und Aufbewahrungshelpern.
Kein zusaetzlicher zentraler Helper und kein Helper-Update erforderlich.

Regressionen pruefen neue Ausgabe neben unveraenderter alter Variable, getrennte
Routen, Regenerierung, Variablen-IDs, Abschalten und Datenschutz. Die UI-Vertraege
pruefen Namen/Zonen ohne Details, Unbekannt sowie Auswahl- und Scroll-Erhalt bei
Updates. Vollstaendige Offline-Suite, Struktur-/Hash-/LF-, JSON- und Style-
Pruefungen unter PHP 8.5.10 mit GD/EXIF bestanden. Keine Aenderung am
angeschlossenen System und keine Apple-Anfrage.
Die Browser-Vorschau ist offen: Die automatische Befehlspruefung lehnte den
lokalen Vorschauserver ab; die Browser-Sicherheitsrichtlinie verweigerte die
Daten-URL-Alternative (nur HTTP/HTTPS erlaubt). Keine Umgehung dieser Sperren.
Visuelle Abnahme, Hell-/Dunkelmodus und echte Mausradbedienung beider Varianten
im tatsaechlichen Kachel-/IPSView-Host bleiben nach dem Update zu pruefen.

## Farbkarte im IPSView-Designer der Anwesenheit (2026-10-24)

Unter IPSView-Designer liegt die neue aufklappbare Farbzuordnung fuer Widget und
WidgetCompact. Das nummerierte Beispielbild zeigt zwoelf technische Rollen:
ViewBackground, ControlBackground, LabelText, TextInactive, Positive, Information,
Warning, Border, Accent, AccentSoft, ControlActiveBackground und ShadowColor.
Eine uebersetzte Legende erklaert die Bereiche und die abgeleitete Auswahlfarbe
(18 Prozent Akzentdeckkraft), die 13-Prozent-Statusmischung der Kopfzeile sowie
die allgemeinen Text- und nicht verwendeten Popup-/Diagramm-/Critical-Farben.

Die Grafik nutzt feste schematische Beispielfarben und ist ausdruecklich keine
Live-Vorschau ungespeicherter Einstellungen. Die Rollen gelten fuer eigene Farben
und Hell-/Dunkelstile gleichermassen. Keine privaten Geraetedaten oder Fotos.
Das native Image-Feld nutzt 100 Prozent Breite ohne feste Panelbreite.

Die modulbezogene SVG-Quelle und die vorcodierte Data-URI liegen unter der
Anwesenheitsvisualisierung. Die Bildressource wurde mit dem vorhandenen zentralen
SVGPreviewHelper::dataUri erzeugt; Laden erfolgt ueber VisualizationAssetHelper.
Damit braucht die feste Grafik keinen neuen Laufzeithelper und keine neue
Subscription. Keine lokale Kopie der SVG-Helperfunktionen. Bei Aenderungen an
color-guide.svg wird color-guide.image mit dem zentralen SVGPreviewHelper neu
erzeugt; der Regressionstest verlangt bytegleiche Quelle und decodierte Grafik.

Nachweise: PNG-Render aus dem SVG visuell kontrolliert. Formular-/Bildvertrag
prueft responsive Einbindung, XML-Gueltigkeit, beide Varianten, Farbrollen,
Uebersetzungen und das Fehlen aktiver oder externer Ressourcen. Die Farbkarte
erscheint nur im Designer; Live-Widgets, Transporte und Bedienung bleiben
unveraendert. Vollstaendige Offline-Suite, Struktur-/Hash-/LF-, JSON- und
Stilpruefungen unter PHP 8.5.10 bestanden. Die Anzeige im echten Konsolenformular ist nach dem Update noch
abzunehmen; es wurde kein angeschlossenes System veraendert.

### Deutsche Designerbezeichnungen in Bild und Legende (2026-10-24)

Die Farbkarte verwendet jetzt die tatsaechlichen deutschen Feldnamen des zentralen
IPSViewStyleHelper-Katalogs, unter anderem View-Hintergrund, Bedienelement-
Hintergrund, Label-Schrift, Inaktive Schrift, Positiver Status, Informationsfarbe,
Warnstatus, Rahmenfarbe, Akzentfarbe, Aktiver Bedienelement-Hintergrund und
Schattenfarbe. Technische Rollennamen stehen als kleinere Zusatzinformation
darunter. Die abgeleitete Auswahlflaeche ist kein eigenes Designerfarbfeld und
wird deshalb entsprechend als Ableitung aus Akzentfarbe erklaert.

Die Textlegende liest die Feldnamen zur Laufzeit mit TranslateHelperText aus
demselben zentralen Katalog wie der Designer. Die Sprachwahl des vorhandenen
zentralen Helpers waehlt die deutsche oder englische vorbereitete SVG-Bildressource.
Tests gleichen Bild und Textlegende mit den zentralen deutschen Bezeichnungen ab
und verlangen fuer beide Sprachen bytegleiche SVG-Quelle und Data-URI. Die
deutsche Grafik wurde als PNG gerendert und visuell auf Lesbarkeit kontrolliert.
Keine neue Subscription oder Aenderung eines zentralen Helpers notwendig.

### Praxisabnahme der deutschen Farbzuordnung (2026-10-24)

Der Benutzer hat die deutsche Farbzuordnung im echten IPSView-Designer nach dem
Update erfolgreich geprueft und mit "Funktioniert" bestaetigt. Damit ist die
offene Formularabnahme fuer Bild und Legende mit den deutschen Feldbezeichnungen
abgeschlossen. Die technischen Rollennamen bleiben ergaenzende Informationen.
Nachweis ist die ausdrueckliche Benutzerbestaetigung, kein neuer MCP-Lauf.

Die daraus abgeleitete allgemeine Vorgabe wurde in SymconDevelopment unter
standards/CONFIGURATION_FORM.md verankert: Jeder Kachel-/IPSView-Designer mit
Farboptionen erhaelt eine modulbezogene Farbkarte samt Legende mit den exakt
verwendeten lokalisierten Designerbezeichnungen. Die Ueberarbeitung der anderen
Libraries folgt separat; deren Umsetzung oder Abnahme wird hier nicht behauptet.

## WoIst: modulbezogene Farbzuordnung im IPSView-Designer (2026-10-24)

WoIst besitzt eine eigene Kartenillustration mit Bedienleiste, Hinweisbereich,
Vektorkartenausschnitt, Zoomtasten, Markern, Genauigkeitskreis, Popup und drei
Geraetekarten (aktuell, ausgewaehlt/veraltet, ohne Position). Keine unveraenderte
Anwesenheitsgrafik: Das Motiv und die 25 Zuordnungen folgen dem WoIst-HTML/CSS.
Die schematischen Beispielfarben sind ausdruecklich keine Live-Vorschau.

Die Legende trennt IPSView-Stilrollen, CurrentColor/StaleColor und die sechs
OpenFreeMap-Palettenfarben. Zentrale deutsche/englische Feldnamen kommen aus
IPSViewStyleHelper und OpenFreeMapHelper, die beiden Positionsfarbnamen aus der
WoIst-Lokalisierung. Hinweise erklaeren die Uebernahme des Kachelprofils,
bedingte benutzerdefinierte Vektorkartenfarben, fest weisse Markerränder,
Akzentfarbe nur fuer die ausgewaehlte Geraetekarte und den Genauigkeitskreis
mit aktueller Positionsfarbe und 8 Prozent Fuelldeckkraft.

Die gemeinsame Formularstruktur fuer Farbguide-Panels liegt librarylokal in
DesignerColorGuide und wird von Anwesenheit und WoIst verwendet. Die konkreten
Illustrationen und Zuordnungen bleiben getrennt. Wiederverwendung der vorhandenen
zentralen Uebersetzungs- und Asset-Helper; die Data-URIs werden beim Erzeugen der
Bildressourcen mit SVGPreviewHelper::dataUri aufgebaut. Keine neue Subscription,
kein zentraler Helper geaendert und keine Testkopien unter libs/helper.

Reproduzierbarer Aufbau der vier SVG-/Bilddateien (de/en):
php tests/generate-woist-color-guide.php ../Symcon_ModuleHelper/src/SVGPreviewHelper.php
Der Generator braucht den zentralen SVG-Helper nur zur Entwicklungszeit.

Nachweise: deutsche Grafik als PNG gerendert und visuell kontrolliert.
Vertragstests gleichen alle 25 Beschriftungen in beiden Sprachen mit den
massgeblichen Katalogen ab, pruefen SVG/Data-URI-Identitaet, passive lokale
Ressourcen, die tatsaechlichen HTML-/CSS-Zuordnungen und die unveraenderten
festen Markerraender/Genauigkeitsdeckkraft. Die bestehende Anwesenheits-Farbkarte
bleibt durch ihre Regressionen abgesichert. Vollstaendige Offline-Suite,
Struktur-/Hash-/LF-, JSON- und Stilpruefungen unter PHP 8.5.10 bestanden.
Keine Aenderung auf einem
angeschlossenen Symcon-System, keine Apple- oder Kartendienstabfrage.
Die Anzeige im echten WoIst-Konsolenformular bleibt nach dem Update abzunehmen.

### Erfolgreiche Praxisabnahme der WoIst-Farbzuordnung (2026-10-24)

Der Benutzer bestaetigt nach dem Update mit "Funktioniert" die erfolgreiche
Pruefung der Farbzuordnung im echten IPSView-Designer der WoIst-Karte.
Die bereitgestellten Screenshots zeigen das geoeffnete Panel
"Farbzuordnung: WoIst-Karte" mit der kartenspezifischen Illustration fuer
Bedienleiste, Karte, Popup und Geraeteliste. Die nummerierten Zuordnungen
verwenden die deutschen Designer-Bezeichnungen; technische Feldnamen werden
ergaenzend angezeigt.

Sichtbar sind sowohl die IPSView-Stilrollen als auch der Abschnitt
"Karteneinstellungen und OpenFreeMap" mit Positions- und Kartenfarben,
Erlaeuterungen zu den schematischen Farben und der ergaenzenden Textlegende.
Damit ist die zuvor offene Anzeigepruefung im echten Konsolenformular
abgeschlossen.

Nachweisquelle sind die Benutzerbestaetigung und die beiden bereitgestellten
Screenshots. Fuer diesen Dokumentationsschritt wurde kein neuer MCP-Lauf
ausgefuehrt; eine erneute Laufzeitpruefung einzelner Farbaenderungen wird damit
nicht behauptet.

### Vereinfachte WoIst-Farbzuordnung (2026-10-24)

Auf Benutzerwunsch entfallen die OpenFreeMap-Markierungen 20 bis 25 samt
Bild- und Textlegendeneintraegen fuer Land, Wasser, Gruenflaechen, Strassen,
Gebaeude und Kartenbeschriftung. Die Farbzuordnung erklaert weiterhin die
IPSView-Stilrollen und die Positionsfarben mit den unveraenderten Nummern
1 bis 19. Die schematische Karte bleibt erhalten; die Grafik wird ohne die
entfallenen Legendenzeilen kuerzer.

Die OpenFreeMap-Paletten bleiben im Formular fuer Hell und Dunkel vollstaendig
beschriftet und einstellbar. Keine Aenderung an Kartenressourcen zur Laufzeit,
Farb-Properties, gespeicherten Einstellungen oder zentralen Helpern.
Der bestehende Generator verwendet weiterhin SVGPreviewHelper zum Erzeugen
der deutschen und englischen Bildressourcen.

Nachweise: beide neu erzeugten Sprachfassungen gerendert und visuell geprueft.
Die angepassten Vertragstests bestaetigen exakt die Nummern 1 bis 19, das Fehlen
der sechs OpenFreeMap-Legendenrollen und den Erhalt saemtlicher heller und
dunkler OpenFreeMap-Farbfelder in beiden Designern. Die vollstaendige
Offline-Suite einschliesslich UI-/Transport-, Struktur-/Hash-/LF- und
Syntaxpruefungen sowie JSON-Check und php-cs-fixer im Pruefmodus sind unter
PHP 8.5.10 erfolgreich. Kein neuer MCP- oder nativer Client-Test fuer diese
reine Designer-Ressourcenaenderung.

### Praxisabnahme der vereinfachten Farbzuordnung (2026-10-24)

Der Benutzer bestaetigt mit "Funktioniert" die erfolgreiche Pruefung der
vereinfachten Farbzuordnung im IPSView-Designer der WoIst-Karte. Damit ist die
reduzierte Darstellung mit den Zuordnungen 1 bis 19 ohne die OpenFreeMap-
Markierungen 20 bis 25 und deren Legendeneintraege in der Praxis abgenommen.
Die schematische Karte und die OpenFreeMap-Farbeinstellungen bleiben erhalten.

Nachweisquelle ist die ausdrueckliche Benutzerbestaetigung. Dieser Schritt
dokumentiert die Praxisabnahme; es wurde kein neuer MCP- oder Client-Test
ausgefuehrt. Die zuvor dokumentierten Offline-Nachweise bleiben davon getrennt.

## Direkter Variantenumschalter in der Anwesenheitskachel (2026-10-24)

Die native Kachel bietet Widget und WidgetCompact als lokale Schaltflaechen.
Der Designerwert TileWidgetVariant ist weiterhin die Startvorgabe. Eine manuelle
Wahl bleibt ueber SDK-Datenupdates erhalten und wird, soweit zulaessig, als
einziger Wert im tabgebundenen sessionStorage abgelegt. Der Schluessel trennt
Instanz und Kontoverbindung; es werden keine Geraete-, Standort- oder Zugangsdaten
gespeichert. Ungueltige oder verweigerte Speicherung verhindert die Bedienung
nicht. Getrennte IPSView-Ausgaben bleiben unveraendert und ohne Umschalter.

Native Bootstrap- und Push-Daten enthalten auch bei kompakter Startvorgabe die
vollstaendigen Anzeigedaten und Designgroessen. Die kompakte Darstellung blendet
Details lokal aus und begrenzt Bildgroesse/Abstaende; Widget stellt die normalen
Angaben wieder her. Bestehende Geraetezeilen werden weiterverwendet, die Auswahl
bleibt ueber ihre ID erhalten. Beide Varianten merken sich ihren jeweiligen
Scrollwert; beim ersten Wechsel gilt der bisherige Wert bis zur tatsaechlichen
Scrollgrenze der kuerzeren Darstellung.

Vorhandene zentrale Theme-, HTML-Seiten-, Responsive- und Interaktionshelper
bleiben eingebunden. Die Umschaltlogik ist modulspezifisch in dieser Library;
der vorhandene OpenFreeMap-Speichervertrag ist an Kartenansichten gebunden und
wird nicht fuer Varianten zweckentfremdet. Keine zentralen Helper-Testkopien.

Der Benutzer hat den Wunsch nach zwei benannten Darstellungen direkt im nativen
Auswahldialog an Symcon gesendet. Der zentrale Standard VARIABLE_PRESENTATION
und dessen Verweis in SymconDevelopment/AGENTS.md halten fest: Bei Zustimmung
und Umsetzung durch Symcon die Zwischenloesung anhand des dann verfuegbaren
SDKs neu pruefen und kompatibel auf die native Variantenwahl umstellen.

Nachweise: der neue Node-Regressionsfall scheiterte vor der Umsetzung am
fehlenden Umschalter und besteht danach. Geprueft sind beide Richtungen,
Zeilenidentitaet/Geraeteauswahl, getrennte Scrollwerte, unveraenderte lokale
Wahl bei SDK-Push, Wiederherstellung der Details und Designgroessen,
tablokale Speicherung sowie ungueltiger und verweigerter Speicher.
PHP-Vertraege bestaetigen vollstaendige native Daten auch bei kompakter
Startvorgabe, die Instanz-/Kontotrennung und unveraenderte IPSView-Varianten.
Vollstaendige Offline-Suite mit Struktur-/Hash-/LF-, JSON-, Syntax- und
JavaScript-Pruefungen sowie php-cs-fixer im Pruefmodus unter PHP 8.5.10 bestanden.
Der neue Stand wurde nicht auf ein Live-System installiert. Praxisabnahme
des Umschalters samt Mausradbedienung in der echten nativen Kachel nach dem
Modulupdate bleibt offen; ein neuer MCP-/Browser-Lauf wird nicht behauptet.

## Anwesenheit: Variantenwahl ausschliesslich im Designer (2026-10-24)

Der Benutzer bestaetigt nach dem Update die Funktion des direkten Umschalters,
wuenscht dessen Schaltflaechen aber nicht in der Kachel selbst. Auf die gezielte
Rueckfrage wurde ausdruecklich "Entfernen; Auswahl im Kacheldesigner" gewaehlt.
Diese Entscheidung ersetzt die zuvor dokumentierte lokale Umschaltloesung.

HTML-Bedienelemente, CSS, Umschaltlogik und Sitzungsspeicherung sind entfernt.
Die native Kachel folgt wieder ausschliesslich TileWidgetVariant aus
Kacheldesigner / Layout und Inhalt. Bereits gespeicherte lokale Variantenwerte
werden nicht mehr gelesen und koennen die Designerwahl nicht ueberschreiben.
WidgetCompact behaelt seine reduzierten Inhalte und Groessen; beide getrennten
IPSView-Ausgaben und die bestehenden zentralen Helper bleiben erhalten.
Geraeteauswahl und Scrollposition bleiben bei Datenupdates abgesichert.

Der offene Wunsch nach mehreren benannten instanzspezifischen Darstellungen
im nativen Symcon-Auswahldialog bleibt in den zentralen Regeln festgehalten.
Die dort beschriebene Zwischenloesung ist entsprechend auf die ausschliessliche
Designerwahl angepasst; Neubewertung bei Zustimmung und Umsetzung durch Symcon
bleibt verbindlich. Die Benutzerbestaetigung betrifft den vorherigen Stand;
fuer die nun entfernten Schaltflaechen wird kein neuer Live-Nachweis behauptet.

Nachweise: vollstaendige Offline-Suite einschliesslich Varianten-, SDK-/Polling-,
Auswahl-/Scroll-, Struktur-/Hash-/LF- und Syntaxpruefungen unter PHP 8.5.10
bestanden. JSON-Check und php-cs-fixer im Pruefmodus ebenfalls erfolgreich.
Die angepassten Vertraege pruefen die Designerwahl ohne Toolbar oder lokale
Speicheruebersteuerung sowie die weiterhin getrennten IPSView-Ausgaben.

## Gewuenschte Erweiterungen und Abgrenzung zu OpenCalendar (2026-10-24)

Benutzerprioritaeten fuer die weitere Planung, noch keine Implementierung:

- Handy suchen / Ton abspielen wird auf jeden Fall aufgenommen, vorzugsweise
  als Aktion der vorhandenen Geraeteinstanz statt als separates Modul.
- Verloren-Modus ist ebenfalls von hohem Interesse. Tatsaechliche
  Geraeteunterstuetzung, Rueckmeldungen und die wirksamen Apple-Aktionen sind
  vor einer Umsetzung gezielt zu pruefen.
- iCloud Fotos ist ein interessanter Kandidat fuer eine eigene Erweiterung.
- iCloud Kontakte ist als Datenquelle fuer das geplante Nachrichten-Modul
  interessant; dessen konkrete Schnittstelle ist noch abzustimmen.
- iCloud Speicher steht weiter hinten und wird nur bei ausdruecklichem Bedarf
  umgesetzt. Er ist nicht als unmittelbar naechster Arbeitsschritt vorgesehen.
- iCloud Kalender wird bereits durch OpenCalendar abgedeckt und hier nicht
  nochmals implementiert.

Erinnerungen sind fachlich zu unterscheiden: Der gepruefte lokale OpenCalendar-
Stand liest fuer Apple/CalDAV VEVENT-Termine und deren VALARM-Erinnerungen.
Seine Aufgabentermine sind bei diesen Providern ganztägige Kalenderereignisse
mit eigenen X-OPENCALENDAR-Aufgabenfeldern. Native Microsoft-To-Do-Aufgaben
sind separat angebunden. Daraus folgt keine vorhandene Synchronisation mit
den eigenstaendigen Aufgaben-/Einkaufslisten der Apple-App Erinnerungen.

Apple-Erinnerungen sind deshalb eine moegliche zusaetzliche Datenquelle,
keine bereits beschlossene SymconiCloud-Erweiterung. Vorher sind Bedarf,
Zustaendigkeit im Verhaeltnis zu OpenCalendar und die technische Erreichbarkeit
der aktuellen iCloud-Erinnerungslisten zu klaeren. Die gemeinsame Anzeige
terminierter Erinnerungen in Apples Kalender-App ist kein Nachweis, dass diese
Eintraege ueber den bestehenden CalDAV-Kalenderzugang geliefert werden.

Nachweise fuer diese Einordnung: OpenCalendar/README.md (Aufgabentermine),
libs/CalDavProvider.php (VEVENT-Filter) und libs/ICalendarCodec.php
(VEVENT/VALARM). Ausschliesslich Quellpruefung und Planungsdokumentation;
keine Aenderung an OpenCalendar und kein Zugriff auf reale Apple-Daten.
