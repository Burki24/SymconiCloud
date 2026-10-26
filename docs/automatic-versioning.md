# Automatische Versionierung

SymconiCloud verwendet denselben Versionsvertrag wie OpenHomeAlarm und
SymconECharts. Die Berechnung liegt zentral in
`Symcon_ModuleCI/library-metadata`; in dieser Library gibt es keine Kopie des
Generators. Tests und Stilprüfung bleiben auf `Symcon_ModuleCI@v1.0.0`.

## Ablauf auf `dev`

1. Der Benutzer committet und pusht seine Änderungen über GitHub Desktop.
2. `Update library metadata` checkt `dev` mit vollständiger Git-Historie aus
   und führt die vollständige Modultestsuite aus.
3. Der zentrale Generator erhöht die zweite Versionszahl pro Quellcommit des
   Pushs. Automatische Metadaten-Commits zählen nicht mit. Build ist die erste
   siebenstellige Hexzahl des Quell-SHA als Integer; Datum ist dessen
   Unix-Zeitstempel. Eine höhere vorhandene Version wird nicht herabgesetzt.
4. Nur bei einer Änderung wird `library.json` mit einem Bot-Commit
   `CHORE: Update library metadata ...` zurückgeschrieben. Dieser Commit
   startet Tests und Stilprüfung erneut, aber keine weitere Versionserhöhung.
5. Vor der nächsten lokalen Bearbeitung den Bot-Commit pullen. Bei bereits
   vorhandenen lokalen Änderungen diese zuerst sichern; nicht verwerfen.

Wiederholungen desselben Pushs erhöhen die Version nicht erneut. Überholte
Quellcommits werden übersprungen. Ändert sich der Remote-Zweig während der
Prüfung, darf der normale Push scheitern; es gibt keinen Force-Push. In diesem
Fall den Workflow des neuesten Quellcommits prüfen beziehungsweise erneut
ausführen. Ein unbekannter/Null-Vorgänger löst nur einen Versionsschritt aus.

## Einrichtung und Reihenfolge

Zuerst die neue Action auf **Symcon_ModuleCI/dev** committen und pushen, dann
die Einbindung auf **SymconiCloud/dev**. Die neue Action verwendet zunächst
`@dev` zur Erprobung; nach Freigabe des nächsten ModuleCI-Release-Tags wird
ausschließlich dieser neue Verweis auf den tatsächlichen Tag umgestellt.
Der bestehende Tag `v1.0.0` wird nicht verändert.

Die bereits für Helper-Verteilung verwendete GitHub App muss auch auf
SymconiCloud installiert sein und **Contents: write** besitzen. Unter
**Settings → Secrets and variables → Actions** benötigt SymconiCloud:

- Variable `HELPER_SYNC_APP_CLIENT_ID` mit der Client-ID der App.
- Secret `HELPER_SYNC_APP_PRIVATE_KEY` mit deren privatem Schlüssel.

Die Werte ausschließlich direkt bei GitHub hinterlegen. Die lokale Prüfung
liest keine Schlüssel. Die App authentifiziert Checkout und Push, damit der
Bot-Commit Folgeprüfungen auslöst. Ein gewöhnlicher `GITHUB_TOKEN` würde diese
Push-Workflows normalerweise nicht erneut starten. Vorhandene Zweigregeln
müssen den App-Push erlauben; sie werden durch den Workflow nicht umgangen.

Der Workflow läuft ausschließlich bei Pushs auf `dev`, nicht im Kontext von
Pull Requests. Er veröffentlicht weder einen GitHub-Release noch einen
Module-Store-Eintrag und installiert nichts auf einem Symcon-System.

## Dokumentationslinks und Beta

Die `url`-Felder aller sieben `module.json`-Dateien verweisen auf ihre jeweilige
README unter `dev`. Die zentrale Release-Vertragsprüfung stellt sicher, dass
kein Modul auf die veralteten Anleitungen unter `main` zurückfällt.
Bei einem späteren Wechsel des Release-Zweigs sind URLs und Vertrag gemeinsam
anzupassen.

Beobachtungsbetrieb und Installations-/Updateprüfung sind vom Benutzer als
abgeschlossen bestätigt. Der Store-Eintrag ist der nächste gesonderte Schritt.
