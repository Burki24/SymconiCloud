# ADR 0001: Erweiterbare SymconiCloud-Library mit WoIst als erstem Dienst

- Status: Angenommen für den ersten Entwicklungsstand
- Datum: 21.10.2026

## Entscheidung

Die Library heißt auf Benutzerwunsch SymconiCloud. Sie ist keine Portierung von iCloud3 und enthält keinen daraus übernommenen Code. Eigene Apple-Protokolllogik liegt unter `libs/`. Allgemeine Fähigkeiten kommen unverändert aus Symcon_ModuleHelper.

Das Kontomodul ist ein offizieller Splitter (Typ 2), der mehrere Dienst-/Geräteinstanzen an eine Kontoidentität und Sitzung bindet. HTTPS wird über PHP-cURL im Konto ausgeführt. Es gibt keinen separaten, zustandslosen I/O-Durchreicher: keine dauerhaft offene Verbindung, kein gemeinsamer austauschbarer Transport und keine eigenständige Transportkonfiguration benötigen eine solche Instanz. Das entspricht der vorhandenen Kalender-Konto-Struktur und Symcons Möglichkeit, Splitter ohne I/O einzusetzen.

WoIst ist der erste Dienst. Ein Konfigurator (Typ 4) und Geräte (Typ 3) sind direkt mit dem Konto verbunden. Module enthalten WoIst im Namen. Instanzen werden über das offizielle Configurator-Element angelegt; dessen physischer Parent ist der bevorzugte Parent des erstellten Gerätes. Bestehende Instanzen werden ausschließlich anhand Kontoverbindung und unveränderter Geräte-ID zugeordnet.

Die äußere Datenflussgrenze erlaubt nur GetDevices und RefreshDevices. Keine generische HTTP-Proxyfunktion und keine Ausgabe von Authentifizierungsdaten. Weitere iCloud-Dienste benötigen einen eigenen fachlichen Auftrag und, falls erforderlich, einen versionierten Dienstvertrag. Die heutige Verfügbarkeit von Find My wird nicht als Beweis für den Zugriff auf andere Dienste gewertet.

## Anmeldung

SRP-6a mit SHA-256, RFC5054-2048-Gruppe und Apples s2k/s2k_fo-Passwortableitung. BC Math stellt die großen Ganzzahlen bereit, statt eine eigene BigInteger-Bibliothek einzuführen. Fehlende Erweiterung wird explizit angezeigt. SHA-256/PBKDF2 und Zufall stammen aus PHP.

Zugangsdaten sind Properties; Cookiejar und Sitzung persistente Attribute. Cookies behalten ihre Domain-/Pfad-/Secure-/Ablauf-Semantik über cURL. Keine privaten Dateien und keine Protokollierung von Rohantworten. Veränderung der Zugangsdaten invalidiert Sitzung und Cache. Ein instanzbezogenes Semaphore serialisiert Netzwerkoperationen und Konfigurationsanwendung. GET-Cachezugriffe haben keine Seiteneffekte.

2FA-Challenge zehn Minuten, keine Speicherung des Codes. Wiederanlauf verwendet vorhandene Sitzungscookies. Ein fehlgeschlagener Geräteabruf darf genau einmal tokenbasiertes accountLogin und einen neuen Abruf versuchen; keine automatische Passwortanmeldung. Rate-Limit setzt eine persistente Sperrfrist. Fehlgeschlagene Antworten werden nie als leere, erfolgreich abgefragte Geräteliste dargestellt.

## Grenzen und Nachweise

Apples Web-Schnittstelle ist nicht als öffentliche Drittanbieter-WoIst-API zugesichert. Insbesondere shouldLocate=false ist eine aus dem Protokollfeld abgeleitete Inventarabfrage und benötigt eine reale Abnahme. Globaler Dienst, vertrauenswürdiger Gerätecode; SMS, Sicherheitsschlüssel und China-Endpunkte sind nicht implementiert.

Offline-Nachweise verwenden unabhängige Python-SRP-Servervektoren, synthetische HTTP-Antworten und offizielle Symcon-Stubs. Sie ersetzen weder Apple-Kompatibilität noch die Abnahme in einer realen Symcon-9-Konsole. Keine echten Anmeldungen oder Live-Systemänderungen wurden vorgenommen.

Quellen: [Symcon-Module](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module.md), [RFC5054](https://www.rfc-editor.org/rfc/rfc5054), [unabhängige GSA-Protokollbeschreibung](https://github.com/foxt/js-srp-gsa), [aktuelle 2FA-Protokollbeobachtung](https://github.com/mandarons/icloudpy), [Find-My-Protokollbeobachtung](https://github.com/picklepete/pyicloud). Aus diesen Projekten wurde kein Implementierungscode übernommen.
