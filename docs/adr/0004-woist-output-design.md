# 0004: WoIst-Ausgaben und Design

Datum: 2026-10-21. Status: angenommen.

WoIst benötigt eine native Tile-Kachel und eine IPSView-Ausgabe mit eigenem Designer. SymconECharts dient als Referenz für Designübernahme, unabhängige Einstellung und verwaltete HTML-Ausgabe.

Beide Ausgaben verwenden dieselben Leaflet-Assets und den vorhandenen zentralen IPSViewHTMLPageHelper. Gemeinsame HTML-Variablenverwaltung, Formularaktionen, Übersetzungen und Rendering bleiben im Helper. Das Kartendesign mit Standortfarben, Markierungsradius, Sichtbarkeit und Kartenhöhe bleibt fachlich im Modul. Ein neuer allgemeiner Helper ist nicht notwendig. Die ECharts-Designergruppierung enthält chartbezogene Annahmen; sie wird nicht kopiert.

IPSView übernimmt standardmäßig das Tile-Design. Eine explizit als gespeichert bezeichnete Kopieraktion erzeugt einen unabhängigen Snapshot. Der Designer speichert bei normalen Feldänderungen erst durch Übernehmen; seine Verfügbarkeitsaktion verändert nur die Oberfläche.

Die IPSView-HTML-Variable aktualisiert sich durch Konto-Broadcasts, ohne weiteren Kontoabruf. Unverändertes HTML wird nicht neu geschrieben. Der ECharts-Gateway-/WebSocket-Transport ist kein allgemeiner Helper und wird nicht übernommen. Ein Neuladen des WebContent kann Zoom und Auswahl zurücksetzen. Ein späterer stabiler Live-Transport wäre eine eigene Architekturentscheidung. IPSView bietet lokale Auswahl und Zoom; Apple-Aktualisierungen erfolgen über Konto oder native Tile-Kachel.

Die optionale HTML-Variable enthält Standortdaten und bleibt nach Deaktivierung mit dem letzten Inhalt bestehen, bis der Benutzer sie über die Helper-Aktion löscht. Sie erbt den normalen Symcon-Zugriffsschutz. Es werden keine Apple-Tokens ausgeliefert und keine neuen öffentlichen HTTP-Endpunkte eingerichtet.

Bei der Wiederverwendung wurde die rekursive Platzhalterersetzung im zentralen Renderer als Fehler erkannt. Der kanonische Helper ersetzt Template-Schlüssel einmalig und prüft unbekannte Schlüssel nur im Template; platzhalterähnliche Gerätetexte bleiben Daten. Die Korrektur wird separat im Helper-Repository gepflegt.

Nachweise: Offline-Symcon-Stubs für Lifecycle und Cache-Vertrag, dynamische Formularverträge, Node-Tests für Design und Marker sowie Browserprüfungen der erzeugten Tile-/IPSView-Seiten mit lokal ersetzten Kartenkacheln. Echter IPSView-Betrieb bleibt praktisch abzunehmen.
