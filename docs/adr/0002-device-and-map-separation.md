# ADR 0002: iCloud-Geräte und WoIst-Karte trennen

- Status: Angenommen
- Datum: 21.10.2026
- Präzisiert und ersetzt die Modulbenennung in ADR 0001.

## Entscheidung

Auf Benutzerwunsch bezeichnet WoIst ausschließlich ein separates Kartenmodul zur Darstellung der Positionen gelisteter Geräte. Die Karte wird in einem eigenen nächsten Schritt umgesetzt.

Die bereits implementierte Geräteinstanz heißt iCloud Device / iCloud Gerät (Typ 3, Präfix ICID). Sie stellt Geräte-, Akku- und Standortdaten als Variablen bereit. Der allgemeine iCloud Device Configurator / iCloud Geräte Konfigurator (Typ 4, Präfix ICDC) erzeugt Geräteinstanzen und ist direkt mit dem Kontomodul verbunden. Das Konto behält ICAC.

## Testphase und Umbenennung

Der Benutzer hat ausdrücklich festgelegt, dass erstellte Testinstanzen nicht erhalten bleiben müssen. Es werden deshalb keine Übergangsklassen oder alten API-Aliase eingeführt. Die bisherigen Präfixe ICWD/ICWC entfallen; Modulverzeichnisse, Klassennamen, Dokumentationslinks, Erstellungsvorgaben und Tests verwenden die neue Benennung. Die Modul-GUIDs identifizieren weiterhin dieselbe Geräte- beziehungsweise Konfiguratorfunktion und werden nicht grundlos geändert. Testinstanzen können neu angelegt werden; eine Migration produktiver Installationen ist nicht zugesagt.

## SDK-Vertragsprüfung

Formularaktionen werden anhand des offiziellen Button-Vertrags auf Callback-Existenz, Argumentanzahl, Typen und Variablen im selben Formularbereich geprüft. Erstellungsvorgaben werden gemäß Configurator-Variante 1 als Objekte geprüft und über den offiziellen Stub-Kernel tatsächlich auf eine Geräteinstanz angewandt. Negativfälle sichern ab, dass ungültige Funktionen, falsche Argumente, scope-fremde Variablen, JSON-Strings anstelle von Konfigurationsobjekten und unbekannte Properties abgewiesen werden.

Der Konfigurator liest in ApplyChanges ausschließlich den Kontocache und ermittelt daraus denselben Verfügbarkeitsstatus wie beim Öffnen und Aktualisieren des Formulars. Die Offline-Prüfungen ersetzen keine vollständige Simulation der Verwaltungskonsole.
