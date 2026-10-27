# Symcon Module Store – erste Beta-Einreichung

Vorbereitete Angaben nach dem Verfahren von OpenHomeAlarm. Die Texte können
in die Vorlage im Symcon-Entwicklerkonto übernommen werden. Dieses Dokument
ist keine gespeicherte oder veröffentlichte Store-Vorlage.

Grundlage ist die am 26.10.2026 geprüfte
[Symcon-Anleitung zum Einreichen](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/store/einreichen/).
Beta wird beim Einreichen unmittelbar angeboten; **Einreichen** erst nach der
abschließenden Prüfung des Kandidaten betätigen.

## Identität und Pflichtfelder

| Store-Feld | Vorbereiteter Wert |
| --- | --- |
| Bundle-ID | `de.burki24.symconicloud` – Vorschlag nach dem bestehenden Burki24-Schema; Verfügbarkeit und gegebenenfalls bereits vorhandene ID im Entwicklerkonto prüfen |
| Initialer Kanal | **Beta** |
| GIT-URL | `https://github.com/Burki24/SymconiCloud` |
| GIT-Commit | Vollständiger SHA des abschließend geprüften Metadaten-Commits auf `dev`; siehe [Release-Prozess](RELEASE_PROCESS.md) |
| Lokalisierung | **Deutsch** |
| Name | **SymconiCloud** |
| Beschreibung | Nachfolgender deutscher Beschreibungstext |
| Versionsinformation | Nachfolgende Hinweise zur ersten öffentlichen Beta |
| Link zur Dokumentation | `https://github.com/Burki24/SymconiCloud/blob/dev/README.md` |
| Kategorien | Mindestens eine passende Kategorie im Entwicklerkonto auswählen; bevorzugt eine dort angebotene Kategorie für Ortung/Anwesenheit bzw. Dienste. Die tatsächlich verfügbaren Bezeichnungen wurden nicht im Portal bestätigt. |
| Aktualisierung beinhaltet keine funktionalen Änderungen | **Nicht aktivieren**; erste funktionale Veröffentlichung |

Die Bundle-ID ist eine dauerhafte Store-Identität und bleibt bei Updates
gleich. Sie ist **nicht** die Library-GUID aus `library.json` und nicht eine
der sieben Modul-GUIDs. Alle sieben Module gehören zu diesem einen
Bibliothekseintrag; keine sieben separaten Store-Bundles anlegen.

Zunächst nur die deutsche Store-Lokalisierung anlegen. Weitere Sprachen können
später mit vollständig passenden Anleitungen ergänzt werden; keine leere
englische Lokalisierung mitveröffentlichen.

## Beschreibung – Deutsch

