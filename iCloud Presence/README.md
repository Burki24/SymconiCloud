# iCloud Presence / iCloud Anwesenheit

Eine Instanz wertet **mehrere Geräte eines iCloud-Kontos** anhand gemeinsamer
kreisförmiger Zonen aus. Jedes Gerät besitzt eine eigene Anwesenheit,
Entfernung und Aufenthaltsdauer. Darüber steht die **Gesamtanwesenheit** der
Gruppe. Voraussetzung sind Symcon 9, ein angemeldetes iCloud-Konto dieser
Library und der zentral verteilte GeofenceHelper mit `AggregateGeofencePresence`.
Eine Geräteinstanz oder WoIst-Karte ist nicht erforderlich.

## Einrichtung

1. Im iCloud-Geräte-Konfigurator unter **Anwesenheitsgruppen** eine Gruppe
   erstellen oder **iCloud Anwesenheit** manuell anlegen. Mit dem gewünschten
   Konto verbinden. Der Konfigurator ordnet vorhandene Gruppen dem Konto zu.
2. Unter **Geräte** die gewünschten Geräte hinzufügen. Der optionale Anzeigename
   überschreibt den Namen aus dem Kontocache. Geräte dürfen nur einmal vorkommen;
   maximal 64 Geräte sind möglich. Zur Zuordnung dient die stabile Geräte-ID,
   nicht der Name oder die Reihenfolge.
3. Unter **Zonen** die gemeinsamen Zonen definieren und genau eine Referenzzone
   markieren. Diese Referenz gilt für alle ausgewählten Geräte.

   | Spalte | Bedeutung |
   | --- | --- |
   | Zonen-ID | Eindeutige stabile ID, etwa `home` oder `work`; beginnt mit einem Buchstaben, danach Buchstaben, Ziffern, `_` oder `-`; maximal 48 Zeichen |
   | Name | Angezeigter Name, maximal 80 Zeichen |
   | Breitengrad / Längengrad | Mittelpunkt in Dezimalgrad |
   | Radius | Kreisradius in Metern, 1 bis 1.000.000 |
   | Referenzzone | Genau eine Zone markieren, beispielsweise Zuhause |

4. GPS-Einstellungen prüfen und die Konfiguration übernehmen.

Eine bis 64 Zonen sind möglich. Bei Überlappungen hat eine eindeutig passende
Referenzzone Vorrang; sonst gilt die Reihenfolge der Zonenliste. Zonen und
GPS-Einstellungen werden für alle Geräte gemeinsam gepflegt. Die Berechnung
nutzt Koordinaten und Radien und benötigt keine Adressauflösung.

## Gesamtanwesenheit

| Anzeige | Bedeutung |
| --- | --- |
| Anwesend | Mindestens ein ausgewähltes Gerät ist bestätigt in der Referenzzone |
| Abwesend | Alle ausgewählten Geräte sind bestätigt außerhalb der Referenzzone |
| Unbekannt | Keines ist anwesend und mindestens eines ist unbekannt; ebenso bei leerer Geräteliste |

Ein unbekanntes oder fehlendes Gerät wird niemals als abwesend gezählt. Ein
anderes Gerät mit bestätigter Anwesenheit reicht weiterhin für Anwesend.

Die Gruppenvariablen sind ausschließlich lesbar:

| Ident | Typ | Bedeutung |
| --- | --- | --- |
| `Presence` | int | Gesamtanwesenheit: -1 Unbekannt, 0 Abwesend, 1 Anwesend, mit Klartextanzeige |
| `SelectedCount` | int | Anzahl ausgewählter Geräte |
| `PresentCount` | int | Geräte mit bestätigter Anwesenheit |
| `AbsentCount` | int | Geräte mit bestätigter Abwesenheit |
| `UnknownCount` | int | Geräte mit unbekannter Anwesenheit |
| `DataAvailable` | bool | Erfolgreicher Zugriff auf den authentifizierten Kontocache |
| `Reason` | string | Gruppenbewertung oder Konfigurations-/Kontofehler |

