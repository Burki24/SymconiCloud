# SymconiCloud

Lies `../SymconDevelopment/AGENTS.md`, `../SymconDevelopment/ARCHITECTURE.md`, die zentralen `principles/` und die relevanten Standards. Sie gelten für dieses Projekt.

- Ziel: IP-Symcon 9.0 / PHP 8.5; eigenständige Library mit `IPSModuleStrict`.
- Library: SymconiCloud. Kontomodul, allgemeiner Gerätekonfigurator, iCloud-Gerät, WoIst-Karte, iCloud-Anwesenheit und iCloud-Fotos sind getrennte Module. WoIst liest alle Geräte des verbundenen Kontocaches; Anwesenheit wertet mehrere ausgewählte Geräte eines Kontos mit gemeinsamen Zonen und Gesamtanwesenheit aus. GPS-Zustand und Aufenthaltsdauer bleiben je Gerät getrennt.
- Öffentliche Präfixe sind vierstellig: ICAC (Konto), ICDC (Device-Konfigurator), ICID (iCloud-Gerät), ICWI (WoIst-Karte), ICPR (Anwesenheit), ICPH (Fotos).
- Keine Implementierung aus iCloud3 übernehmen. Externe Protokollinformationen sind Quellen, keine Kopiervorlage.
- Kontomodul besitzt Anmeldung, Sitzung, HTTPS und Gerätecache. Kinder erhalten ausschließlich normalisierte Daten, keine Zugangsdaten oder Tokens.
- Fotos: optionaler lesender CloudKit-Zugriff im Konto, privater geteilter Festplatten-Cache mit Anzahl- und Bytegrenzen; keine Apple-URLs oder Sitzungsdaten an Widgets. Foto-Code bleibt librarylokal.
- Keine realen Apple-Anmeldungen ohne ausdrücklichen Praxisauftrag. Offline-Tests verwenden synthetische Daten.
- `libs/helper` enthält unveränderte, manifestierte Quellen aus Symcon_ModuleHelper; Änderungen erfolgen dort separat.
- Leaflet unter `libs/vendor` bleibt unverändert und wird per SHA-256 geprüft. Browserprüfungen verwenden synthetische Daten und lokal ersetzte Kartenkacheln.
- Lizenz: PolyForm Noncommercial License 1.0.0.
- Entwicklung auf `dev`. Commit und Push führt ausschließlich der Benutzer über GitHub Desktop aus. Nach Änderungen liefert der Agent Commit-Betreff und Erklärung auf Englisch. Releases und Live-Installation erfordern einen gesonderten Auftrag.
- Prüfung: `php tests/run.php`, `php tests/validate_structure.php`, offizielle StylePHP-Prüfung und php-cs-fixer im Prüfmodus.