```text
SymconiCloud verbindet ausgewählte Apple-iCloud-Dienste mit Symcon ab Version 9.0.

Ein gemeinsames iCloud-Konto übernimmt Anmeldung mit Zwei-Faktor-Bestätigung, Sitzung und begrenzte Datenabfragen. Darauf bauen sieben Module auf:

• iCloud Konto: gemeinsame Anmeldung, Kontoschutz, Abfragepause und datensparsame Diagnose.
• iCloud Geräte Konfigurator: Geräteinstanzen und Anwesenheitsgruppen aus dem verbundenen Konto anlegen.
• iCloud Gerät: Akku-, Geräte- und Standortvariablen, letzter bekannter Ort, Suchton sowie bestätigungspflichtiges Aktivieren und Deaktivieren des Verloren-Modus.
• iCloud WoIst Karte: Gerätepositionen gemeinsam auf einer OpenFreeMap-Karte, optional mit Adressen über Nominatim oder Geoapify.
• iCloud Anwesenheit: mehrere Geräte mit gemeinsamen Zonen, Gesamtanwesenheit, GPS-Stabilisierung und einstellbaren Regeln je Zone. Unklare oder veraltete Standorte werden als „Unbekannt“ behandelt.
• iCloud Fotos: Albumwahl und Diashow mit begrenztem Vorschau-Cache und wählbaren Ressourcenprofilen.
• iCloud Kontakte: lesendes Empfängerverzeichnis mit getrennten Telefonnummernkategorien sowie Empfängerübernahme in Mitteilungen oder Benachrichtigung nach Vorschau und Bestätigung. Die Übernahme versendet keine Nachrichten.

Karte, Anwesenheitswidgets und Fotos sind für die Symcon-Kachelvisualisierung und IPSView verfügbar. Designer bieten anpassbare Darstellung und Hell-/Dunkelmodus. Gerätebilder können vom Rechner hochgeladen und automatisch verkleinert werden.

Benötigt werden ein Apple-Account, ein vertrauenswürdiges Gerät für den Bestätigungscode sowie Internetzugriff. Fotos, Kontakte und Adressauflösung werden nur bei Bedarf aktiviert. Externe Adressdienste können eigene Bedingungen und Kosten haben.

Die Anbindung nutzt Apples Web-Schnittstellen und ist kein offizielles Apple-Produkt. Änderungen bei Apple können den Zugriff beeinträchtigen oder eine erneute Anmeldung erfordern. Kontoschutz reduziert vermeidbare Anfragen, kann Apple-Sicherheitsprüfungen und Kontosperren aber nicht ausschließen. Standortdaten können ungenau oder veraltet sein.

Lizenz: PolyForm Noncommercial License 1.0.0. Einzelheiten, Einrichtung und Einschränkungen stehen in der Dokumentation.
```

## Versionsinformation – Deutsch

```text
Erste öffentliche Beta von SymconiCloud für Symcon 9.0 / PHP 8.5.

Enthalten sind ein gemeinsames iCloud-Konto mit Zwei-Faktor-Anmeldung und Kontoschutz, der Gerätekonfigurator, Gerätevariablen und Geräteaktionen, die WoIst-Karte, Anwesenheit für mehrere Geräte, Fotos mit Albumwahl und Diashow sowie ein lesendes Kontaktverzeichnis mit bestätigter Empfängerübernahme.

Die Visualisierungen unterstützen Kachel und IPSView mit Designern, Hell-/Dunkelmodus und Mausradbedienung. Kartenansicht und Geräteauswahl beziehungsweise Bild und Pausenzustand bleiben bei Datenupdates und Wiederverbindung erhalten. Die Anwesenheit bietet Widget und WidgetCompact sowie Gerätebilder mit automatischer Aufbereitung.

Die optionale Adressauflösung über Nominatim oder Geoapify berücksichtigt die gemeldete Standortgenauigkeit und zeigt kompakte Adressen. Anwesenheitszonen können eigene GPS-Vorgaben verwenden oder die globalen Werte übernehmen.

Bekannte Grenzen: Apples Web-Schnittstellen sind nicht für diese Anbindung zugesichert. Eine erneute Sicherheitsprüfung oder Kontosperre bleibt möglich. Der globale iCloud-Dienst und die Bestätigung über ein vertrauenswürdiges Gerät werden unterstützt; China-Endpunkte, SMS als alternatives Anmeldeverfahren und Hardware-Sicherheitsschlüssel sind nicht enthalten. Fotos können eine zusätzliche Freigabe auf einem vertrauenswürdigen Gerät benötigen. Kalender, Erinnerungen, iCloud Drive und Nachrichtenversand sind nicht Bestandteil dieser Library.

Für bestehende Entwicklungsinstallationen vor dem Wechsel eine Symcon-Sicherung erstellen und dieselbe Library nicht parallel über Module Control und Module Store installieren. Die Anleitung beschreibt die Einrichtung und optionalen Dienste.
```

Die Versionsinformation beschreibt den ersten Beta-Umfang und enthält bewusst
keine fest eingetippte Versionsnummer. Maßgeblich sind Version, Build und Datum
aus der `library.json` des endgültig ausgewählten Commits; diese Felder werden
nicht manuell geändert.

