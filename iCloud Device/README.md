# iCloud Device / iCloud Gerät

Eine Instanz zeigt die Geräte-, Akku- und Standortdaten eines Apple-Gerätes aus dem Kontocache an. Voraussetzung ist Symcon 9 und ein angemeldetes iCloud-Konto dieser Library. Geräte vorzugsweise über den iCloud-Geräte-Konfigurator anlegen. Eine Karte ist nicht Bestandteil dieser Geräteinstanz; dafür gibt es das Modul [WoIst](../iCloud%20WoIst/README.md).

## Konfiguration

`DeviceID` enthält die unveränderte Apple-Geräte-ID; Standard ist leer. Die Instanz muss physisch mit dem richtigen iCloud-Konto verbunden sein. Ein leerer Identifikator oder fehlendes Konto führt zu einem inaktiven Zustand. Die normale Datenaktualisierung liest ausschließlich gespeicherte Daten des Kontos und empfängt dessen Aktualisierungen. Apple-Anmeldung und Netzwerkzugriff bleiben im Kontomodul; nur ausdrücklich ausgelöste Geräteaktionen senden einen zusätzlichen Auftrag.

### Handy suchen

Die Aktionsvariable **Handy suchen** (`PlaySound`) stellt in der Kachelvisualisierung
einen Taster **Ton abspielen** bereit. Nach dem Modulupdate einmal die
Instanzkonfiguration übernehmen, damit die Variable angelegt wird. Diese Variable
oder eine Verknüpfung darauf als eigene Kachel hinzufügen und die Darstellung
**Aufzählung** verwenden; sie ist bereits vom Modul voreingestellt. In der
Listendarstellung der Geräteinstanz ist die Aktion ebenfalls bedienbar.
Ein zusätzliches Aktionsskript ist nicht erforderlich.

Der Taster und der Formularbutton **Handy suchen (Ton abspielen)** senden
denselben einmaligen Suchton-Auftrag für das
konfigurierte Gerät über dessen verbundenes iCloud-Konto. Das Gerät muss im
Cache dieses angemeldeten Kontos vorhanden sein. Der Button verwendet immer
die gespeicherte Gerätezuordnung; eine andere Ziel-ID kann beim Aufruf nicht
übergeben werden.

Die Rückmeldung im Formular unterscheidet einen von Apple angenommenen Auftrag
von einer fehlgeschlagenen oder nicht bestätigten Anfrage. Eine Annahme ist kein
Nachweis, dass das Gerät bereits hörbar klingelt. Ein nicht erreichbares Gerät
kann den Ton später beim Wiederverbinden abspielen. Den Ton bei Bedarf am
Gerät beenden; Apple kann eine Bestätigungs-E-Mail senden.

Bei Fehlern wird der Auftrag nicht automatisch erneut gesendet. Insbesondere
nach einem Verbindungsabbruch zuerst das Gerät prüfen, bevor erneut ausgelöst
wird. Eine abgelaufene Sitzung erfordert zunächst die Wiederherstellung der
Kontositzung; die Tonaktion startet keine Passwortanmeldung. Apple-Sperrfristen
gelten gemeinsam für alle Anfragen dieses Kontos.

Formularöffnung, Konfigurationsübernahme, Cacheupdates und Timer spielen keinen
Ton ab. Die Gerätevariablen bleiben lesbar und behalten ihre bisherigen IDs.

### Verloren-Modus

Im Instanzformular unter **Verloren-Modus** das angezeigte Zielgerät prüfen.
Bei Bedarf eine **Rückrufnummer** und eine **Nachricht auf dem Sperrbildschirm**
(höchstens 160 Zeichen, einzeilig) eingeben. Die Angaben werden beim Auslösen
an Apple gesendet und können auf dem verlorenen Gerät erscheinen.

**Neuer Gerätecode** bleibt bei einem bereits vorhandenen Code leer. Nur wenn
Apple für das Gerät einen neuen Code benötigt, einen vier- oder sechsstelligen
Zifferncode eingeben und sicher zum späteren Entsperren aufbewahren. Apple kann
für bestimmte Geräte oder Familienfreigaben weitere Schritte verlangen; diese
gegebenenfalls direkt in **Wo ist?** durchführen. Das Modul umgeht keine
Apple-Zugriffsbeschränkungen.