Für Automationen ausdrücklich auf `Presence == 0` oder `== 1` prüfen. Eine
Boolean-Umwandlung würde `-1` falsch auswerten. Datenverfügbarkeit bedeutet
nicht, dass jedes ausgewählte Gerät eine aktuelle und verlässliche Position hat.

## Variablen je Gerät

Je Gerät werden elf lesbare Variablen angelegt. Die sichtbaren Namen beginnen
mit dem Anzeigenamen des Geräts. Gleiche Gerätenamen sind erlaubt; bei Bedarf
unterschiedliche Anzeigenamen vergeben. Technisch sind die Variablen anhand
der Geräte-ID getrennt.

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `Presence` | int | -1 Unbekannt, 0 Abwesend, 1 Anwesend bezogen auf die Referenzzone |
| `Zone` | string | Bestätigte Zone, Außerhalb aller Zonen oder Unbekannt |
| `ZoneID` | string | Stabile ID der aktuellen Zone; sonst leer |
| `Reason` | string | Verständlicher Grund der Standortbewertung |
| `DataAvailable` | bool | Gerät im verfügbaren Kontocache vorhanden |
| `DistanceAvailable` | bool | Aktuelle Entfernung auswertbar |
| `Distance` | float | Luftlinie zum Mittelpunkt der Referenzzone in Metern |
| `StayAvailable` | bool | Bestätigter Aufenthalt innerhalb einer Zone vorhanden |
| `ZoneSince` | int | Unix-Zeit der ersten bestätigten Beobachtung des Aufenthalts, sonst 0 |
| `StayDuration` | int | Sekunden seit dieser Beobachtung, als Dauer formatiert; sonst 0 |
| `TransitionPending` | bool | Dieses Gerät wartet auf Bestätigung eines Zonenwechsels |

Skripte erhalten den jeweiligen Ident über
`ICPR_GetDeviceVariableIdent(int $InstanceID, string $DeviceID, string $Ident): string`.
`Ident` ist eines der oben genannten Felder. Anschließend die Variablen-ID mit
`IPS_GetObjectIDByIdent` an der Anwesenheitsinstanz auflösen. Die technischen
Idents verwenden `Device_`, die ersten 32 Hexzeichen des SHA-256 der Geräte-ID,
`_` und das Feld. Anzeigenamen und Reihenfolge verändern diese Kennungen nicht.

Aufenthaltsdauer und GPS-Bestätigung sind pro Gerät getrennt. Außerhalb aller
Zonen oder bei Unbekannt sind Beginn und Dauer 0; `StayAvailable` ist false.
Nicht verfügbare Entfernungen sind 0 mit `DistanceAvailable=false`. In einem
unklaren Grenzbereich kann die Entfernung verfügbar sein, obwohl Anwesenheit
noch unbekannt ist. Die Dauer beschreibt beobachtete Zeit, nicht den garantiert
exakten Ankunftszeitpunkt. Bei einem bestätigten Wechsel beginnt sie mit der
bestätigenden Position neu.

## GPS-Stabilisierung und Aktualisierung

| Eigenschaft | Vorgabe | Bedeutung |
| --- | --- | --- |
| `Enabled` | true | Gruppe auswerten |
| `Devices` | `[]` | Liste aus `deviceID` und optionalem `name` |
| `Zones` | `[]` | Gemeinsame Zonenliste mit genau einer Referenzzone |
| `MaxLocationAge` | 900 s | Maximal zulässiges Standortalter, 30 bis 86.400 s |
| `MaxAccuracy` | 100 m | Maximal zulässiger positiver Genauigkeitsradius, 1 bis 10.000 m |
| `Hysteresis` | 25 m | Grenzpuffer, 0 bis 10.000 m; kleiner als jeder Zonenradius |
| `ConfirmationTime` | 30 s | Wechsel mit einer weiteren späteren Position bestätigen; 0 deaktiviert die Verzögerung |
| `MaxSpeed` | 300 km/h | Unplausible Sprünge nach Abzug beider Genauigkeitsradien verwerfen; 0 deaktiviert den Filter, maximal 2.000 km/h |

