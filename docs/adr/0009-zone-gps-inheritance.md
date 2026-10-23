# ADR 0009: GPS-Vorgaben je Zone

Datum: 2026-10-23

Status: Lokal umgesetzt und geprüft; reguläre Helper-Verteilung und Modulupdate
stehen noch aus.

## Entscheidung

Die gemeinsamen Zonen einer Anwesenheitsgruppe können eigene Werte für
Grenzpuffer, maximale Standortungenauigkeit und Bestätigungszeit verwenden.
Standortalter und Geschwindigkeitsprüfung bleiben global. Standardmäßig werden
alle drei globalen GPS-Werte übernommen. `customGPS=false` ignoriert ruhende
eigene Werte; `customGPS=true` aktiviert `hysteresis`, `maxAccuracy` und
`confirmSeconds`. Alte Zonen benötigen keine Umschreibung.

Die Auflösung und Prüfung der wirksamen Einstellungen liegt im zentralen
`GeofenceHelper::ResolveGeofenceZoneSettings`. Aktive Einstellungen sind Teil
der normalisierten Zone und damit des bestehenden Quellfingerabdrucks. Alte und
weiterhin erbende Zonen behalten dieselbe normalisierte Geometrie und einen
bestehenden Aufenthalt. Die Variablenstruktur und Geräte-Idents bleiben gleich.

## Wechsel und Datenverlust

Ein Wechsel erfüllt die Qualität beider beteiligten Zonen und die längere ihrer
Bestätigungszeiten. Bei Eintritt aus außerhalb gilt die Zielzone, bei Austritt
ins Freie die Herkunftszone. Eine neue Position muss nach Ablauf der Zeit den
Wechsel bestätigen; dieselbe Cacheposition genügt nicht.

Ein persistenter optionaler Herkunftshinweis `qualityZone` verhindert, dass ein
Qualitätsverlust oder die Serialisierung des Zustands strengere Austrittsregeln
umgeht. Unbekannt schreibt keinen Aufenthalt fort. Wiederaufnahme in einer
anderen Zone benötigt ebenfalls Bestätigung; ein neuer verlässlicher Fix in der
gleichen Zone beginnt einen neuen beobachteten Aufenthalt. Alte Zustände ohne
dieses Feld bleiben lesbar. Eindeutig passende Referenzzonen behalten bei
Überlappungen ihre Priorität.

## Formular und Auslieferung

Die Zonenliste verwendet den offiziellen dynamischen List-Zeilendialog. Eigene
Werte werden in einem Bereich eingeblendet; die Vorgaben kommen aus den noch
nicht übernommenen globalen Formularwerten. Der Zeilendialog und die unabhängige
Laufzeitprüfung berücksichtigen den tatsächlich wirksamen Puffer derselben Zone.
Die Liste zeigt weiter nur die Geometrieübersicht. Wegen des Zeilenfeldes `id`
verwenden Zeilenaktionen die eindeutige Instanz-ID als Literal.

Zuerst den zentralen Helper regulär verteilen und integrieren, anschließend die
Moduländerung veröffentlichen. Es entstehen keine zusätzlichen Assets,
Abonnements oder Apple-Aufrufe. Lokale Testpakete verbleiben außerhalb der
normalen Arbeitsbäume. MCP-Tests verwenden eine getrennte temporäre Bibliothek,
deren Registrierung und Objekte nach der Prüfung entfernt werden.
