# iCloud Geräte Konfigurator

Der Konfigurator ist die zentrale Auswahl zum Anlegen von
[iCloud-Geräteinstanzen](../iCloud%20Device/README.md) und
[Anwesenheitsgruppen](../iCloud%20Presence/README.md). Er zeigt die Geräteliste
genau eines verbundenen iCloud-Kontos. Die Anmeldung erfolgt ausschließlich
im [iCloud Konto](../iCloud%20Account/README.md).

## Voraussetzungen

- Symcon 9.0 und die installierte Library SymconiCloud.
- Ein eingerichtetes, angemeldetes iCloud-Konto dieser Library mit abgerufener
  Geräteliste. Für das erste Einrichten die Kontoanleitung verwenden.

## Geräte anlegen

1. Eine Instanz **iCloud Geräte Konfigurator** anlegen.
2. Das gewünschte **iCloud Konto** als übergeordnete Instanz verbinden und
   die Konfiguration übernehmen. Der Ordner im Objektbaum ist dafür unerheblich.
3. Das Formular öffnen. Unter **Geräte** stehen Gerätename und Modell aus
   der gespeicherten Kontoliste.
4. Das gewünschte Gerät markieren und **Erstellen** wählen. Die neue Instanz
   **iCloud Gerät** erhält die richtige Geräte-ID automatisch; diese muss
   nicht von Hand aus iCloud kopiert werden.
5. Die neue Geräteinstanz öffnen, die Verbindung zu demselben Konto prüfen
   und ihre Anleitung zur weiteren Nutzung beachten.

Für ein weiteres Gerät die Auswahl und Erstellung wiederholen. Bereits
vorhandene Geräteinstanzen werden zugeordnet und können über den Konfigurator
geöffnet werden. Die Zuordnung verwendet Geräte-ID **und** Kontoverbindung,
nicht den sichtbaren Namen. Gleiche Gerätenamen oder Geräte an einem anderen
Konto werden dadurch nicht versehentlich gleichgesetzt.

Symcon verbindet neue Geräte bevorzugt mit dem Konto dieses Konfigurators.
Die Verbindung nach der Anlage besonders dann prüfen, wenn mehrere Konten
vorhanden sind. Jedes Konto bekommt seinen eigenen Konfigurator.

## Gespeicherte Liste oder neue Apple-Daten?

| Bedienung | Wirkung |
| --- | --- |
| Formular öffnen / Gespeicherte Liste neu laden | Bereits vorhandene Daten aus dem Konto anzeigen; keine Anfrage an Apple |
| Geräte bei Apple aktualisieren | Neue Geräteliste ausdrücklich über das Konto abrufen; dessen Abfragepause und Wartefristen gelten weiterhin |

**Nicht in aktueller Liste** bedeutet, dass eine schon angelegte Geräteinstanz
zum Konto gehört, ihr Gerät aber im aktuellen Datenbestand fehlt. Die Instanz
bleibt zur weiteren Verwendung erhalten. Eine fehlende Position ist dagegen
kein Grund, dass ein Gerät aus der Geräteliste verschwinden muss.

Bei Status **201** zuerst ein Konto verbinden. Bei Status **202** im Konto
Anmeldestatus, Datenbestand und Hinweise prüfen. Der Konfigurator selbst meldet
sich nicht bei Apple an und besitzt keine Geräte- oder Standortvariablen.

## Anwesenheitsgruppe anlegen

Unter **Anwesenheitsgruppen** bietet der Konfigurator eine gemeinsame
[iCloud-Anwesenheitsinstanz](../iCloud%20Presence/README.md) für das Konto an.
Vorhandene Gruppen werden anhand ihrer Kontoverbindung zugeordnet, nicht je
Gerät mehrfach aufgelistet. Ist noch keine Gruppe vorhanden, lässt sich eine
leere Gruppe erstellen. Anschließend die gewünschten Geräte und gemeinsamen
Zonen mit genau einer Referenzzone in dieser Instanz auswählen. Die Erstellung
startet keine Apple-Abfrage. Mehrere bereits vorhandene Gruppen bleiben erhalten.

Vorhandene Anwesenheitsgruppen werden ohne irreführende rote Hinterlegung
zugeordnet. Hinweise auf unvollständige Konfiguration, fehlende Kontodaten oder
fehlende Geräte stehen in der Spalte **Auswertung**. Das Öffnen oder Neuladen
des Konfigurators verändert weder Geräteauswahl noch Zonen einer Gruppe.

**Eine Gruppe genügt für mehrere Geräte und gemeinsame Zonen.** Für Anwesenheit
müssen vorher keine einzelnen Geräteinstanzen angelegt werden. Auch
[WoIst-Karte](../iCloud%20WoIst/README.md) und
[Foto-Widget](../iCloud%20Photos/README.md) werden als eigene Module angelegt;
der Konfigurator erstellt keine Karte und keine Foto-Diashow.

## Öffentliche Funktionen

| Funktion | Rückgabe | Wirkung |
| --- | --- | --- |
| `ICDC_Reload(int $InstanceID)` | bool | Gespeicherte Liste lesen und Formular aktualisieren |
| `ICDC_RefreshDevices(int $InstanceID)` | bool | Geräte bei Apple über das verbundene Konto aktualisieren |

`false` bedeutet fehlende Kontoverbindung oder nicht verfügbare Kontodaten. Die Instanz besitzt keine Statusvariablen.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