## Hinweise zur Datenverarbeitung und zu besonderen Funktionen

Diese Punkte sind bei Rückfragen zur Einreichung anhand der Modulanleitungen
und des Quellcodes zu erläutern; es wird keine bereits erteilte Ausnahme oder
Freigabe durch Symcon behauptet:

- Zugangsdaten und Sitzung verbleiben in der Kontoinstanz. Kinder erhalten
  normalisierte Daten über den Symcon-Datenfluss. Standort-, Foto- und
  Kontaktdaten sind vertraulich; Konfiguration und Sicherungen schützen.
- Optionaler Fotocache: kontoeigene Vorschauen in einem festgelegten
  Unterverzeichnis des Symcon-Datenverzeichnisses, begrenzt nach Anzahl und
  Größe. Kein beliebiger Dateipfad wird aus dem Widget übernommen. Die
  Modulanleitung erläutert Ressourcenprofile und Bereinigung.
- OpenFreeMap lädt Kartendaten von externen Servern. Nur bei aktivierter
  Adressauflösung werden Standortkoordinaten an den ausgewählten
  Nominatim-/Geoapify-Dienst übermittelt. Nominatim kann selbst betrieben
  werden; für Geoapify wird ein API-Key benötigt.
- Die Empfängerübernahme ändert ausschließlich ausdrücklich ausgewählte
  Empfängerfelder einer zugewiesenen Zielinstanz nach Vorschau und Bestätigung.
  Bestehende sonstige Einstellungen werden erhalten; kein Versand wird
  ausgelöst. Das ist kein automatischer Kontaktsynchronisationsdienst.
- Verloren-Modus kann ein echtes Gerät sperren. Aktivieren und Deaktivieren
  benötigen Bestätigung. Eine erneute Praxisprüfung darf nicht ohne Auftrag
  ein Gerät sperren oder seinen Verloren-Modus verändern.
- Es gibt kein Discovery-Modul. Der Konfigurator arbeitet ausschließlich mit
  dem verbundenen iCloud-Konto.

## Kandidat und Nachweise der Vorbereitung

Ausgangsstand vor diesen Dokumentationsänderungen:

