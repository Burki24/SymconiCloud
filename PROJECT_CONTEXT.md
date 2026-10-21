# Entwicklungsstand

Zielplattform: Symcon 9.0 / PHP 8.5. Entwicklungsbranch: dev. Lizenz: PolyForm Noncommercial License 1.0.0. Version 0.1/build 0/date 0 bezeichnet ausschließlich den unveröffentlichten Ausgangsstand, keinen Release.

## Prüfung

- `php tests/run.php`: Protokoll-, Konto- und Geräte-/Konfiguratorverhalten sowie Struktur und JSON.
- `php tests/validate_structure.php`: Library, offizielle Modultypen, GUID-Datenfluss, Module, Helper-Integrität, LF und CI.
- `php tests/sdk-contracts.php`: dokumentbasierte Formularaktionen (Callback, Parameter, Scope) und Configurator-Erstellungsvorgaben (bekanntes Modul, Konfigurationsobjekt, Property-Typen und tatsächliche Geräteanlage), einschließlich Negativfällen. Der Test ist in `tests/run.php` und damit im gemeinsamen CI-Testlauf enthalten. Unterstützt werden die einfachen Funktionsaufrufe, die dieses Projekt als Button-Skripte verwendet; dies ist kein allgemeiner PHP-/Symcon-Konsolenvalidator.
- `php tests/map.php`, `node tests/map-ui.js` und `node tests/map-transport.js`: WoIst-Backend-/Frontend-Verträge einschließlich SDK-Zustandsnachrichten, Cache-Isolation, Aktualität, leeren/ungültigen Positionen, Providerwechsel und sicherer Textdarstellung. Node 22 wird für den CI-Testlauf eingerichtet.
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

Der Zugriffstoken wird nur in der IPSView-Seite eingebettet und als `X-WoIst-Token` gesendet. Native Tile-Nachrichten, `GetState()` und HTTP-Zustandsantworten enthalten ihn nicht. Deaktivierung und Kontowechsel widerrufen den bisherigen Zugang. CORS ohne Cookies erlaubt auch opaque WebViews; solche Seiten brauchen eine explizite `IPSViewServerURL`, da weder Referrer noch fremde Elternfenster als Token-Ziel herangezogen werden. Die HTML-Variable enthält Standortdaten und einen lesenden Bearer-Zugang und ist deshalb vertraulich.

Die Seite hält die Leaflet-Karte und vorhandene Marker am Leben. Aktualisierungen verändern Position, Stil und Popup-Inhalt ohne automatisches Verschieben oder Zoomen. Auswahl bleibt bei gelisteten Geräten auch ohne Position erhalten; ein entferntes Gerät verliert seine Auswahl. Verbindungsfehler zeigen einen Hinweis, behalten Daten und Ansicht und lösen begrenzte Wiederholungen aus. Details stehen in ADR 0005. Änderungen der Konfiguration und ausdrücklich angeforderte HTML-Regeneration dürfen weiterhin die Seite ersetzen.

Formular-Verträge prüfen jetzt die tatsächlich erzeugten Formulare einschließlich Designer-`onChange` und Helper-`MESSAGE:`-Rückgaben. Die Kopieraktion ist ausdrücklich ein Snapshot des gespeicherten Kachel-Designs; sie übernimmt keine ungespeicherten Formulareingaben. Ein realer IPSView-Laufzeittest bleibt offen; die Browserprüfung verwendet synthetische HTML-Ausgaben und lokal ersetzte Kartenkacheln.

Der zentrale HTML-Renderer wurde im selben Arbeitspaket in `Symcon_ModuleHelper` gegen rekursive Platzhalterersetzung korrigiert und mit Tests abgesichert. Das lokale Bundle enthält die byteidentische, noch nicht commitete Quelle; `source_sha` bezeichnet weiterhin den bisherigen Helper-HEAD, der SHA-256-Wert die tatsächlich gelieferten Bytes. Nach dem Benutzer-Commit kann der reguläre Helper-Sync die Provenienz auf den neuen Commit bringen. Keine Version wurde manuell erhöht und kein Consumer-Sync ausgelöst.

Lokaler Nachweis für ADR 0005: Gesamtsuite unter PHP 8.5.10 einschließlich zweier Node-Vertragstests erfolgreich. Chromium-Prüfung mit echtem lokalem Leaflet und synthetischem Fetch-Endpoint: genau ein HTML-Dokument/eine Karteninstanz, exakter Mittelpunkt und Zoom sowie ausgewähltes bewegtes Popup über Updates und Netzfehler/Wiederverbindung erhalten. Positionsverlust erhält die Auswahl, Entfernung des Geräts hebt sie auf. Zusätzliche HTTP-SAPI-Prüfung des echten Handlers mit synthetischer Instanz bestätigt 200/403/405/204, JSON, No-Store, Nosniff und CORS ohne Credentials. Native Symcon-Hook-Weiterleitung und reale IPSView-WebViews bleiben praktisch abzunehmen.
