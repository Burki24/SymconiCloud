# Beta-Release vorbereiten

Dieses Verfahren ergänzt die [Einreichungsdaten](STORE_SUBMISSION.md) und die
[automatische Versionierung](automatic-versioning.md). Es folgt dem
Release-Verfahren der anderen Libraries, unterscheidet aber den hier
vorbereiteten **Beta-Kanal** vom späteren Stable-Release auf `main`.

## Kandidat bestimmen

1. Beschreibung und Versionsinformation in `STORE_SUBMISSION.md` mit dem
   tatsächlichen Funktionsumfang und der `CHANGELOG.md` abgleichen.
2. Der Benutzer committet und pusht die Vorbereitung über GitHub Desktop.
3. Den erfolgreichen Lauf **Update library metadata** und den resultierenden
   Bot-Commit auf `dev` abwarten. Den Bot-Commit lokal pullen.
4. `Tests` und `Check Style` müssen für exakt diesen letzten Commit erfolgreich
   sein. Ein grüner Quellcommit vor der Metadatenänderung genügt nicht.
5. Den vollständigen SHA dieses Commits notieren und Version, Build und Datum
   aus genau seiner `library.json` übernehmen. Nichts von Hand hochzählen und
   keinen noch laufenden oder fehlgeschlagenen Stand auswählen.
6. Dokumentationslink, Kategorien, Bundle-ID und deutsche Lokalisierung im
   Entwicklerkonto kontrollieren und die Vorlage speichern.

Für die Beta darf der ausdrücklich geprüfte Commit aus `dev` ausgewählt werden;
die Symcon-Commit-Auswahl unterstützt Branches. Ein Merge nach `main`, Tag,
GitHub-Release oder zusätzliches ZIP ist für diese Vorbereitung nicht nötig
und wurde nicht ausgeführt. Der spätere Stable-Prozess wird getrennt
beauftragt. `dev` bleibt der Entwicklungszweig.

## Reproduzierbare Kontrolle

Lokal lassen sich der ausgecheckte Kandidat und seine Metadaten lesen:

```powershell
git status --short
git rev-parse HEAD
Get-Content library.json
```

Der Arbeitsbaum muss zum ausgewählten Commit passen. Wird nach dessen Auswahl
weiterer Code geändert, die Freigabe erneut auf den neuen Kandidaten beziehen.
Die Store-Einreichung verwendet einen festen Commit-SHA, keinen beweglichen
Branch-Verweis. Der aktuelle Dokumentationslink auf `dev` wird bei späterer
Entwicklung mitgeführt; für eine unveränderliche Referenz kann im Store der
README-Link mit dem ausgewählten vollständigen Commit-SHA verwendet werden.

Die üblichen lokalen Checks bleiben `php tests/run.php`,
`php tests/validate_structure.php`, JSON-Stilprüfung und PHP-CS-Fixer im
Prüfmodus mit der offiziellen `.style`-Konfiguration. Für Bildprüfungen werden
GD/EXIF benötigt, für die Visualisierungstests Node.js. Die Praxisabnahmen
werden aus dem Projektkontext übernommen, soweit der Kandidat das geprüfte
Verhalten nicht verändert; Dokumentationsänderungen lösen keine Apple-Anmeldung
oder erneute schreibende Prüfung auf einem echten Gerät aus.

## Veröffentlichung und Kanalwechsel

**Einreichen** veröffentlicht auf Beta unmittelbar. Dafür ist ein gesonderter
Auftrag erforderlich; eine gespeicherte Vorlage ist noch keine Veröffentlichung.
Version, Build, Datum, vollständigen Commit, Kanal und Veröffentlichungsdatum
bei tatsächlicher Freigabe im Projektkontext festhalten. Den Changelog erst
dann der bestätigten Versionsnummer und dem tatsächlichen Datum zuordnen.

Vor einem Wechsel einer bestehenden Entwicklungsinstallation eine Sicherung
erstellen. Module Control und Store dürfen nicht gleichzeitig dieselbe
Library mit identischen IDs bereitstellen. Den vorgesehenen Wechselweg zuerst
auf dem Testsystem prüfen und bestehende Kontokonfiguration nicht allein für
die Veröffentlichung neu anmelden.
