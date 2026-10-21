# 0006: Echte HTTP(S)-Kartenseite für IPSView

Datum: 2026-10-21. Status: angenommen. Ergänzt ADR 0005.

## Anlass

Der Benutzer beobachtet 403-Sperrkacheln des öffentlichen OSM-Anbieters in IPSView. OSM verlangt bei Webseiten einen gültigen Referer. Eine `data:`-Seite kann diesen auch mit Meta-Referrer-Policy nicht liefern. Die Diagnose reproduzierte diesen Unterschied ohne öffentliche Kachelserver anzusprechen. Die HTTP(S)-Seite schafft einen echten Browser-Ursprung; der konkrete Sperrgrund und eine Aufhebung einer schon bestehenden Sperre bleiben Sache des Anbieters.

## Entscheidung

Die bestehende WebContent-Variable wird vom zentralen IPSViewHTMLPageHelper weiterhin als stabile Ausgabe verwaltet. Sie enthält nur einen Loader, der ein iframe einmalig auf die echte instanzbezogene Symcon-HTTP(S)-Route `/hook/SymconiCloudWoIst<ID>/page` navigiert. Ein iframe-srcdoc, data-Kartendokument oder Kartenproxy wird nicht verwendet. Der Loader leitet den Zielserver ausschließlich aus der expliziten Server-Adresse oder seiner eigenen HTTP(S)-Adresse ab. Ohne diese Information zeigt er einen Konfigurationshinweis.

GET auf der exakten Seitenroute liefert die lokalen Leaflet-/Modulassets und eine leere Seitenschale. Die öffentliche Seite enthält keine Geräte, Standorte, Zugangstokens oder privaten Kartenanbieter-URLs beziehungsweise Attributionstexte. `Referrer-Policy: origin`, UTF-8, No-Store und Nosniff werden im HTTP-Header gesetzt. Der Daten-POST bleibt authentifiziert und ausschließlich lesend. Query-Token und andere Routen werden nicht angenommen. Apple-Anfragen entstehen weder beim GET der Seite noch beim Polling.

Der Loader übergibt den zufälligen lesenden Token im Fragment der iframe-Zieladresse. HTTP überträgt dieses Fragment nicht zum Server. Die Kartenseite liest und entfernt es vor den ersten Daten- oder Kartenanfragen mit `history.replaceState`. Scheitert dies, wird der Zugang nicht benutzt. Weder HTTP-Zugriffslogs noch Karten-Referer enthalten den Token. Die HTML-Variable selbst enthält ihn und bleibt vertraulich. Ein eigenes Speichern in Cookies oder Browser-Storage wird nicht eingeführt.

Nach gültiger Token-Übernahme lädt die Seite den bestehenden bereinigten Zustand per POST-Header. Erst dessen Antwort konfiguriert den Kartenanbieter. Es bleiben derselbe Leaflet-Zustand, Marker, Zoom, Mittelpunkt und Geräteauswahl erhalten. Datenänderungen oder Netzfehler navigieren weder iframe noch Seite. Ein manueller Reload der inneren Seite ohne Fragment besitzt keinen automatischen Token-Ersatz; erneutes Öffnen der IPSView-Ausgabe startet ihren Loader wieder mit dem gültigen Fragment.

Die native Symcon-Kachel verwendet weiterhin ihren HTML-SDK-Vertrag. Die Änderung richtet sich an IPSView/WebContent. Vorhandene Konfiguration muss nach dem Update einmal übernommen werden, damit die Ausgabe den neuen Loader erhält. Es werden keine Modul-GUIDs oder Präfixe geändert und keine neuen universellen Helper eingeführt.

## Nachweise und Grenzen

PHP-Verträge prüfen public GET ohne sensible Daten, exakte Routen, Methoden und unveränderten Header-Zugang zum privaten Zustand. Node-Verträge prüfen Loader-Zielauflösung, Fragmentverbrauch, Fail-Closed bei Sandbox-Einschränkungen und bestehenden Zustandserhalt. Browserprüfungen mit dem echten Loader in einem opaque data-Dokument und der echten ausgelieferten Kartenseite mit lokal ersetzten Kacheln bestätigen den tatsächlich ausgehenden Origin-Referer, keine Token in Netzwerk-URLs/Kachelheaders sowie dieselbe Karteninstanz mit exakt gleichem Mittelpunkt, Zoom, Auswahl und Popup über Datenänderungen und Fehler/Wiederverbindung. Ein lokaler HTTP-SAPI-Test bestätigt die Antwortheader und den geschützten Zustandszugriff. Öffentliche OSM-Server wurden nicht angesprochen.

Die native IPSView-WebView und die Symcon-Live-Hook-Weiterleitung bleiben praktisch abzunehmen. Ein vom Host geerbtes restriktives iframe-Sandboxing oder Mixed-Content-Blockaden können die Einbettung verhindern. Der Browser darf in diesem Fall keine ungeschützte Ersatzroute benutzen.

Quellen: [OSM Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/), [Location.hash](https://developer.mozilla.org/en-US/docs/Web/API/Location/hash), [History.replaceState](https://developer.mozilla.org/en-US/docs/Web/API/History/replaceState).
