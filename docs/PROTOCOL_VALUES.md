# Gerätewerte: Herkunft und synthetische Referenzen

Diese Referenz trennt den bestehenden Library-Vertrag von einer Garantie des
Apple-Webdienstes. Es wurde keine Implementierung aus anderen Projekten kopiert.
Es wurden keine Apple-Anmeldung und keine reale Geräteabfrage ausgeführt.

## Quellenlage

Das [PyiCloud-README, Stand 592ff464](https://github.com/picklepete/pyicloud/blob/592ff464c53830395375462e645f53820ef1e9af/README.rst)
zeigt die Rohfelder `batteryLevel`, `latitude`, `longitude`, `timeStamp`,
`horizontalAccuracy` und ein boolesches `isOld`. Die zugehörige
[FindMy-Protokollreferenz](https://github.com/picklepete/pyicloud/blob/592ff464c53830395375462e645f53820ef1e9af/pyicloud/services/findmyiphone.py)
ordnet sie der Geräte-/Standortabfrage zu. Dies ist eine externe
Protokollbeobachtung, keine offizielle Apple-Spezifikation und kein Beleg für
die Verfügbarkeit aller Felder auf jedem Gerät. Die Beispiele wurden nur
inhaltlich ausgewertet; Testwerte sind unabhängig gewählte synthetische Daten.

| Rohfeld | Bestehender Library-Vertrag | Herleitung und Grenze |
| --- | --- | --- |
| `batteryLevel` | Endlicher Anteil 0–1; Gerätevariable 0–100 Prozent | Der Referenzwert liegt zwischen 0 und 1. Die Interpretation als Anteil bleibt eine ausdrücklich gekennzeichnete Protokollannahme. 0, 0,5 und 1 ergeben mathematisch 0, 50 und 100 Prozent. |
| `timeStamp` | Millisekunden seit Unix-Epoche, im Cache volle Unix-Sekunden | Die Referenz zeigt einen 13-stelligen Wert. Die Millisekundeninterpretation bleibt eine Protokollannahme. Der unabhängig gewählte Zeitpunkt 1700000001250 ms entspricht 1700000001,250 s; gespeichert werden 1700000001 volle Sekunden. |
| `latitude`, `longitude` | Dezimalgrad, Bereiche −90–90 und −180–180 | Geografische Koordinatenkonvention; keine Umrechnung. Nullkoordinaten sind gültig. |
| `horizontalAccuracy` | Nicht negativer endlicher Radius in Metern oder `null` | Metrische Interpretation ist der bestehende, noch nicht durch eine offizielle Apple-Spezifikation abgesicherte Protokollvertrag. Es erfolgt keine Skalierung. Fehlend ist nicht 0. |
| `isOld` | Boolean; nur explizites `false` bedeutet nicht als alt markiert | Der Boolean-Typ ist in der Referenz sichtbar. Für fehlende/ungültige Felder gilt die eigene konservative Library-Regel `true`, keine behauptete Apple-Semantik. Zeitpunkt und Altersgrenzen bleiben zusätzlich relevant. |

## Nachweis und offene Annahmen

`tests/fixtures/device-units.json` enthält handgerechnete Erwartungswerte,
einschließlich echter Nullwerte und Millisekundenbruchteil. `tests/device-details.php`
prüft sie über den synthetischen Apple-Transport, die tatsächliche Normalisierung
und die Geräteinstanz. Erwartungen werden nicht mit dem getesteten Algorithmus
erzeugt. Ungültige/fehlende Werte, Wiederkehr, Standortverlust und Benutzer-Metadaten
werden separat geprüft. Die unabhängig erzeugten SRP-Vektoren bleiben unter
`tests/generate-srp-fixtures.py` dokumentiert.

Eine offizielle Bestätigung von Akkuskala, Zeitbasis und Genauigkeitseinheit
durch Apple liegt nicht vor. Diese Unsicherheit ist kein Anlass, den bestehenden
Wertevertrag spekulativ zu ändern. Abweichende reale Antworten benötigen einen
gesondert beauftragten, datensparsamen Praxisnachweis und eine Kompatibilitätsprüfung.
Aus dieser Protokollreferenz werden keine Aussagen über Symcon-Fehlerweitergabe,
Rückgabelimits oder neue Darstellungsparameter abgeleitet. Dafür bleibt ein
ausdrücklicher Nachweis für Symcon 9.0 erforderlich.
