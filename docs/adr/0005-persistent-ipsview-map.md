# 0005: Stabile IPSView-Karte mit getrennten Zustandsdaten

Datum: 2026-10-21. Status: angenommen. Ersetzt die Datenübertragung aus ADR 0004.

## Anlass

Das Ersetzen der WebContent-HTML-Variable bei Konto-Broadcasts lädt die IPSView-Seite neu und verliert Zoom, Kartenausschnitt und Geräteauswahl. Datenaktualisierungen müssen innerhalb derselben Karte stattfinden.

## Entscheidung

Das HTML wird beim Übernehmen von Konfiguration oder bei ausdrücklicher Regeneration erzeugt. Broadcasts, Cache-Updates und explizite Apple-Aktualisierungen verändern ausschließlich den bereinigten IPSView-Zustandscache. Die geöffnete Seite fragt diesen per POST alle fünf Sekunden ab. Eine weitere Apple-Abfrage entsteht dadurch nicht. Der native HTML-SDK-Datenfluss bleibt erhalten.

Der per Instanz benannte native Symcon-WebHook akzeptiert einen unabhängigen zufälligen 256-Bit-Zugriffstoken im Request-Header. Weder GET/Query-Token noch Cookies sind für den Zugang vorgesehen. Exakter Pfad, aktivierte Ausgabe und Bindung an das verbundene Konto werden vor der Ausgabe geprüft. Deaktivieren und Kontowechsel widerrufen bisherige Tokens. Der Endpoint gibt keine Apple-Zugangsdaten aus und kann keine Befehle oder Refresh-Aktionen ausführen. Der zentrale HttpResponseHelper wird unverändert für JSON-/Fehlerantworten mit No-Store und Nosniff eingebunden.

CORS ohne Credentials unterstützt opaque IPSView-WebViews; der Zugriff bleibt an den Token gebunden. Solche Seiten benötigen die explizite aus dem Client erreichbare HTTP(S)-Server-Adresse. Nur bei eigener HTTP(S)-Seitenadresse kann der relative Hook-Pfad aufgelöst werden. Fremde Elternadressen, Referrer und Base-Tags werden nicht als mögliche Token-Ziele geraten. Redirects werden nicht verfolgt. Die Server-Adresse erlaubt keinen Benutzer, Pfad, Query oder Fragment.

Die HTML-Variable enthält den lesenden Zugriffstoken und vorhandene Standortdaten. Exportierte/aufbewahrte HTML-Ausgaben sind vertraulich. Ein vom Benutzer gewünschtes Neuladen oder Regenerieren bleibt möglich; der Anspruch auf Ansichtserhalt bezieht sich auf Datenaktualisierungen.

## Verhalten und Fehlerfälle

Die Leaflet-Karte und vorhandene Marker bleiben bestehen. Position, Stil und Popup-Inhalt werden aktualisiert. Popup-AutoPan ist abgeschaltet, damit eine bewegte ausgewählte Position nicht die Ansicht verschiebt. Manuelle Gerätewahl und Alle Positionen anzeigen dürfen die Ansicht verändern. Auswahl bleibt bei Positionsverlust erhalten, solange das Gerät noch gelistet ist; bei Entfernung wird sie aufgehoben.

Es gibt höchstens eine aktive Abfrage. Zehn Sekunden Timeout, begrenzte Wiederholungsabstände bis 30 Sekunden, Pausieren unsichtbarer Seiten und Verwerfen überholter Antworten verhindern Abfragestau und Rücksprünge. Ausfälle zeigen einen Hinweis und behalten letzte Daten und Ansicht. Es erfolgt kein automatisches Neuladen.

Ein eigener WebSocket-Splitter wird für diesen begrenzten Anwendungsfall nicht eingeführt. Der ECharts-Transport hängt fachlich an seinem Gateway. Ein neuer universeller Transport-Helper ist bei einem Verbraucher nicht begründet; Kartenprotokoll und Abfrage bleiben lokal.

## Nachweise und Grenzen

PHP-Integrationstests prüfen unverändertes HTML bei Datenänderungen, Zustandscache, Methoden-/Tokenprüfung, Konto-/Instanzisolation und Widerruf. Node-Tests prüfen Abfrage-Lifecycle, Fehler/Wiederverbindung und Ansichtserhalt. Browserprüfungen verwenden echte lokale Leaflet-Assets mit synthetischen Daten und ersetzten Kartenkacheln. Native IPSView-WebView und die Live-Symcon-WebHook-Weiterleitung bleiben praktisch abzunehmen.

Offizielle SDK-Verträge: [RegisterHook](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/registerhook/) und [ProcessHookData](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/processhookdata/). Native Hooks sind flüchtig und werden in Create erneut registriert; die Instanz prüft ihren Ausgabezustand bei jeder Anfrage.
