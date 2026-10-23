# iCloud Device Configurator / iCloud Geräte Konfigurator

Der Konfigurator legt lesende iCloud-Geräteinstanzen aus der gespeicherten Geräteliste eines iCloud-Kontos an. Voraussetzung ist Symcon 9 und ein eingerichtetes iCloud-Konto aus dieser Library.

## Einrichtung und Bedienung

Den Konfigurator mit dem gewünschten iCloud-Konto verbinden. Die Anmeldung und Zwei-Faktor-Bestätigung erfolgen im Konto. Anschließend dort oder über **Geräte bei Apple aktualisieren** die Geräteliste abrufen.

Das Öffnen des Formulars und **Gespeicherte Liste neu laden** lesen ausschließlich den Kontocache. **Geräte bei Apple aktualisieren** startet einen lesenden Abruf bei Apple. Die Liste zeigt vorhandene Instanzen nur dann als zugeordnet an, wenn Geräte-ID und physische Kontoverbindung übereinstimmen. Symcon verbindet neu angelegte Geräte bevorzugt mit dem Konto dieses Konfigurators; die Verbindung nach der Anlage prüfen, insbesondere bei mehreren Konten.

Unter **Anwesenheitsgruppen** bietet der Konfigurator eine gemeinsame
[iCloud-Anwesenheitsinstanz](../iCloud%20Presence/README.md) für das Konto an.
Vorhandene Gruppen werden anhand ihrer Kontoverbindung zugeordnet, nicht je
Gerät mehrfach aufgelistet. Ist noch keine Gruppe vorhanden, lässt sich eine
leere Gruppe erstellen. Anschließend die gewünschten Geräte und gemeinsamen
Zonen mit genau einer Referenzzone in dieser Instanz auswählen. Die Erstellung
startet keine Apple-Abfrage. Mehrere bereits vorhandene Gruppen bleiben erhalten.

## Öffentliche Funktionen

| Funktion | Rückgabe | Wirkung |
| --- | --- | --- |
| `ICDC_Reload(int $InstanceID)` | bool | Gespeicherte Liste lesen und Formular aktualisieren |
| `ICDC_RefreshDevices(int $InstanceID)` | bool | Geräte bei Apple über das verbundene Konto aktualisieren |

`false` bedeutet fehlende Kontoverbindung oder nicht verfügbare Kontodaten. Die Instanz besitzt keine Statusvariablen.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