| Angabe | Geprüfter Ausgangswert |
| --- | --- |
| Commit | `2ae43fdf509f4b9d25357fdcbced4a18174c3686` |
| Library-Version | `0.2` |
| Build | `1563103` |
| Metadaten-Zeitstempel | `1793048438` |
| GitHub Tests | [Erfolgreich](https://github.com/Burki24/SymconiCloud/actions/runs/40402032681) |
| GitHub Check Style | [Erfolgreich](https://github.com/Burki24/SymconiCloud/actions/runs/40402033011) |
| Automatische Versionierung des Quellcommits | [Erfolgreich](https://github.com/Burki24/SymconiCloud/actions/runs/40400724487) |

Die Versionierung des Bot-Commits wird anschließend erwartungsgemäß
übersprungen, damit keine Schleife entsteht. Der Benutzer hat den erfolgreichen
Versionslauf bestätigt. Beobachtungsbetrieb sowie Installation/Update sind
ebenfalls als abgeschlossen bestätigt; die Praxisnachweise der einzelnen
Module stehen im Projektkontext.

Diese Werte sind **kein bereits festgelegter Einreichungs-Commit**: Nach dem
Committen dieser Vorbereitung erzeugt die Automatik neue Metadaten. Für den
Store muss deren tatsächlicher Commit erneut geprüft und ausdrücklich gewählt
werden; keinen aktuellen Zweigstand ungeprüft übernehmen.

## Einordnung der offenen Punkte

**BEDINGT EINREICHUNGSREIF** für Beta: keine bestätigten Blocker aus dieser
Vorbereitung, zwei Hindernisse und ein nicht blockierender Hinweis. Die
Einordnung bezieht sich auf die vorbereiteten Store-Daten und den oben
benannten technischen Ausgangsstand, nicht auf eine bereits veröffentlichte
Version oder eine neue vollständige Live-Abnahme.

| ID | Einordnung und Grundlage | Nachweis / erforderlicher Abschluss |
| --- | --- | --- |
| STORE-01 | **HINDERNIS – Symcon:** endgültige Store-Pflichtfelder im Entwicklerkonto noch nicht bestätigt | Bundle-ID/gegebenenfalls vorhandene Vorlage prüfen, mindestens eine reale Kategorie auswählen und Dokumentationslink sichtbar kontrollieren; siehe Abschnitt „Identität und Pflichtfelder“ |
| STORE-02 | **HINDERNIS – Project:** finaler Kandidat nach diesem Dokumentationsstand noch nicht festgelegt | Metadaten-Bot abwarten und Tests/Style für exakt dessen vollständigen SHA prüfen; siehe `RELEASE_PROCESS.md` |
| STORE-03 | **HINWEIS – Risk:** nicht zugesicherte Apple-Web-Schnittstellen | Grenzen einschließlich möglicher Kontoprüfungen/Sperren in Store-Text und Kontomodul-Anleitung transparent benannt; keine Garantie gegen Änderungen bei Apple |

Frische lokale Prüfungen: `php tests/run.php` unter PHP 8.5 mit GD/EXIF und
Node.js, `php tests/validate_structure.php`, `php .style/json-check.php`,
PHP-CS-Fixer mit offizieller `.style`-Konfiguration im Prüfmodus und
`git diff --check` bestanden. Der Skill-Scanner meldet keine statischen
Blocker. Seine Rohsignale enthalten auch ignorierte lokale `.preview`-Dateien;
die Filterung auf Git-Quellen ergibt 48 manuell zu bewertende Signale, keine
48 nachgewiesenen Fehler. Die für diese Vorbereitung betrachteten Grenzen
(statische Assets, kontoeigener Fotocache, explizite Designeraktionen und
bestätigte Empfängerübernahme) begründen keinen zusätzlichen bestätigten
Blocker. Die neue Vorbereitung verändert diese Implementierungen nicht.

Keine neue Apple-Anmeldung, Geräteaktion, Store-Installation oder Änderung
an echten Instanzen wurde zur Dokumentationsprüfung ausgeführt. Vorhandene
Praxisabnahmen und die Benutzerbestätigung von Beobachtung und Updateprüfung
bleiben die Grundlage für diese unveränderten Laufzeitfunktionen. Die
GitHub-Checks nach dem nächsten Push und die Portalprüfung sind offen.

**Freigabe:** Vorlage kann mit den Texten vorbereitet werden. Erst nach
Abschluss von STORE-01 und STORE-02 den gesonderten Veröffentlichungsschritt
ausführen.

## Abschluss im Entwicklerkonto

1. Nach [Release-Prozess](RELEASE_PROCESS.md) den finalen Kandidaten ermitteln
   und `tests`/`style` für genau diesen Commit kontrollieren.
2. Unter **Modul hinzufügen** die bestätigte Bundle-ID und **Beta** wählen.
   Falls bereits eine Vorlage existiert, diese bearbeiten und kein zweites
   Bundle anlegen.
3. GIT-URL und vollständigen Kandidaten-SHA eintragen. Genau dessen
   `library.json` und Anleitungen mit der Vorlage abgleichen.
4. Die deutsche Lokalisierung mit Name, Beschreibung, Versionsinformation und
   Dokumentationslink aus diesem Dokument befüllen.
5. Mindestens eine tatsächlich angebotene passende Kategorie auswählen.
   Kategorie, Dokumentationslink und Bundle-ID sichtbar im Portal prüfen;
   sie sind mit dieser lokalen Vorbereitung nicht bestätigt.
6. Die Vorlage mit **Speichern** sichern und nochmals kontrollieren.
   **Einreichen** ist ein gesonderter Veröffentlichungsschritt und wurde mit
   dieser Vorbereitung nicht ausgeführt.