**Verloren-Modus aktivieren** öffnet vor jedem Auftrag einen Ja/Nein-Dialog.
Erst **Ja** sendet die Aktivierung für das gespeicherte Gerät. Der Modus kann
das Gerät sperren und Apple Pay aussetzen. Bei nicht erreichbaren Geräten kann
die Aktivierung später erfolgen. Abbrechen oder **Nein** sendet keinen Auftrag.
Die Felder sind flüchtige Aktionsdaten, keine gespeicherten Einstellungen;
nach einem Aufruf werden sie geleert. Kontaktangaben, Gerätecode und rohe
Apple-Antworten werden nicht im Modulzustand oder in Diagnosemeldungen abgelegt.

Die Aktion verlangt ein angemeldetes Konto und ein Gerät aus dessen Cache mit
ausdrücklich gemeldeter Verloren-Modus-Unterstützung. Fehlende oder unbekannte
Unterstützung und ein bereits aktivierter Modus werden abgewiesen. Das Formular
meldet Annahme oder Fehler. **Verloren-Modus aktiviert** (`LostModeEnabled`)
wird nicht anhand der Auftragsannahme gesetzt, sondern erst mit dem nächsten
regulären Kontoupdate aus dem gemeldeten Gerätezustand übernommen.

Bei Fehlern oder unbestätigtem Ergebnis gibt es keine automatische Wiederholung.
Vor einem weiteren Auftrag den Zustand in **Wo ist?** prüfen. Sitzung,
Kontosperrfristen und Gerätezuordnung gelten wie beim Suchton. Formularöffnung,
Konfigurationsübernahme, Cacheupdates und Timer aktivieren den Modus nicht.
Es gibt keinen unbestätigten Verloren-Modus-Taster in der Kachel.

