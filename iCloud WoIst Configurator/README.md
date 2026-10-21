# iCloud WoIst Configurator

Der Konfigurator legt lesende WoIst-Geräteinstanzen aus der gespeicherten Geräteliste eines iCloud-Kontos an. Voraussetzung ist Symcon 9 und ein eingerichtetes iCloud-Konto aus dieser Library.

## Einrichtung und Bedienung

Den Konfigurator mit dem gewünschten iCloud-Konto verbinden. Die Anmeldung und Zwei-Faktor-Bestätigung erfolgen im Konto. Anschließend dort oder über **Geräte bei Apple aktualisieren** die Geräteliste abrufen.

Das Öffnen des Formulars und **Gespeicherte Liste neu laden** lesen ausschließlich den Kontocache. **Geräte bei Apple aktualisieren** startet einen lesenden Abruf bei Apple. Die Liste zeigt vorhandene Instanzen nur dann als zugeordnet an, wenn Geräte-ID und physische Kontoverbindung übereinstimmen. Symcon verbindet neu angelegte Geräte bevorzugt mit dem Konto dieses Konfigurators; die Verbindung nach der Anlage prüfen, insbesondere bei mehreren Konten.

## Öffentliche Funktionen

| Funktion | Rückgabe | Wirkung |
| --- | --- | --- |
| `ICWC_Reload(int $InstanceID)` | bool | Gespeicherte Liste lesen und Formular aktualisieren |
| `ICWC_RefreshDevices(int $InstanceID)` | bool | Geräte bei Apple über das verbundene Konto aktualisieren |

`false` bedeutet fehlende Kontoverbindung oder nicht verfügbare Kontodaten. Die Instanz besitzt keine Statusvariablen.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
