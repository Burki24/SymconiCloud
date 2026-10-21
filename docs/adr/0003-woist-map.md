# ADR 0003: WoIst als Kontocache-basierte Karte

- Status: Angenommen für den Teststand
- Datum: 21.10.2026

## iCloud-Möglichkeiten und Grenzen

Die bestehende Geräteabfrage liefert Koordinaten, horizontalAccuracy, den Standortzeitpunkt und eine Alterskennzeichnung. Daraus kann die Library eine eigene Karte bauen; die iCloud-Geräteinstanzen werden dafür nicht als Zwischenstufe benötigt. Apples [Beschreibung von Find Devices](https://support.apple.com/en-hk/guide/icloud/mmfc0f2442/icloud) unterscheidet aktuelle und letzte bekannte Positionen sowie nicht ortbare Geräte. Die Darstellung in der nativen Find-My-App und die im Web verfügbaren Daten können sich unterscheiden. Weder eine Echtzeit-GPS-Verfolgung noch Zugriff auf alle verschlüsselten Offline-Netzwerkfunktionen wird aus der erfolgreichen Web-Anmeldung abgeleitet.

Die bereits implementierte Web-Abfrage refreshClient nutzt shouldLocate=false. Eine Aktualisierung liest die neueste von Apple bereitgestellte Inventarantwort, fordert jedoch keine aktive Neuortung an. Diese Grenze bleibt bestehen; die Karte zeigt den Gerätezeitpunkt getrennt vom Kontoabruf. Aktive Ortungsanforderungen wären eine eigene Änderung am Konto-Protokoll mit einer gesonderten Praxisprüfung. Die Beobachtung der Web-Schnittstelle ist keine von Apple zugesicherte Entwickler-API.

Für eine eigene Apple-Maps-Webkarte verlangt [MapKit JS](https://developer.apple.com/documentation/mapkitjs/creating-a-maps-token) einen Maps-Token aus einem Apple-Developer-Account. Die bestehende iCloud-Sitzung stellt keine solche MapKit-Autorisierung bereit. Interne Kartenendpunkte oder Tokens der iCloud-Webseite werden nicht übernommen.

## Modul und Datenfluss

iCloud WoIst (Typ 3, Präfix ICWI) ist eine eigene HTML-SDK-Instanz mit dem Konto als physischem Parent. HTML-SDK ist auch für Geräteinstanzen offiziell vorgesehen; ein zusätzlicher I/O- oder Splitter-Durchreicher hat keine Aufgabe. Es gelten dieselben Request-/Notification-DataIDs wie bei den anderen Kontokindern. Pro Karte wird ein Konto dargestellt; mehrere Konten verwenden getrennte Karten.

GetVisualizationTile liefert eine anfängliche, per JSON_HEX escapte Momentaufnahme. ReceiveData veröffentlicht neue Kontodaten über UpdateVisualizationValue. Die JavaScript-Funktion handleMessage aktualisiert Marker und Liste. requestAction('GetState', '') fordert nach dem Aufbau ausschließlich den Cache an, um die Lücke zwischen Erzeugung und Anzeige zu schließen. requestAction('RefreshDevices', '') ist die ausdrückliche Benutzerabfrage. Es gibt keinen zusätzlichen Apple-Polling-Timer, keine neue Anmeldung, keinen öffentlichen Webhook und keine Kopie der Apple-Sitzung im Kartenmodul.

Ungültige Positionen erzeugen keinen Marker; (0,0) bleibt eine gültige Position. Fehlende Standortzeitpunkte und weit in der Zukunft liegende Zeitpunkte werden nicht als aktuelle Position akzeptiert. Bei Kontofehlern bleiben gelieferte letzte bekannte Positionen sichtbar und werden als alt markiert. Leere Gerätelisten oder Kontowechsel entfernen alte Marker. Namen werden als Text eingefügt, nicht als HTML.

## Kartendarstellung und Abhängigkeiten

Leaflet 1.9.4 wird lokal unter libs/vendor ausgeliefert, einschließlich BSD-2-Clause-Lizenz. JS-/CSS-Prüfsummen wurden gegen die [offiziellen Integritätsangaben](https://leafletjs.com/download.html) geprüft; das Vendor-Manifest sichert die Dateien in CI ab. Neue zentrale Visualisierungs-Helper werden unverändert über den vorhandenen Helper-Vertrag eingebunden.

Standard sind OpenStreetMap-Rasterkacheln. Die [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/) verlangt HTTPS, sichtbare Attribution, normale Browser-Cachebehandlung und einen gültigen Referer. Die Darstellung setzt keine Cache-Bypass-Header, keinen Tile-Proxy und keinen Vorabdownload ein. Kachel-URL und Quellenangabe können geändert werden. Beim Kartenzugriff sind IP-Adresse und angefragte Region für den Kartenanbieter sichtbar; Gerätenamen und Apple-Zugangsdaten werden nicht übertragen.

## Nachweise

PHP-Integrationstests prüfen SDK-Signaturen, Parent-Vertrag, Cache-/Refresh-Trennung, Zustandsnachrichten, Kontoisolation, sichere HTML-Einbettung und ungültige Positionen. JavaScript-Tests prüfen Listen-/Marker-Verhalten, Alterung, Fehler, explizite Aktionen und Providerwechsel. Browserprüfungen mit synthetischen Daten verwenden lokal ersetzte Kacheln und rufen keine öffentlichen Kartenserver ab. Die reale Symcon-Kachel bleibt eine eigene Praxisabnahme.
