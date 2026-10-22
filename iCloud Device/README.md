# iCloud Device / iCloud Gerät

Eine Instanz zeigt die Geräte-, Akku- und Standortdaten eines Apple-Gerätes aus dem Kontocache an. Voraussetzung ist Symcon 9 und ein angemeldetes iCloud-Konto dieser Library. Geräte vorzugsweise über den iCloud-Geräte-Konfigurator anlegen. Eine Karte ist nicht Bestandteil dieser Geräteinstanz; dafür gibt es das Modul [WoIst](../iCloud%20WoIst/README.md).

## Konfiguration

`DeviceID` enthält die unveränderte Apple-Geräte-ID; Standard ist leer. Die Instanz muss physisch mit dem richtigen iCloud-Konto verbunden sein. Ein leerer Identifikator oder fehlendes Konto führt zu einem inaktiven Zustand. Die Instanz liest ausschließlich gespeicherte Daten des Kontos und empfängt dessen Aktualisierungen. Sie startet selbst keine Apple-Anmeldung oder Netzwerkabfrage.

### Adresse aus WoIst

Für die Variable **Adresse** muss in einer [WoIst-Karte](../iCloud%20WoIst/README.md#optionale-adressauflösung)
am selben iCloud-Konto die Adressauflösung mit Nominatim oder Geoapify eingerichtet und aktiviert
sein. Die Geräteinstanz übernimmt ausschließlich die dort bereits aufgelöste
Adresse; sie benötigt keine eigene Serveradresse und startet keine zusätzliche
Anbieterabfrage oder einen eigenen Abfragetimer. Bei Geoapify gelten Tagesbudget
und Genauigkeitsregeln der ausgewählten WoIst-Instanz. Bei eigenen Anzeigen der
Adressvariable die Quellenhinweise von Geoapify und OpenStreetMap beibehalten.

Beide Anbieter liefern die kompakte Darstellung **Straße Hausnummer, PLZ Ort,
Land**. Die Gerätevariable übernimmt denselben Text wie die Karte, einschließlich
der von der Standortgenauigkeit abhängigen Detailstufe und des Ungefähr-Hinweises.

**WoIst-Adressquelle (optional)** (`AddressSource`, Standard `0`) kann leer
bleiben, wenn genau eine Karte mit aktivierter Adressauflösung an diesem Konto
vorhanden ist. Bei mehreren solchen Karten die gewünschte Instanz ausdrücklich
auswählen. Eine Quelle an einem anderen Konto wird nicht verwendet, auch wenn
die Geräte-ID identisch ist. Eine ungültige Quelle beeinträchtigt die übrigen
Gerätevariablen nicht.

Nach dem Modulupdate die Geräteinstanzkonfiguration einmal übernehmen, damit
die neue Variable angelegt wird. Vorhandene Cachetreffer erscheinen sofort;
später aufgelöste Adressen werden von WoIst automatisch an die Geräteinstanz
weitergegeben. Für die Übernahme ist keine Apple-Aktualisierung erforderlich.
Nach dem Löschen oder Umverbinden einer Quelle wird deren Zuordnung beim
nächsten Geräteupdate erneut geprüft.

## Variablen

Alle Variablen sind ausschließlich lesbar.

| Ident | Typ | Bedeutung / Einheit |
| --- | --- | --- |
| `DataAvailable` | bool | Gerät in der erfolgreich abgerufenen Kontoliste vorhanden |
| `Online` | bool | Von Apple gemeldeter Gerätestatus 200 |
| `DeviceName`, `Model`, `DeviceClass` | string | Gerätebezeichnung und Typ |
| `DeviceStatus` | int | Gerätestatus von Apple |
| `BatteryAvailable` | bool | Akkustand vorhanden |
| `BatteryLevel` | float | Akkustand in Prozent |
| `BatteryStatus` | string | Akkustatus von Apple |
| `LocationAvailable` | bool | Koordinaten in der letzten Antwort vorhanden |
| `LocationIsOld` | bool | Standort von Apple als veraltet gemeldet oder aktuell nicht verfügbar |
| `Latitude`, `Longitude` | float | Breitengrad / Längengrad in Grad |
| `Address` | string | Aufgelöste Adresse der aktuell verfügbaren Geräteposition aus dem ausgewählten WoIst-Cache |
| `Accuracy` | float | Standortgenauigkeit in Metern |
| `LocationTimestamp` | int | Unix-Zeit des Standorts |
| `LastUpdate` | int | Unix-Zeit des letzten erfolgreichen Geräteabrufs |

Bei fehlendem Standort bleiben die letzten gültigen Koordinaten und deren Zeit erhalten. `LocationAvailable` zeigt dann `false`. Bei fehlendem Akkustand bleibt ebenfalls der letzte Wert erhalten; `BatteryAvailable` zeigt dessen Nichtverfügbarkeit an. Bei Kontofehlern oder einem nicht mehr gelisteten Gerät werden Verfügbarkeitsanzeigen und Online auf `false` gesetzt; alte Werte und Zeitstempel bleiben zur Einordnung erhalten. Ein Wechsel der Geräte-ID oder Kontoverbindung setzt die bisherigen Gerätewerte zurück.

`Address` bleibt leer, wenn kein eindeutiger aktivierter Adresslieferant oder
kein passender Cachetreffer vorhanden ist. Die Adresse wird bei einem
Positionswechsel nicht von der vorherigen Position übernommen. Fehlende
Positionen, Kontofehler, entfernte Geräte und das Deaktivieren der Auflösung
leeren die Adresse bei der jeweiligen Aktualisierung. Eine veraltete, aber
weiterhin verfügbare Position kann eine Adresse besitzen; deren Alter zeigt
`LocationIsOld`. Die Adresse beschreibt das vom gewählten Anbieter gefundene nahegelegene
OSM-Objekt und garantiert keine genaue Hausnummer.

Die [Genauigkeitsregeln der WoIst-Adressauflösung](../iCloud%20WoIst/README.md#optionale-adressauflösung)
gelten auch für `Address`: Gebäude werden nur bis einschließlich 20 m angezeigt.
Bei geringerer Genauigkeit enthält die Variable die gröbere Adresse mit dem
Präfix **Ungefähr** und der jeweiligen Detailstufe. Unbekannte Genauigkeit wird
ausdrücklich benannt. Verbessert sich die Position, entfällt der Hinweis wieder.

## Öffentliche Funktion

`ICID_Update(int $InstanceID): bool` liest das Gerät aus dem Kontocache. `true` bedeutet, dass das Gerät in einer erfolgreich abgerufenen Liste vorhanden ist. Die Funktion und **Gespeicherte Kontodaten lesen** starten keinen Netzwerkabruf. Für neue Apple-Daten die Geräteabfrage im Konto oder Konfigurator verwenden.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
