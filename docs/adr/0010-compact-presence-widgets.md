# ADR 0010: Kompakte Anwesenheitswidgets

## Entscheidung

iCloud Anwesenheit bietet jeweils ein kompaktes Widget für native Kachel und
IPSView. Gesamtanwesenheit und ausgewählte Einzelgeräte teilen den verfügbaren
Bereich. Keine neue Instanz, vollständige Seite oder zusätzliche Apple-Abfrage.

Der librarylokale Trait `SymconiCloud\PresenceWidget` besitzt Darstellung, unabhängige Designer,
Bildprüfung und lesenden IPSView-Cachetransport. Er verwendet bestehende Theme-,
Style-, HTML-, Interaktions-, HTTP- und Layouthelper. Die Modulklasse übersetzt nur
bereits ausgewertete Variablen in das Fachmodell. Koordinaten, Apple-Geräte-IDs
und Kontozugangsdaten gehören nicht in dieses Modell.

Ein optionales `picture`-Feld in jeder `Devices`-Zeile verweist auf ein lokales
Bildmedium. Der Helper erlaubt begrenzte Rasterbilder; fehlende/ungültige Bilder
verwenden Initialen. Geräteauswahl, Variablenkennungen und GPS-Quellidentität
hängen nicht von Bild oder Designer ab.

## Aktualisierung und Konfiguration

Native Widgets nutzen `UpdateVisualizationValue`. IPSView erhält eine optionale
WebContent-Variable; deren HTML bleibt bei Datenupdates unverändert. Ein
authentifizierter POST liest alle fünf Sekunden ausschließlich den Widgetcache.
Der instanzbezogene Token wird bei Kontoänderungen erneuert und beim Deaktivieren
verworfen. Der flüchtige Hook wird in Create registriert. Der zentrale Origin-
Resolver vermeidet normalerweise eine manuelle Serveradresse; ein optionaler
expliziter HTTP(S)-Ursprung bleibt für Clients ohne Herkunft verfügbar.

Die Widget-Ressourcen erhalten DOM-Zeilen, Fokus, Geräteauswahl und
Scrollposition bei Updates. Mausrad und Frames verwenden den bestehenden
Interaktionsvertrag. IPSView-Stile bleiben von Kachelfarben unabhängig.

## Verteilung und Prüfung

Der Baustein liegt unter `libs/PresenceWidget.php`, seine HTML/CSS/JS-Ressourcen
beim Anwesenheitsmodul. Er wird ausschließlich von SymconiCloud genutzt und darf
nach der zentralen Festlegung deshalb nicht in Symcon_ModuleHelper liegen.
Mehrere Module innerhalb einer Library oder denkbare weitere Nutzer genügen
nicht. Erst bei tatsächlicher Nutzung in einer zweiten Library wird er zentralisiert.
Die bereits libraryübergreifend verwendeten Basishelper werden unverändert genutzt.
Ein neues zentrales Abonnement oder eine Helper-Verteilung ist hierfür nicht nötig.

Offline-Prüfungen sichern Modell, fehlende Werte, Bilder, Formularaktionen,
Designer, Zugriffsschutz und GPS-/Variablenzustand. Libraryeigene JavaScript-Tests
prüfen SDK-Nachrichten, Zeilenidentität, Auswahl, Scrollposition und Wiederanlauf.
MCP verwendet eine getrennte temporäre Library mit synthetischen Daten.
Kachelvorschau und IPSView-Testeinbettung bestätigen keine nativen Live-Pushs oder
native IPSView-App; diese Hostprüfungen bleiben nach regulärer Installation nötig.