Sicher innerhalb: Entfernung plus Genauigkeitsradius liegt innerhalb von
Radius minus Grenzpuffer. Sicher außerhalb: Entfernung minus Genauigkeitsradius
beträgt mindestens Radius plus Grenzpuffer. Dazwischen bleibt eine bestätigte
Zuordnung erhalten; ohne Vorgeschichte lautet das Ergebnis Unbekannt. Die erste
klare Position wird sofort angenommen. Anschließende Wechsel brauchen neue,
zeitlich getrennte Positionen. Wiederholte Cache-Abfragen bestätigen keinen Wechsel.

Fehlende, ungenaue, von Apple als alt oder ungenau markierte, überalterte oder
unplausible Positionen führen je Gerät zu Unbekannt. Datenverlust unterbricht
den Aufenthalt. Erst eine neue verlässliche Position ermöglicht die Wiederaufnahme;
ein alter Cachetreffer darf die Unterbrechung nicht überbrücken.

Kontobenachrichtigungen und ein lokaler 15-Sekunden-Timer lesen den Kontocache
**einmal je Gruppenaktualisierung**. Sie aktualisieren Datenalter und Dauer,
ohne Apple-Abfragen oder aktive Neuortung. Das gilt auch beim Öffnen des
Formulars, bei **Gespeicherten Standort auswerten** und bei
`ICPR_Update(int $InstanceID): bool`. Bei einem 300-Sekunden-Kontoabruf kann die
Bestätigung eines Wechsels entsprechend länger als 30 Sekunden dauern.

`ICPR_Update` liefert true bei verfügbarem authentifiziertem Kontocache, auch wenn
einzelne Positionen unbekannt oder ausgewählte Geräte nicht mehr gelistet sind.
Bei deaktivierter Gruppe, ungültiger Konfiguration oder Kontofehlern lautet das
Ergebnis false. Status 201 kennzeichnet Konfigurationsfehler, 202 fehlende
Kontodaten und 203 eine Auswahl, deren Geräte sämtlich im Kontocache fehlen.
Bei leerer Auswahl, ungültiger Konfiguration oder Deaktivierung ist der Timer aus.

## Bestehende Instanzen und Änderungen der Auswahl

Eine bisherige einzelne `DeviceID` wird beim Modulupdate über den offiziellen
Symcon-Migrationsvertrag als erste Gerätezeile übernommen. Gemeinsame Zonen und
GPS-Einstellungen bleiben erhalten. Die alten Gerätevariablen behalten ihre
Objekt-IDs und werden technisch dem ursprünglichen Gerät zugeordnet. Die Gruppe
bekommt eine eigene Gesamtanwesenheit. Bestehende Verweise auf alte Variablen-IDs
beziehen sich somit weiterhin auf das ursprüngliche Gerät. Alte feste Feld-Idents
müssen in Skripten auf die neue Gruppenvariable oder den Geräte-Ident umgestellt werden.

Ein vorhandener Aufenthalt wird bei unveränderter Konto-, Geräte-, Zonen- und
GPS-Zuordnung übernommen. Konto-, Zonen- oder GPS-Änderungen beginnen eine neue
Auswertung; Ergänzen und Umordnen der Geräte setzt andere Aufenthalte nicht zurück.
Entfernte Geräte werden nicht mehr mitgezählt. Ihre Variablen werden auf unbekannt
bzw. nicht verfügbar gesetzt und ausgeblendet, sodass Objekt-IDs und Archivdaten
erhalten bleiben. Erneute Auswahl verwendet diese Variablen wieder. Eine bewusst
leere Liste wird nicht aus der alten Geräteauswahl erneut befüllt.

Vor dem fachlichen Modulupdate das regulär verteilte GeofenceHelper-Paket mit
`AggregateGeofencePresence` integrieren. Das bestehende Helper-Abonnement reicht
aus; es gibt keine neue Subscription oder zusätzliche Helper-Ressourcen.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
