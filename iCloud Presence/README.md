# iCloud Presence / iCloud Anwesenheit

Eine Instanz wertet den gespeicherten Standort **eines Geräts** anhand mehrerer
kreisförmiger Zonen aus. Sie zeigt Anwesenheit in der Referenzzone, aktuelle
Zone, Entfernung und Aufenthaltsdauer. Voraussetzung sind Symcon 9, ein
angemeldetes iCloud-Konto dieser Library und der zentral verteilte GeofenceHelper.
Eine separate Geräteinstanz oder WoIst-Karte ist nicht erforderlich.

## Einrichtung

1. Im iCloud-Geräte-Konfigurator unter **Anwesenheitsauswertungen** eine Instanz
   für das gewünschte Gerät erstellen. Alternativ **iCloud Anwesenheit** manuell
   anlegen und mit der vorhandenen Kontoinstanz verbinden.
2. Das Gerät auswählen und unter **Zonen** eine oder mehrere Zonen eintragen:

   | Spalte | Bedeutung |
   | --- | --- |
   | Zonen-ID | Eindeutige, stabile ID, etwa `home` oder `work`; beginnt mit einem Buchstaben, danach Buchstaben, Ziffern, `_` oder `-`; maximal 48 Zeichen |
   | Name | Angezeigter Name, maximal 80 Zeichen |
   | Breitengrad / Längengrad | Mittelpunkt in Dezimalgrad |
   | Radius | Kreisradius in Metern, 1 bis 1.000.000 |
   | Referenzzone | Genau eine Zone markieren, beispielsweise Zuhause |

3. GPS-Einstellungen prüfen und die Konfiguration übernehmen.

**Anwesend** bedeutet: Das Gerät befindet sich in der Referenzzone.
**Abwesend** bedeutet: Es befindet sich zuverlässig in einer anderen Zone oder
außerhalb aller Zonen. **Unbekannt** bedeutet, dass keine verlässliche Aussage
möglich ist. Für Automationen ausdrücklich auf `Presence == 0` oder `== 1`
prüfen; eine Boolean-Umwandlung würde `-1` falsch auswerten.

Bei Überlappungen hat eine eindeutig passende Referenzzone Vorrang. Für andere
eindeutige Treffer gilt die Reihenfolge der Zonenliste. Die Auswertung nutzt
Koordinaten und Radien; eine Adressauflösung ist dafür nicht nötig.

## GPS-Stabilisierung

| Eigenschaft | Vorgabe | Bedeutung |
| --- | --- | --- |
| `Enabled` | true | Auswertung aktivieren |
| `DeviceID` | leer | Stabile Apple-Geräte-ID aus dem Kontocache |
| `Zones` | `[]` | Zonenliste mit genau einer Referenzzone |
| `MaxLocationAge` | 900 s | Maximal zulässiges Standortalter, 30 bis 86.400 s |
| `MaxAccuracy` | 100 m | Maximal zulässiger positiver Genauigkeitsradius, 1 bis 10.000 m |
| `Hysteresis` | 25 m | Zusätzlicher Grenzpuffer, 0 bis 10.000 m; muss kleiner als jeder Zonenradius sein |
| `ConfirmationTime` | 30 s | Zonenwechsel erst mit weiterer Position nach mindestens dieser Zeit bestätigen; 0 deaktiviert die Verzögerung |
| `MaxSpeed` | 300 km/h | Unplausible Sprünge nach Abzug beider Genauigkeitsradien verwerfen; 0 deaktiviert diesen Filter, maximal 2.000 km/h |

Ein Standort gilt sicher als innerhalb einer Zone, wenn Entfernung plus
Genauigkeitsradius innerhalb von Radius minus Grenzpuffer liegt. Sicher
außerhalb liegt er erst, wenn Entfernung minus Genauigkeitsradius mindestens
Radius plus Grenzpuffer erreicht. Im Grenzbereich bleibt ein zuvor bestätigter
Zustand erhalten; ohne solchen Zustand lautet das Ergebnis **Unbekannt**.
Das stabilisiert die Zonenerkennung, verbessert aber nicht die GPS-Messung.

