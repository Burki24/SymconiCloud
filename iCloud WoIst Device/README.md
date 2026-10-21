# iCloud WoIst Device

Eine Instanz zeigt ein Apple-Gerät aus der WoIst-Geräteliste an. Voraussetzung ist Symcon 9 und ein angemeldetes iCloud-Konto dieser Library. Geräte vorzugsweise über den zugehörigen WoIst-Konfigurator anlegen.

## Konfiguration

`DeviceID` enthält die unveränderte Apple-Geräte-ID; Standard ist leer. Die Instanz muss physisch mit dem richtigen iCloud-Konto verbunden sein. Ein leerer Identifikator oder fehlendes Konto führt zu einem inaktiven Zustand. Die Instanz liest ausschließlich gespeicherte Daten des Kontos und empfängt dessen Aktualisierungen. Sie startet selbst keine Apple-Anmeldung oder Netzwerkabfrage.

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
| `Accuracy` | float | Standortgenauigkeit in Metern |
| `LocationTimestamp` | int | Unix-Zeit des Standorts |
| `LastUpdate` | int | Unix-Zeit des letzten erfolgreichen Geräteabrufs |

Bei fehlendem Standort bleiben die letzten gültigen Koordinaten und deren Zeit erhalten. `LocationAvailable` zeigt dann `false`. Bei fehlendem Akkustand bleibt ebenfalls der letzte Wert erhalten; `BatteryAvailable` zeigt dessen Nichtverfügbarkeit an. Bei Kontofehlern oder einem nicht mehr gelisteten Gerät werden Verfügbarkeitsanzeigen und Online auf `false` gesetzt; alte Werte und Zeitstempel bleiben zur Einordnung erhalten. Ein Wechsel der Geräte-ID oder Kontoverbindung setzt die bisherigen Gerätewerte zurück.

## Öffentliche Funktion

`ICWD_Update(int $InstanceID): bool` liest das Gerät aus dem Kontocache. `true` bedeutet, dass das Gerät in einer erfolgreich abgerufenen Liste vorhanden ist. Die Funktion und **Gespeicherte Kontodaten lesen** starten keinen Netzwerkabruf. Für neue Apple-Daten die Geräteabfrage im Konto oder Konfigurator verwenden.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
