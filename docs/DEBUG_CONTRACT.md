# Interner Debug-Vertrag von SymconiCloud

Stand: 28.10.2026. Maßgeblich ist `SymconDevelopment/standards/DEBUG_OUTPUT.md`.
Dieses Dokument beschreibt Implementierung und Prüfung, keine Anwenderbedienung.
Library- und Modul-READMEs enthalten keine Debug-/Diagnoseanleitung.

## Zuständigkeiten

Der bestehende zentrale `DebugFlowHelper` besitzt Korrelation, Start/Ende,
Callback-Ausführung, sichere Ausnahmebeschreibung und native Schreibereignisse.
Er verwendet `DebugHelper` für Formatierung, Maskierung und das 4096-Byte-Limit.
`libs/CloudDebug.php` enthält ausschließlich iCloud-spezifische deutsche
Ursachen, Ergebnisbedeutungen und sichere Kontextauswahl. Allgemeine
Maskierungs- oder Ablaufmechanik wird dort nicht dupliziert.

## Erfasster Umfang

108 libraryeigene Einstiege verwenden einen zentralen Callback-Ablauf:

| Quellbereich | Einstiege |
| --- | ---: |
| Kontomodul | 13 |
| Kontakte-Modul | 13 |
| Gerätemodul | 10 |
| Gerätekonfigurator | 8 |
| Fotos-Modul | 9 |
| Anwesenheitsmodul | 16 |
| WoIst-Modul | 12 |
| Fach-Traits einschließlich öffentlicher Exporte und eigener HTTP-Hooks | 27 |

Create, ApplyChanges, Destroy, Bedienung, Timer, Formulare und Exporte sind
einbezogen. Die reinen Getter GetCompatibleParents und GetDeviceVariableIdent
sind ausgenommen. Interne Rechenhelfer erhalten keine künstlichen Start-/Endpaare;
relevante Entscheidungen und Kommunikationsgrenzen sind gesondert instrumentiert.
Gemeinsame Designer-/SDK-Helper bleiben zentral. Innerhalb eines Modulaufrufs
gehören sie zum aktiven Vorgang; alte Debugfehler werden ohne ihre beliebige
Nutzlast in die sichere Struktur überführt.

Ein äußerer synchroner Aufruf beginnt einen neuen Vorgang. Verschachtelte
Aufrufe derselben Instanz teilen diesen und haben eigene Aufrufnummern.
Instanz-/Datenflussgrenzen übertragen keine Kennung automatisch; Quellen und
Ziele werden über ihre bereits bekannten Instanz-IDs zugeordnet. Einzelereignisse
außerhalb eines laufenden Aufrufs dürfen keinen abgeschlossenen Vorgang erben.
Argumentfreie Rückverfolgung ordnet Callback-Ereignisse ihrer Modulfunktion zu.

## Daten- und Verhaltensgrenzen

- Native Statusschreibvorgänge und Timeränderungen werden genau einmal ausgeführt.
- Apple-Sendeereignisse erhalten nur die fest definierte Operation, keine URL,
  Header, Zugangsdaten oder Nutzlast. HTTP-Ergebnis und bekannte Gründe werden erklärt.
- Formulardiagnose und vorhandene begrenzte Kontohistorie bleiben getrennt vom
  nativen Debugkanal; es entsteht kein neuer persistenter Debugspeicher.
- Kontakt-/Empfängerwerte, Namen, Koordinaten, Adressen, absolute Standortzeiten,
  Entfernungen, Fotos, Token und ungeprüfte Exception-Texte werden nicht übergeben.
- Sichere Werte haben Bedeutung/Einheit; unbekannte dreistufige Geräteflags werden
  erklärt. Ausgaben unterscheiden Cache, neue Abfrage, Annahme und Gerätewirkung.
- Void-Aufrufe behaupten keinen fachlichen Erfolg. Für boolesche Ergebnisse gelten
  die jeweiligen Fachverträge, insbesondere bei Suchton, Verloren-Modus,
  Empfängerübernahme und Adressauflösung. Texte/Strukturen erscheinen nur als Umfang.
- Debugausfall darf Rückgabewert, ursprüngliche Ausnahme, Kontoschutz, Netzwerk-
  und Geräteaktionen nicht ändern. Zusätzliche Messwertabfragen sind unzulässig;
  Instanz-IDs dienen ausschließlich als vorhandene Zuordnungsmetadaten.

## Nachweise und Distribution

`tests/debug-output.php` prüft alle sieben realen Module, Lifecycle-IDs,
verschachtelte Korrelation, reguläre Abbrüche/Ausnahmen, HTTP-Ablehnung,
synthetische 2FA, Pause und einen defekten Debugkanal.
`tests/debug-data.php` verwendet die realen Kontakt-/Übernahme-Fixtures für
Geheimnisschutz und prüft die Instrumentierung öffentlicher Methoden/Fach-Traits.
`tests/debug-presence.php` schließt die bestehenden Anwesenheitsgruppen-Tests ein
und prüft GPS-Erklärungen, Einheiten sowie geschützte Standort-/Namensinhalte.
Die zentrale Suite sichert Callback-Ergebnisse, Ausnahmeidentität, Standalone-
Korrelation und genau einen nativen Schreibvorgang ab.

Der vollständige Integrationsstand wird in einer temporären Kopie über die
regulären Metadaten-/Bundle-Generatoren hergestellt. Keine vorläufigen
Helper-Dateien verbleiben in libs/helper. Der neue DebugFlowHelper-Vertrag muss
zuerst zentral freigegeben und regulär verteilt werden. Danach den Consumer
pullen und die Library veröffentlichen; Tests werden nicht für alte Helperstände
abgeschwächt. Die reale Debug-Fenster-Abnahme und Laufzeitkosten nach dem
Benutzerupdate sind separat offen; Offline-Nachweise ersetzen sie nicht.
