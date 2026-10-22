# Entwicklungsstand

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