Die erste eindeutig auswertbare Position legt den Zustand fest. Danach benötigt
ein Wechsel eine neue, zeitlich spätere Position und die eingestellte
Bestätigungszeit. Wiederholtes Lesen desselben Standorts bestätigt keinen
Wechsel. Bei einem 300-Sekunden-Kontoabruf kann ein Wechsel deshalb länger als
30 Sekunden dauern. Die Library fordert weiterhin keine aktive Neuortung an.

Fehlende Position, fehlende oder zu große Unsicherheit, Apples Ungenauigkeits-
oder Veraltet-Kennzeichnung, überalterte Zeitstempel und unplausible Bewegungen
führen zu **Unbekannt**. Fehlende Kontodaten und entfernte Geräte ebenfalls.
Ein Datenverlust setzt den Aufenthalt zurück; ein alter Cachetreffer darf ihn
nicht erneut bestätigen. Erst eine neue belastbare Position ermöglicht die
Wiederaufnahme. Eine lange Beobachtungslücke zählt nicht als zusammenhängender
Aufenthalt. Für Flugreisen den Geschwindigkeitsfilter passend einstellen.

## Variablen

Alle Variablen sind ausschließlich lesbar.

| Ident | Typ | Bedeutung |
| --- | --- | --- |
| `Presence` | int | Klartextanzeige: -1 Unbekannt, 0 Abwesend, 1 Anwesend |
| `Zone` | string | Bestätigte Zone, Außerhalb aller Zonen oder Unbekannt |
| `ZoneID` | string | Stabile ID der aktuellen Zone; leer außerhalb bzw. bei Unbekannt |
| `Reason` | string | Verständliche Standortbewertung, etwa Grenzbereich oder ausstehende Bestätigung |
| `DataAvailable` | bool | Gerät in einem erfolgreich verfügbaren Kontocache vorhanden |
| `DistanceAvailable` | bool | Aktuelle Entfernung auswertbar |
| `Distance` | float | Luftlinienentfernung zum Mittelpunkt der Referenzzone in Metern |
| `StayAvailable` | bool | Bestätigter Aufenthalt innerhalb einer Zone vorhanden |
| `ZoneSince` | int | Unix-Zeit der ersten bestätigten Beobachtung des Aufenthalts, sonst 0 |
| `StayDuration` | int | Sekunden seit dieser Beobachtung, als Dauer formatiert; sonst 0 |
| `TransitionPending` | bool | Ein Zonenwechsel wartet auf Bestätigung |

Die Aufenthaltsdauer beschreibt beobachtete Zeit, nicht den garantiert exakten
Ankunftszeitpunkt. Bei bestätigtem Wechsel beginnt sie mit der bestätigenden
Position neu. Außerhalb aller Zonen oder bei Unbekannt sind `ZoneSince` und
`StayDuration` 0 und `StayAvailable` false. Nicht verfügbare Entfernungen werden
auf 0 gesetzt und mit `DistanceAvailable=false` gekennzeichnet; 0 allein ist
kein Anwesenheitsnachweis. In einem unklaren Grenzbereich kann die Entfernung
verfügbar sein, obwohl Anwesenheit noch unbekannt ist.

Der letzte Auswertungszustand wird über Neustarts gespeichert. Eine Änderung
von Konto, Gerät, Zonen oder GPS-Einstellungen beginnt eine neue Auswertung;
vorhandene Variablen-IDs bleiben erhalten.

## Aktualisierung und Status

Kontobenachrichtigungen und ein lokaler 15-Sekunden-Timer lesen nur den
vorhandenen Kontocache. Der Timer aktualisiert Dauer und Datenalter. Weder das
Öffnen des Formulars noch **Gespeicherten Standort auswerten** oder
`ICPR_Update(int $InstanceID): bool` starten einen Apple-Abruf.

`ICPR_Update` liefert true, wenn ein gültig konfiguriertes Gerät im verfügbaren
Kontocache gefunden wurde; Anwesenheit kann dabei mangels gutem Standort
**Unbekannt** sein. Bei deaktivierter Auswertung, fehlender Konfiguration oder
Kontofehlern lautet das Ergebnis false. Status 201 kennzeichnet fehlende bzw.
ungültige Konfiguration, 202 nicht verfügbare Kontodaten und 203 ein nicht mehr
gelistetes Gerät. Bei deaktivierter Auswertung ist der Timer aus.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
