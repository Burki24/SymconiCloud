# Unveröffentlicht

## Fixes

- Die eingebettete IPSView-Ausgabe ermittelt die Serveradresse über den zentralen HTTP-Ursprungsresolver automatisch aus dem einbettenden Kontext. Die HTTP-Kartenseite nutzt einen expliziten Asset-Pfad für den Symcon-WebHook-Kontext und meldet fehlende Assets mit HTTP 503.

- WoIst registriert seinen flüchtigen internen WebHook bedingungslos beim Laden der Instanz. Das Aktivieren und Deaktivieren der IPSView-Ausgabe steuert nur Zugriff und Token; fehlgeschlagene Registrierungen werden als Fehler gemeldet.

- Gemeinsame Erkennung und Platzreservierung der nativen Kachelüberschrift sowie der höhenfüllende Visualisierungsrahmen werden aus dem zentralen ResponsiveVisualizationHelper bezogen.

- WoIst berücksichtigt die native Kachelüberschrift und passt Karte und Geräteliste an Breite und Höhe der Kachel an. Schmale Kacheln stapeln die Bereiche; die Geräteliste scrollt innerhalb der verfügbaren Fläche. Größenänderungen erhalten Mittelpunkt, Zoom und Auswahl.

- IPSView lädt die WoIst-Karte als echte HTTP(S)-Seite aus Symcon. Kartenkacheln erhalten dadurch den tatsächlichen Server-Ursprung als Referer. Geräte- und Standortdaten bleiben geschützt; die Seite und die bestehende Ansicht werden bei Datenänderungen nicht neu geladen.

- WoIst aktualisiert geöffnete IPSView-Karten über einen geschützten, ausschließlich lesenden Zustandsabruf. Datenänderungen ersetzen das HTML nicht mehr; Zoom, Kartenausschnitt und Geräteauswahl bleiben erhalten. Verbindungsfehler führen zu einem Hinweis und Wiederholungen statt zu einem Neuladen.

- Der iCloud-Geräte-Konfigurator bleibt nach dem Übernehmen bei gültigem Kontocache aktiv und meldet fehlende Verbindung oder nicht verfügbare Daten mit dem passenden Status.
- WoIst-Konfigurator und Geräte lassen sich im Einrichtungsassistenten mit einem vorhandenen iCloud-Konto verbinden; die Ermittlung kompatibler übergeordneter Instanzen liefert jetzt das von Symcon erwartete Format.

## Neu

- WoIst erhält einen Kachel- und IPSView-Designer für Größe, Standortfarben und sichtbare Elemente. Die optionale IPSView-HTML-Variable aktualisiert sich mit den Kontodaten; ihr Design kann von der Kachel übernommen oder unabhängig eingestellt werden.

- Der Gerätekonfigurator wird ausdrücklich als iCloud Geräte Konfigurator angeboten; das Präfix ICDC bleibt unverändert.

- Neues Modul iCloud WoIst (ICWI) mit interaktiver Karte aller Geräte des verbundenen Kontos, Standortalter, Genauigkeit und Anzeige nicht ortbarer Geräte. Bei Apple aktualisieren fragt ausdrücklich das Konto ab; Kartenöffnung und Cache-Aktualisierung starten keine Apple-Anmeldung.
- OpenStreetMap-Kartenhintergrund mit lokal ausgeliefertem Leaflet; Kachel-URL und Quellenangabe sind konfigurierbar. Beim Kartenanbieter werden die sichtbare Kartenregion und die Browser-IP angefragt.
- Geräteinstanz und Konfigurator heißen jetzt iCloud Gerät und iCloud Geräte Konfigurator; die Präfixe sind ICID und ICDC. Achtung: Die bisherigen Test-APIs ICWD und ICWC entfallen. Testinstanzen können bei Bedarf neu angelegt werden.
- WoIst ist ausschließlich für ein späteres Kartenmodul vorgesehen; eine Karte ist in diesem Stand nicht enthalten.
- Erste SymconiCloud-Library mit eigenständigem iCloud-Konto und lesender WoIst-Geräteanbindung.
- Apple-Anmeldung mit SRP und sechsstelliger Zwei-Faktor-Bestätigung über ein vertrauenswürdiges Gerät.
- Persistente Sitzungen, kontrollierte Wiederherstellung und getrennte Gerätecaches je Konto.
- WoIst-Konfigurator und ausschließlich lesbare Gerätevariablen für Akku, Gerätestatus und vorhandene Standortdaten.
- Fehler- und Verfügbarkeitsanzeigen sowie Wartezeiten bei Apple-Anfragelimits.