Zum Deaktivieren **Wo ist?** verwenden oder das Gerät gemäß Apple entsperren;
das Modul bietet in diesem Stand ausschließlich die bestätigte Aktivierung.
Siehe [Apples Anleitung zum Verloren-Modus](https://support.apple.com/de-de/guide/icloud/mmfc0f0165/icloud).

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

Die Statusvariablen sind ausschließlich lesbar. **Handy suchen** ist die einzige
Aktionsvariable; ihr Wert ist kein Wiedergabestatus.

| Ident | Typ | Bedeutung / Einheit |
| --- | --- | --- |
| `PlaySound` | int, Aktion | Taster **Ton abspielen**: `RequestAction($VariablenID, 1)` sendet einmalig; `0` ist der neutrale Wert und löst keinen Auftrag aus |
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
| `LowPowerMode` | int | Stromsparmodus laut Apple |
| `LocationEnabled`, `LocationCapable` | int | Ortung aktiviert / vom Gerät unterstützt |
| `IsLocating` | int | Ortung läuft laut Apple |
| `LostModeEnabled`, `LostModeCapable` | int | Verloren-Modus aktiviert / vom Gerät unterstützt |
| `ActivationLocked` | int | Aktivierungssperre laut Apple |
| `FamilyShared` | int | Kennzeichnung der Familienfreigabe laut Apple |
| `LocationIsInaccurate` | int | Vorhandener Standort von Apple ausdrücklich als ungenau gekennzeichnet |
| `LocationFinished` | int | Ortung für den vorhandenen Standort laut Apple abgeschlossen |
| `ModelIdentifier` | string | Technische Modellkennung oder **Nicht verfügbar** |

Die zehn zusätzlichen Statusvariablen verwenden drei klar unterscheidbare Werte:
**-1 = Nicht verfügbar**, **0 = Nein**, **1 = Ja**. Für Skripte ausdrücklich
auf `0` oder `1` prüfen; eine Umwandlung in Boolean würde `-1` falsch auswerten.
Fehlende, leere oder ungültige Angaben werden nicht als Nein interpretiert.
Die Anzeige übernimmt die von Apple gelieferten Kennzeichnungen; insbesondere
belegt ein fehlender Wert weder Unterstützung noch Aktivierung einer Funktion.
`LocationIsInaccurate` ist Apples zusätzliche Kennzeichnung und ersetzt nicht
die metrische `Accuracy` oder die Genauigkeitsregeln der Adressauflösung.

In Symcon erscheinen die lesbaren Texte **Nicht verfügbar**, **Nein** und **Ja**.
Die numerischen Werte bleiben für Skripte und Archivierung erhalten. Die
Darstellung verwendet den zentralen VariablePresentationHelper mit nativen
Zahlenintervallen; nach dessen automatischer Verteilung die
Geräteinstanzkonfiguration erneut übernehmen.

Nach dem Update die Geräteinstanzkonfiguration übernehmen. Alte Kontocaches
bleiben lesbar; die neuen Angaben zeigen zunächst **Nicht verfügbar**, bis die
nächste erfolgreiche Geräteabfrage im Konto die zusätzlichen Felder liefert.
Die Geräteinstanz selbst startet für diese Statusanzeigen keinen Netzwerkabruf.
Suchton und bestätigte Aktivierung des Verloren-Modus sind getrennte,
ausdrücklich auszulösende Aktionen.

Anders als die bisherigen historischen Koordinaten werden die neuen
Statusangaben und die technische Modellkennung bei fehlenden Daten, abgelaufener
Kontositzung, entferntem Gerät oder Wechsel der Gerätezuordnung unmittelbar
auf **Nicht verfügbar** gesetzt. Fehlt nur die Position, bleiben die übrigen
Geräteangaben nutzbar; nur die beiden zusätzlichen Standortstatus sind unbekannt.

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

## Öffentliche Funktionen

`ICID_Update(int $InstanceID): bool` liest das Gerät aus dem Kontocache. `true` bedeutet, dass das Gerät in einer erfolgreich abgerufenen Liste vorhanden ist. Die Funktion und **Gespeicherte Kontodaten lesen** starten keinen Netzwerkabruf. Für neue Apple-Daten die Geräteabfrage im Konto oder Konfigurator verwenden.

`ICID_PlaySound(int $InstanceID): bool` sendet den Suchton-Auftrag für genau
dieses Gerät. `true` bedeutet, dass Apple den Auftrag angenommen hat; bei
Ablehnung, fehlender Sitzung, falscher Zuordnung oder einem Transportfehler
wird `false` geliefert. Ein Formularhinweis enthält das letzte Aktionsergebnis.
Das Skript kann die Funktion gezielt aus einer Automation aufrufen.

Alternativ `RequestAction($PlaySoundVariablenID, 1)` für die Aktionsvariable
verwenden. Diese bleibt nach Erfolg und Fehler auf `0`, damit jeder weitere
Tastendruck wieder einen ausdrücklichen Auftrag auslösen kann. Fehler werden
bei der Variablenaktion an den Client zurückgemeldet; der letzte Hinweis bleibt
auch im Instanzformular sichtbar. Ein direktes Setzen eines Wertes ersetzt
keinen Aktionsaufruf und startet keine Wiedergabe.

`ICID_ActivateLostMode(int $InstanceID, bool $Confirmed, string $PhoneNumber,
string $Message, string $NewPasscode): bool` aktiviert den Verloren-Modus für
das konfigurierte Gerät. `$Confirmed` ist bei jedem Aufruf ausdrücklich
anzugeben; `false` führt keine Anfrage aus. Die übrigen Parameter können leer
sein. `true` als Ergebnis bestätigt ausschließlich die Annahme durch Apple.
Skripte müssen die Bestätigung vor dem Aufruf selbst einholen; der PHP-Aufruf
öffnet keinen Dialog. Kontaktangaben und Code nicht in Skripten fest hinterlegen
oder protokollieren. Bestehende Variablen und ihre IDs bleiben erhalten.

## Lizenz

PolyForm Noncommercial License 1.0.0; siehe [LICENSE](../LICENSE).
