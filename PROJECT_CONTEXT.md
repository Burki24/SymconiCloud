# Entwicklungsstand

Zielplattform: Symcon 9.0 / PHP 8.5. Entwicklungsbranch: dev. Lizenz: PolyForm Noncommercial License 1.0.0. Version 0.1/build 0/date 0 bezeichnet ausschließlich den unveröffentlichten Ausgangsstand, keinen Release.

## Prüfung

- `php tests/run.php`: Protokoll-, Konto- und Geräte-/Konfiguratorverhalten sowie Struktur und JSON.
- `php tests/validate_structure.php`: Library, offizielle Modultypen, GUID-Datenfluss, Module, Helper-Integrität, LF und CI.
- `php .style/json-check.php`: offizielle StylePHP-JSON-Prüfung.
- `php-cs-fixer fix --config=.style/.php-cs-fixer.php --allow-risky=yes --dry-run --diff`: offizielle StylePHP-Regeln im Prüfmodus.
- Gemeinsame CI: Burki24/Symcon_ModuleCI v1.0.0 mit PHP 8.5 sowie curl, bcmath, dom.

`.style` und `tests/stubs` sind offizielle Git-Submodule. `libs/helper` ist ein unverändertes, per SHA-256 manifestiertes Teilbundle der zentralen Helper; `.helper-sync.json` beschreibt die gewünschten Quellen. Die Registrierung des neuen Consumers im zentralen Repository ist ein eigener Auftrag; es wurde dort nichts verändert. Git erhält die Helper-Bytes unverändert, während projektspezifische Dateien UTF-8/LF verwenden.

## Offene Praxisabnahme

Lokale Prüfung am 21.10.2026 mit PHP 8.5.10: Gesamtsuite einschließlich Syntaxprüfung, Strukturprüfung, offizielle StylePHP-JSON-Prüfung und php-cs-fixer 3.95.27 mit offiziellen Regeln im Prüfmodus erfolgreich. php-cs-fixer gibt einen unverbindlichen Hinweis auf die fehlende optionale composer.json aus; die Prüfung selbst endet erfolgreich. Die GitHub-CI wurde noch nicht ausgeführt.

Noch nicht bestätigt: echte Apple-SRP-Anmeldung, tatsächliche 2FA-Codezustellung, Cookie-Vertrauen nach Neustart, Inventarabfrage mit shouldLocate=false, Mehrkonten-Anlage in der realen Konsole und Darstellung in schmaler/breiter Konsole. Ohne diese Abnahme ist der Stand kein als produktionsbereit geprüfter Release.

Eine spätere Praxisabnahme sollte einen autorisierten Testaccount verwenden und keine Passwörter, Codes, Cookies, Gerätedaten oder Standorte im Repository speichern. Änderungen an laufenden Symcon-Systemen erfolgen nur separat beauftragt.
