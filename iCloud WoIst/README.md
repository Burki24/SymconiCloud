# iCloud WoIst

WoIst zeigt die Positionen der Geräte eines iCloud-Kontos gemeinsam auf einer interaktiven Karte. Einzelne iCloud-Geräteinstanzen sind dafür nicht erforderlich. Voraussetzung sind Symcon 9.0 und ein angemeldetes iCloud-Konto dieser Library.

## Einrichtung

1. **iCloud WoIst Karte** anlegen und mit dem gewünschten iCloud-Konto verbinden.
2. Die Instanz zur Symcon-Kachelvisualisierung hinzufügen. Eine Instanz zeigt jeweils alle Geräte ihres verbundenen Kontos.
3. Ein Gerät aus der Liste wählen, um seine Position zu öffnen. **Alle Positionen anzeigen** passt den Kartenausschnitt an die verfügbaren Positionen an.

Die Karte erhält neue Daten automatisch vom Konto. **Bei Apple aktualisieren** löst eine ausdrückliche Geräteabfrage über das Konto aus und beachtet dessen Anmeldestatus und Wartezeiten. Das Öffnen der Karte oder des Formulars liest nur den Kontocache und löst keine Apple-Anmeldung aus.

## Daten und Aktualität

Die Karte verwendet die von Apple zuletzt gelieferten Koordinaten. Aktuelle und ältere Positionen sind unterschiedlich markiert. Zu jeder Position werden Zeitpunkt und, soweit verfügbar, Genauigkeit angezeigt. Geräte ohne Position bleiben in der Liste sichtbar, erhalten aber keinen erfundenen Kartenpunkt. Offline-Geräte können eine letzte bekannte Position besitzen.

Ein aktueller Kontoabruf bedeutet nicht automatisch eine aktuelle Geräteposition. Nach Ablauf der Altersgrenze oder bei Kontofehlern gelten angezeigte Positionen als letzte bekannte Standorte. Die vorhandene Kontoabfrage fordert keine aktive Neuortung an; eine Live-GPS-Verfolgung oder die vollständige Funktionalität der Apple-App wird nicht zugesagt.

## Konfiguration

| Eigenschaft | Standard | Bedeutung |
| --- | --- | --- |
| `StaleAfter` | 900 | Positionen nach dieser Anzahl Sekunden als älter markieren; 60 bis 86400 |
| `TileURL` | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` | HTTPS-URL des Kartenanbieters mit den drei Platzhaltern |
| `TileAttribution` | © OpenStreetMap contributors | Sichtbare Quellenangabe des Kartenanbieters |

OpenStreetMap ist der Standardhintergrund. Die Kartendarstellung benötigt Internetzugriff im verwendeten Browser. Dieser lädt die Kacheln für den sichtbaren Kartenausschnitt beim Anbieter; der Anbieter erhält damit IP-Adresse und angefragte Kartenregion. Gerätenamen, Apple-Zugangsdaten und Tokens werden nicht an ihn gesendet. Bei eigenem Kartenanbieter dessen Bedingungen und Quellenangaben beachten. Leaflet wird lokal mit der Library ausgeliefert; es wird kein JavaScript von einem CDN nachgeladen.

Ein eigener Apple-Maps-Hintergrund ist nicht enthalten. Apple MapKit JS benötigt einen separaten Maps-Token aus einem Apple-Developer-Account; die iCloud-Anmeldung ersetzt diesen nicht.

## Öffentliche Funktionen

| Funktion | Rückgabe | Wirkung |
| --- | --- | --- |
| `ICWI_Update(int $InstanceID)` | bool | Kontocache lesen und offene Karten aktualisieren |
| `ICWI_RefreshDevices(int $InstanceID)` | bool | Einmalige ausdrückliche Abfrage über das Konto |
| `ICWI_GetState(int $InstanceID)` | string | JSON mit Kartendaten, Beschriftungen und Kartenoptionen aus dem Cache |

Das Modul erzeugt keine Gerätevariablen. Status 201 bezeichnet ein fehlendes Konto, 202 nicht verfügbare Kontodaten und 203 eine ungültige Kartenkonfiguration. Alte Positionen können bei einem Kontofehler weiterhin zur Einordnung angezeigt werden.

## Lizenz

Eigenes Modul: [PolyForm Noncommercial License 1.0.0](../LICENSE). Leaflet 1.9.4: BSD-2-Clause, siehe [Leaflet-Lizenz](../libs/vendor/leaflet/LICENSE). Kartendaten: [© OpenStreetMap contributors](https://www.openstreetmap.org/copyright).
