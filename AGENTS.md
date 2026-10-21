# SymconiCloud

Lies `../SymconDevelopment/AGENTS.md`, `../SymconDevelopment/ARCHITECTURE.md`, die zentralen `principles/` und die relevanten Standards. Sie gelten für dieses Projekt.

- Ziel: IP-Symcon 9.0 / PHP 8.5; eigenständige Library mit `IPSModuleStrict`.
- Library: SymconiCloud. Erster Dienst: WoIst. Weitere Dienste sind keine zugesagten Funktionen.
- Öffentliche Präfixe sind vierstellig: ICAC (Konto), ICWC (WoIst-Konfigurator), ICWD (WoIst-Gerät).
- Keine Implementierung aus iCloud3 übernehmen. Externe Protokollinformationen sind Quellen, keine Kopiervorlage.
- Kontomodul besitzt Anmeldung, Sitzung, HTTPS und Gerätecache. Kinder erhalten ausschließlich normalisierte Gerätedaten, keine Zugangsdaten oder Tokens.
- Keine realen Apple-Anmeldungen ohne ausdrücklichen Praxisauftrag. Offline-Tests verwenden synthetische Daten.
- `libs/helper` enthält unveränderte, manifestierte Quellen aus Symcon_ModuleHelper; Änderungen erfolgen dort separat.
- Lizenz: PolyForm Noncommercial License 1.0.0.
- Entwicklung auf `dev`. Keine Commits, Pushes, Releases oder Live-Installation ohne Auftrag.
- Prüfung: `php tests/run.php`, `php tests/validate_structure.php`, offizielle StylePHP-Prüfung und php-cs-fixer im Prüfmodus.
