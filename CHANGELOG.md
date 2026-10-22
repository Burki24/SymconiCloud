# Unveröffentlicht

## Neu

- iCloud-Geräteinstanzen erhalten die lesbare Variable **Adresse** aus dem vorhandenen WoIst-Adresscache, ohne zusätzliche Nominatim-Abfragen. Eine passende Karte am selben Konto wird automatisch verwendet; bei mehreren Karten lässt sich die Quelle auswählen. Nach dem Update die Geräteinstanzkonfiguration einmal übernehmen.

## Fixes

- **Bei Apple aktualisieren** steht auch in der IPSView-Karte zur Verfügung. Der feste, authentifizierte Aufruf nutzt die bestehende Kontoabfrage samt Wartezeit und aktualisiert die Daten ohne Neuladen oder Verlust der Kartenansicht.

- Die WoIst-Geräteauswahl hebt den zugehörigen Kartenmarker mit größerem Punkt und stärkerem Rand hervor und bringt ihn vor überlappende Marker. Die Listenwahl zentriert die Karte auf das Gerät; Datenupdates erhalten die gewählte Ansicht und Hervorhebung.

- Gemeinsame Mausrad-, Scroll- und Frame-Unterstützung aus Symcon_ModuleHelper eingebunden; IPSView-Ereignisse mit umadressiertem Ziel werden zentral behandelt.

- WoIst bindet die zentralen Kachelfarben und den vollständigen IPSView-Stileditor einschließlich Stilquellen, Transparenz und Typografie ein. Die Farbrollen wirken auf die Kartenoberfläche und werden ohne Neuladen aktualisiert; beide Helper-Pakete einschließlich ihrer Abhängigkeiten und Ressourcen sind für den automatischen Sync abonniert.

- Die native WoIst-Kachel stellt nach einem iframe-Neuaufbau bei Wiederverbindung Zoom, Kartenausschnitt, Geräteauswahl und Popup aus dem zentralen OpenFreeMap-Sitzungsspeicher wieder her. Entfernte Geräte werden nicht erneut ausgewählt; gesperrter Browser-Speicher verhindert die Kartenanzeige nicht.

- Das eingebundene OpenFreeMap-Helper-Manifest enthält jetzt auch die Abhängigkeiten `HelperTranslationHelper` und `VisualizationAssetHelper` einschließlich ihrer Ressourcen.

- Der Kartenrenderer wird lokal und komprimiert über einen öffentlichen Asset-Endpunkt ausgeliefert, damit native Kachel und HTTP-Seite unter Symcons Ausgabelimit bleiben. Konfliktmarkierungen im vorhandenen Helper-Manifest wurden mit den kanonischen Metadaten abgeglichen.

- Die eingebettete IPSView-Ausgabe ermittelt die Serveradresse über den zentralen HTTP-Ursprungsresolver automatisch aus dem einbettenden Kontext. Die HTTP-Kartenseite nutzt einen expliziten Asset-Pfad für den Symcon-WebHook-Kontext und meldet fehlende Assets mit HTTP 503.

- WoIst registriert seinen flüchtigen internen WebHook bedingungslos beim Laden der Instanz. Das Aktivieren und Deaktivieren der IPSView-Ausgabe steuert nur Zugriff und Token; fehlgeschlagene Registrierungen werden als Fehler gemeldet.

- Gemeinsame Erkennung und Platzreservierung der nativen Kachelüberschrift sowie der höhenfüllende Visualisierungsrahmen werden aus dem zentralen ResponsiveVisualizationHelper bezogen.

- WoIst berücksichtigt die native Kachelüberschrift und passt Karte und Geräteliste an Breite und Höhe der Kachel an. Schmale Kacheln stapeln die Bereiche; die Geräteliste scrollt innerhalb der verfügbaren Fläche. Größenänderungen erhalten Mittelpunkt, Zoom und Auswahl.

- IPSView lädt die WoIst-Karte als echte HTTP(S)-Seite aus Symcon. Kartenkacheln erhalten dadurch den tatsächlichen Server-Ursprung als Referer. Geräte- und Standortdaten bleiben geschützt; die Seite und die bestehende Ansicht werden bei Datenänderungen nicht neu geladen.

- WoIst aktualisiert geöffnete IPSView-Karten über einen geschützten, ausschließlich lesenden Zustandsabruf. Datenänderungen ersetzen das HTML nicht mehr; Zoom, Kartenausschnitt und Geräteauswahl bleiben erhalten. Verbindungsfehler führen zu einem Hinweis und Wiederholungen statt zu einem Neuladen.

- Der iCloud-Geräte-Konfigurator bleibt nach dem Übernehmen bei gültigem Kontocache aktiv und meldet fehlende Verbindung oder nicht verfügbare Daten mit dem passenden Status.
- WoIst-Konfigurator und Geräte lassen sich im Einrichtungsassistenten mit einem vorhandenen iCloud-Konto verbinden; die Ermittlung kompatibler übergeordneter Instanzen liefert jetzt das von Symcon erwartete Format.

## Neu

- Optionale Nominatim-Adressauflösung mit konfigurierbarer eigener Serveradresse und Sprache. Der zentrale ReverseGeocodingHelper löst Positionen im Hintergrund auf und speichert Ergebnisse persistent mit begrenztem Cache und Fehlerwartezeit. Adressen erscheinen in Geräteliste und Popup von Kachel und IPSView, ohne die Kartenansicht oder Auswahl zurückzusetzen.

- WoIst nutzt OpenFreeMap-Vektorkarten. Kachel- und IPSView-Designer besitzen eigene OpenFreeMap-Menüs mit Auto-/Hell-/Dunkelmodus, Kartenstilen, eigenen Stil-URLs, Kartendetails, Beschriftungssprache und getrennten Farbpaletten. Daten- und Designupdates erhalten die Kartenansicht und Geräteauswahl.

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
