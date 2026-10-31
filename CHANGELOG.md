# Unveröffentlicht

## Fixes

- **iCloud Gerät:** Fehlende Standortgenauigkeit wird nicht mehr als `0 m`
  gespeichert. Die neue lesende Variable **Genauigkeit verfügbar**
  (`AccuracyAvailable`) unterscheidet einen bekannten Radius vom erhaltenen
  historischen Zahlenwert. Bestehende Variablen und ihre IDs bleiben erhalten.
- **Standortdaten:** Fehlende oder ungültige Apple-Alterskennzeichnungen gelten
  vorsorglich als alt; nur ein explizites `false` kennzeichnet eine Position als
  nicht alt. **Achtung:** Für eigene Automationen die Standortzeit und beim
  Genauigkeitswert zusätzlich `AccuracyAvailable` auswerten. Nach dem Update
  die Geräteinstanzkonfiguration übernehmen.
- Die sieben Modulanleitungen erläutern Property-Typen, Kennungen und
  Rückgabebedeutungen und enthalten ausführbare synthetische Skriptbeispiele.

## Release-Vorbereitung

- Automatische Versionierung auf `dev` über den zentralen ModuleCI-Baustein:
  Versionsnummer, Build und Datum werden nach erfolgreichen Tests erzeugt.
  Die Dokumentationsschaltflächen aller sieben Module verweisen auf die
  aktuellen Anleitungen unter `dev`.

## Neue Funktionen

- **WoIst** erlaubt Drag-and-drop über einen eigenen Ziehgriff sowie Hoch-/Runter-
  und Tastaturbedienung in der Geräteliste. Die Reihenfolge wird je Instanz für
  Kachel und IPSView gemeinsam gespeichert. Neue Geräte werden am Ende ergänzt;
  Filter und Updates erhalten den Stand. Ein alphabetischer Reset steht bereit.
  Die tokengeprüfte IPSView-Sortierung löst keine Apple-Abfrage aus.

- **WoIst** bietet eine gemeinsame Geräteauswahl für Kachel und IPSView.
  Standardmäßig bleiben alle Geräte sichtbar. Optional werden nur ausgewählte
  Geräte in Liste, Karte und Positionsübersicht angezeigt, eindeutig nach Konto
  und Gerätekennung zugeordnet. Neue Geräte werden bei eingeschränkter Auswahl
  nicht automatisch aufgenommen; leere und vorübergehend nicht verfügbare
  Auswahlen bleiben erhalten. Der Kontofilter schränkt die Geräteauswahl weiter ein.

- **WoIst** kann zusätzliche vorhandene iCloud-Konten gemeinsam auf einer Karte
  darstellen. Der Kontofilter begrenzt Karte und Geräteliste; gleichnamige
  Geräte und identische Gerätekennungen verschiedener Konten bleiben getrennt.
  Bestehende Einstellungen und Gerätekennungen des verbundenen Kontos bleiben
  erhalten. Zoom, Ausschnitt, Auswahl, Filter und Scrollposition überstehen
  Datenupdates und den Wiederaufbau der Ausgabe. Nicht verfügbare Konten
  beeinträchtigen die übrigen Konten nicht.

- **iCloud Konto** bewahrt sicherheitsrelevante Ereignisse getrennt von normalen
  Abfragen auf: bis zu 200 Einträge aus 30 Tagen mit bekannten Apple-Fehlercodes
  und verständlichen deutschen Ergebnissen. Eine zusätzliche Option verhindert
  neue Anmeldungen und Code-Anfragen auf Zweitsystemen bei erhaltener Sitzung;
  standardmäßig bleibt das bisherige Verhalten erhalten. Bekannte Kontosperren,
  zusätzliche Kontoprüfungen und ausstehende Nutzungsbedingungen stoppen alle
  betroffenen Kontoanfragen. Allgemeine Ablehnungen werden nicht als Beweis für
  ein falsches Passwort ausgegeben.

- **iCloud Gerät** besitzt eine instanzspezifische Kachel mit Suchton-Taster und
  letztem bekannten Standort: bevorzugt die eingerichtete Adresse, sonst
  Koordinaten, jeweils mit Standortzeit und verfügbarer Genauigkeit. Fehlende
  oder veraltete Standortdaten sind gekennzeichnet. Datenupdates erhalten die
  Scrollposition und lösen keinen Ton aus; die separate Tastervariable bleibt
  bestehen.

- **iCloud Kontakte** übernimmt ausgewählte Telefonnummern und E-Mail-Adressen
  nach Vorschau und Bestätigung in **Mitteilungen (IPSymconNotice)** oder
  vorhandene SMTP-/SMS-Aktionen von **Benachrichtigung**. Bestehende Einstellungen
  bleiben erhalten; neue Notice-Benutzer sind zunächst inaktiv. Veraltete oder
  inzwischen geänderte Vorschauen werden abgewiesen. Die Übernahme versendet
  keine Nachrichten und startet keinen zusätzlichen iCloud-Abruf.

- Das Empfängerverzeichnis bietet **Geschäftlich**, **Hauptnummer**, **Privat**,
  **iPhone**, **Fax**, **Pager** und **Weitere Telefonnummern** als eigene
  Spalten. Mobilnummern stehen getrennt in **Mobil1**, **Mobil2**
  und **Mobil3**; weitere Mobilnummern bleiben gemeinsam in **Mobil3** erhalten.
  Nummern ohne bekannte Kategorie werden bei Beginn mit `+491` oder `01`
  ebenfalls dort einsortiert. Bekannte Kategorien behalten Vorrang und
  Nummernschreibweisen sowie eigene Labels bleiben erhalten.

- **iCloud Kontakte** stellt ein lesendes Empfängerverzeichnis mit Suche,
  Telefonnummern und E-Mail-Adressen bereit. Der optionale Abruf verwendet
  die bestehende Kontositzung, einen gemeinsamen begrenzten Cache und einen
  separaten Aktualisierungstimer. Kontoschutz und Abfragepause gelten auch
  für Kontakte; fehlende und veraltete Daten sind ausdrücklich erkennbar.

## Fixes

- **iCloud Anwesenheit** ergänzt außerhalb aller Zonen optional die passende
  Adresse aus einer ausgewählten WoIst-Karte desselben Kontos. Kachel, IPSView
  und WidgetCompact benötigen dafür keine Geräteinstanz und starten keine
  zusätzlichen Adressabfragen. Zonenvariablen und Anwesenheitslogik bleiben
  unverändert; ohne passenden Cachetreffer bleibt die bisherige Anzeige erhalten.

- **iCloud Konto** bietet einen optionalen Schnellmodus mit 60-Sekunden-Abruf
  und aktiver Standortanforderung höchstens einmal pro Minute. Wiederholte
  manuelle Abrufe und Neustarts umgehen diese Grenze nicht. Apple-Wartefristen,
  Kontoschutz und GPS-Wechselbestätigung bleiben erhalten. Bestehende Konten
  behalten ihren bisherigen Modus; eine sofortige Ortung ist nicht garantiert.

- **Gerätekonfigurator** zeigt vorhandene Anwesenheitsgruppen nicht mehr allein
  wegen ihrer Auflistung rot an. Geräteauswahl und Zonen bleiben unverändert;
  tatsächliche Konfigurations- und Kontoprobleme werden weiterhin erklärt.

- **iCloud Anwesenheit** macht ausstehende Zonenwechsel in Gerätekarten und
  Gruppensumme sichtbar und dokumentiert, dass ihre Bestätigung eine weitere
  neuere Apple-Position benötigt. Ein zusätzlicher Zustandszeitpunkt bleibt auch
  außerhalb aller Zonen aussagekräftig, während der bisherige Zonenzeitpunkt
  kompatibel erhalten bleibt. Lange Entfernungen werden in Kachel und IPSView
  lesbar in Kilometern dargestellt; die gespeicherte Variable bleibt in Metern.
  Der Gerätekonfigurator erklärt außerdem vorübergehende Anwesenheitsstatus.

- **WoIst** hält bei ausgeblendeter nativer Kachelüberschrift rechts in der
  Bedienleiste Platz für den Maximieren-Pfeil frei. Buttons werden dadurch nicht
  überdeckt. Karte und Geräteliste behalten die freie Fläche; Zoom, Auswahl und
  Scrollposition bleiben bei Datenupdates erhalten.

- Das Library-Paket enthält den freigegebenen DebugFlowHelper samt manifestierter
  Abhängigkeit. Fehlende Instanzschnittstellen nach dem Debug-Update werden damit
  behoben. Die Paketprüfung kontrolliert zusätzlich alle Helper-Abhängigkeiten
  und Ressourcen und benennt fehlende oder veränderte Dateien eindeutig.

- Alle sieben Module bieten nachvollziehbare deutsche Debug-Abläufe über den
  zentralen DebugFlowHelper: Start/Ende, Entscheidungen, Anfragen, Antworten,
  Statuswerte und konkrete Ablehnungsgründe. Private Inhalte bleiben geschützt;
  ungeprüfte Exception-Texte werden nicht mehr direkt ausgegeben. Debugfehler
  verändern keine Geräteaktionen, Rückgaben, Kontoschutzgrenzen oder Abfragen.

- **iCloud Konto** verarbeitet ausdrücklich gültige Bestätigungscodes auch bei
  Apples HTTP-409-Antwort. Vertrauensbestätigung und erfolgreiche Kontositzung
  bleiben erforderlich; unbestätigte Antworten und Sicherheitsablehnungen
  werden nicht akzeptiert. HTTP 202 erhält einen eigenen Hinweis auf die noch
  nicht abgeschlossene Authentifizierung, ohne automatische Wiederholung.
  Der bekannte HTTP-Status bleibt auch bei einer nicht lesbaren Antwort in der
  datensparsamen Diagnose erhalten. Kontoschutz und Versuchslimits bleiben bestehen.

- Die WoIst-Anleitung erklärt den eigenen Nominatim-Server anhand der
  Raspberry-Pi-Installation ohne Docker. Eine verlinkte Schrittfolge enthält
  den getrennten Datenbank-Cluster, Ressourcenlimits, regionalen Import,
  Freiplatzschutz sowie lokale und LAN-Anbindung an Symcon.

- WoIst, Gerät, Anwesenheit mit Widget/WidgetCompact und Fotos sind in den
  Benutzeranleitungen mit Praxisbildern illustriert. Adressen und der private
  Fotodateiname sind unkenntlich gemacht. Private Gerätemarkierungen sind
  entfernt; der Kartenhintergrund bleibt erkennbar.

- Die Benutzeranleitungen erklären Einrichtung, Kontoverbindung und Datenalter
  verständlicher. Gerätevariablen sind mit deutschen Anzeigenamen beschrieben;
  Statushinweise, Modulübersicht, optionale Voraussetzungen und fehlende
  Konfigurationsangaben sind ergänzt. Ein Screenshot zeigt den Suchton-Taster.
  Veraltete Links und Hinweise zur separaten Helper-Verteilung wurden bereinigt.

- **iCloud Anwesenheit** erhält manuell ausgeblendete Gerätevariablen bei
  Datenupdates und beim Übernehmen der Konfiguration. Entfernte Geräte werden
  einmalig ausgeblendet; bei erneuter Auswahl wird nur eine vom Modul verwaltete
  Ausblendung zurückgenommen. Manuelle Sichtbarkeitsänderungen und Variablen-IDs
  bleiben erhalten.

- **Anmelden** zeigt nun das Ergebnis unmittelbar im Kontoformular, einschließlich
  ausstehendem Bestätigungscode und verbleibender Wartezeit. Eine lokale
  Schutzsperre wird deutlich von einer Apple-Kontosperre unterschieden. Nach
  ausdrücklicher Bestätigung kann der Button die lokale Sperre freigeben und
  einen begrenzten Anmeldeversuch starten. Pause und Versuchslimits bleiben
  unverändert wirksam.

- iCloud Kontakte übernimmt Telefonnummern und E-Mail-Adressen aus dem von
  iCloud gelieferten Feld `field`. Auch mehrere Nummern pro Kontakt werden
  angezeigt; vorhandene Adressen erscheinen nicht mehr fälschlich als
  **Nicht verfügbar**. Nach dem Update den Kontaktbestand einmal aktualisieren.

- Das Kontomodul begrenzt Passwort-Anmeldungen und 2FA-Versuche dauerhaft je
  Kontoinstanz. Ablehnungen der Authentifizierung und HTTP-Sperrantworten stoppen
  weitere Anfragen bis zur manuellen Prüfung; Drosselung, Sitzungablauf,
  Foto-Freigabe und Netzwerkfehler werden getrennt behandelt. Eine neue
  Abfragepause erhält die Sitzung und Caches. Die letzten 50 datensparsamen
  Diagnoseeinträge sind im Formular verfügbar; der vorhandene zentrale
  `DebugHelper` ist regulär eingebunden.

- iCloud Fotos überträgt große Vorschauen mit dem zentralen
  `ChunkedJsonTransferHelper` in begrenzten JSON-Seiten bis zum Browser.
  Der HTTP-Seitenaufbau verwendet einen expliziten Ressourcenpfad auch im
  synthetischen Symcon-WebHook-Kontext. Bildauswahl und Pause bleiben bei
  Metadatenupdates und vorübergehenden Verbindungsunterbrechungen erhalten.

- Die Anwesenheitskachel zeigt keine Schaltflächen zur Variantenwahl mehr.
  **Widget** und **WidgetCompact** werden ausschließlich im Kacheldesigner
  ausgewählt; bisherige lokale Sitzungswerte überschreiben diese Auswahl nicht.

- Farbkarte und Legende der Anwesenheit verwenden die deutschen Feldbezeichnungen
  des IPSView-Designers aus dem zentralen Übersetzungskatalog. Technische Rollen
  bleiben ergänzend sichtbar; der englische Designer behält englische Labels.

- Der Gerätedialog der Anwesenheit zeigt die Bildgrenzen direkt an und meldet
  unzulässige Uploads bereits bei der Auswahl. Zu große Dateien (mehr als
  128 KiB), unzulässige Formate und Abmessungen über 1024 × 1024 Pixel
  erhalten konkrete Hinweise statt eines stillen Rückfalls auf Initialen.

- Native IPSView-HTMLBoxen mit einer vom Client bereitgestellten Basisadresse
  werden bei der automatischen Servererkennung berücksichtigt. Zuerst das
  aktualisierte VisualizationAssetHelper-Paket regulär verteilen und übernehmen,
  danach die Instanzkonfiguration übernehmen. Startfehler zeigen einen passenden
  Konfigurationshinweis statt einer irreführenden Wiederholungsmeldung.

- Anwesenheitswidgets in IPSView laden eine echte HTTP(S)-Widgetseite. Der
  geschützte Cacheabruf bleibt damit auf demselben Ursprung und benötigt keine
  CORS-Vorabfrage aus der HTMLBox. Auswahl und Scrollposition bleiben bei
  Datenupdates erhalten. WoIst und Anwesenheit verwenden denselben librarylokalen
  Loader mit den vorhandenen zentralen Adress-, Interaktions- und Seitenhelpern.

## Neu

- **iCloud Fotos**: lesender Albumzugriff über das vorhandene Konto, Einzelbild
  und Diashow als Kachel-/IPSView-Widget mit unabhängigen Designern, Farbzuordnung,
  Bildwechsel, Pause, Beschriftung und Erhalt des Clientzustands bei Updates.
  Der gemeinsame Kontocache bietet Kompakt (50/100 MiB), Standard (250/500 MiB),
  Groß (1000/2 GiB) und eigene Grenzen, LRU-Verdrängung sowie bedarfsgerechtes
  Nachladen größerer Alben. Apple-Sitzungen und Download-Adressen bleiben privat.


- Die Geräteinstanz ergänzt **Verloren-Modus deaktivieren** mit eigenem
  Ja/Nein-Dialog und `ICID_DeactivateLostMode()`. Auch Skripte benötigen eine
  ausdrückliche Bestätigung. Der Auftrag verwendet feste Ausschaltwerte ohne
  Kontaktangaben oder Code und kann vor der Bestätigung eines Aktivierungsauftrags
  angefordert werden. Status und Variablen-IDs bleiben erhalten; Fehler lösen
  weder eine automatische Wiederholung noch eine ersatzweise Aktivierung aus.

- Die Geräteinstanz bietet die Aktivierung des **Verloren-Modus** mit
  Rückrufnummer, Sperrbildschirmnachricht und optionalem neuem Gerätecode.
  Ein Ja/Nein-Dialog sichert jeden Formularauftrag ab; Skriptaufrufe benötigen
  eine ausdrückliche Bestätigung. Eingaben bleiben flüchtig. Das Kontomodul
  prüft Gerätezuordnung und Unterstützung und sendet ohne automatische
  Wiederholung. Die Statusvariable folgt weiterhin den Apple-Gerätedaten;
  eine Auftragsannahme wird nicht als abgeschlossene Aktivierung angezeigt.

- Die Geräteinstanz bietet **Handy suchen (Ton abspielen)** und
  `ICID_PlaySound()` für das konfigurierte Gerät. Die zusätzliche Aktionsvariable
  **Handy suchen** lässt sich direkt als Kachel mit dem Taster **Ton abspielen**
  verwenden. Nach dem Update die Instanzkonfiguration übernehmen. Bestehende
  Variablen behalten ihre IDs. Der Auftrag läuft über das
  angemeldete Konto und wird nur für dessen gelistete Geräte gesendet. Das
  Formular meldet Annahme oder Fehler; bei nicht erreichbaren Geräten kann der
  Ton später ertönen. Datenupdates lösen keinen Ton aus. Fehler führen zu keiner
  automatischen Wiederholung; bestehende Gerätevariablen bleiben erhalten.

- Der IPSView-Designer der WoIst-Karte erhält eine eigene Farbzuordnung für
  Karte, Bedienleiste, Popup und Geräteliste. 19 nummerierte Zuordnungen
  erklären IPSView-Stil und Positionsfarben; OpenFreeMap-Farben bleiben direkt
  in ihren Formularfeldern beschriftet und einstellbar. Die
  Beschriftungen entsprechen den deutschen beziehungsweise englischen
  Designer-Feldnamen.

- Der IPSView-Designer der Anwesenheit enthält eine beschriftete Farbkarte für
  Widget und WidgetCompact mit zwölf Farbrollen und deutscher Legende. Das
  schematische Beispiel erklärt auch Auswahlflächen, Statusfärbung und Schatten.

- **WidgetCompact** ergänzt die Anwesenheitsdarstellung um Gerätebild, Name und
  Zone mit schmaler Gesamtanwesenheit. Die bisherige Ausgabe bleibt erhalten.
  IPSView erhält eine zusätzliche HTML-Variable; die native Kachel bietet die
  Variantenwahl im Designer. Beide nutzen vorhandene Stile und Bilder und
  erhalten Geräteauswahl und Scrollposition bei Datenupdates.

- Gerätebilder der Anwesenheit werden beim lokalen Upload automatisch skaliert
  und komprimiert. Der zentrale `RasterImageHelper` verarbeitet Bilder bis
  10 MiB und 16 Millionen Pixel auf höchstens 512 Pixel pro Seite und 128 KiB.
  Proportionen, Transparenz und unterstützte JPEG-Ausrichtung bleiben erhalten.
  Ein Cache verhindert erneute Komprimierung bei Datenupdates. Das neue
  Helper-Abonnement muss vor Installation über die automatische Verteilung
  übernommen werden.

- **iCloud Anwesenheit** kann Gerätebilder direkt vom Rechner laden, auf dem die
  Konsole geöffnet ist. Je Gerät lässt sich zwischen Symcon-Medium und lokalem
  Datei-Upload wählen. Beide Wege prüfen PNG, JPEG, GIF und WebP auf höchstens
  128 KiB und 1024 × 1024 Pixel; bestehende Bildzuordnungen bleiben erhalten.

- **iCloud Anwesenheit** erhält kompakte Kachel- und IPSView-Widgets mit
  Gesamtanwesenheit, Einzelgeräten und optionalem Bildmedium je Gerät. Unabhängige
  Designer nutzen zentrale Farb-, Stil-, Schrift- und Layoutvorgaben. Datenupdates
  erhalten Auswahl und Scrollposition; IPSView aktualisiert den geschützten Cache
  ohne Neuladen der HTML-Variable. Der Widget-Baustein samt Ressourcen und Tests
  bleibt innerhalb der Library; die vorhandenen zentralen Basishelper werden
  unverändert weiterverwendet. Kein zusätzliches Helper-Abonnement erforderlich.

- **iCloud Anwesenheit** bietet je Zone eigene GPS-Vorgaben für Grenzpuffer,
  maximale Standortungenauigkeit und Bestätigungszeit. Ohne Aktivierung gelten
  weiterhin die globalen Werte. Eintritt und Austritt prüfen Herkunft und Ziel;
  die längere Bestätigungszeit gilt. Ungenaue Folgedaten können einen strengen
  Austritt nicht umgehen. Zuerst das zentrale GeofenceHelper-Update mit
  `ResolveGeofenceZoneSettings` über den regulären Helper-Sync integrieren.

- **iCloud Anwesenheit** verwaltet mehrere ausgewählte Geräte mit gemeinsamen
  Zonen in einer Instanz. Neben getrennten Gerätewerten zeigt die Gruppe
  Gesamtanwesenheit und Anzahlen für Anwesend, Abwesend und Unbekannt.
  Mindestens ein anwesendes Gerät genügt für Anwesend; Abwesend erfordert alle
  Geräte als zuverlässig abwesend. Der Konfigurator erstellt eine Gruppe je
  Konto statt einer Instanz je Gerät. Bestehende Einzelgeräte-Auswahl, Zonen
  und Variablen-IDs werden übernommen. Zuerst das reguläre GeofenceHelper-Update
  mit der Gruppenaggregation integrieren, danach das Modulupdate durchführen.

- Neues Modul **iCloud Anwesenheit** (ICPR): konfigurierbare Kreiszonen je Gerät,
  genau eine Referenzzone, lesbare Anwesenheit mit **Unbekannt**, Entfernung zum
  Referenzmittelpunkt und beobachtete Aufenthaltsdauer. Der zentrale
  GeofenceHelper stabilisiert GPS-Daten mit Genauigkeitsgrenzen, Grenzpuffer,
  zeitlich getrennten Bestätigungen und einem Filter für unplausible Sprünge.
  Der Geräte-Konfigurator erhält eine eigene Liste für Anwesenheitsinstanzen.
  Es entstehen keine zusätzlichen Apple-Abfragen; vor der Installation muss
  das neue GeofenceHelper-Paket über den regulären Helper-Sync eingegangen sein.

- iCloud-Geräteinstanzen zeigen zusätzliche Angaben zu Stromsparmodus, Ortung, Verloren-Modus, Aktivierungssperre, Familienfreigabe und technischer Modellkennung. Die zehn neuen Statusvariablen unterscheiden **Nicht verfügbar**, **Nein** und **Ja**; fehlende Angaben und Kontofehler lassen keine alten Statuswerte stehen. Nach dem Update die Geräteinstanzkonfiguration übernehmen und die nächste Kontoaktualisierung abwarten.

- Geoapify steht als zweiter Adressanbieter neben dem eigenen Nominatim-Server zur Wahl. Eigener API-Key, einstellbares UTC-Tageslimit je WoIst-Instanz (Vorgabe 2.500), Verbrauchsanzeige und automatische Fortsetzung am nächsten Tag ergänzen die bisherigen Genauigkeitsregeln. Cachetreffer bleiben am Limit verfügbar; Quellenhinweise erscheinen in Kachel und IPSView. Bestehende Nominatim-Einstellungen bleiben erhalten.

- Die Adressanzeige berücksichtigt die Standortgenauigkeit über den zentralen ReverseGeocodingHelper: Gebäude bis einschließlich 20 m, Straße bis 100 m, Gebiet bis 1000 m und sonst Ort. Ungenaue oder unbekannte Positionen erhalten in Geräteliste, Popup und Gerätevariable einen deutlichen Ungefähr-Hinweis. Alte präzise Cachetreffer werden bei schlechterer Genauigkeit sofort ausgeblendet.

- iCloud-Geräteinstanzen erhalten die lesbare Variable **Adresse** aus dem vorhandenen WoIst-Adresscache, ohne zusätzliche Nominatim-Abfragen. Eine passende Karte am selben Konto wird automatisch verwendet; bei mehreren Karten lässt sich die Quelle auswählen. Nach dem Update die Geräteinstanzkonfiguration einmal übernehmen.

## Fixes

- Das Anwesenheitsformular verhindert gleich große oder kleinere Zonenradien
  gegenüber dem Grenzpuffer durch voneinander abhängige Eingabegrenzen.
  Änderungen an der Zonenliste und am Puffer werden vor dem Übernehmen
  berücksichtigt. Ungültige gespeicherte Kombinationen benennen die betroffene
  Zone und beide Werte, ohne Einstellungen automatisch zu verändern.

- Die zusätzlichen Integer-Statusvariablen des iCloud-Geräts zeigen **Nicht verfügbar**, **Nein** und **Ja** statt der Rohwerte -1, 0 und 1. Der zentrale VariablePresentationHelper verwendet dafür native Zahlenintervalle; Variablen-IDs, Typen und gespeicherte Werte bleiben erhalten.

- Nominatim und Geoapify zeigen dieselbe kompakte Adressstruktur ohne zusätzliche Verwaltungshierarchie. Die Darstellung gilt für Geräteliste, Popup und Gerätevariable; Genauigkeitsgrenzen bleiben erhalten. Alte Cachetexte werden im Hintergrund neu aufgelöst und das Geoapify-Tagesbudget bleibt bestehen.

- **Bei Apple aktualisieren** steht auch in der IPSView-Karte zur Verfügung. Der feste, authentifizierte Aufruf nutzt die bestehende Kontoabfrage samt Wartezeit und aktualisiert die Daten ohne Neuladen oder Verlust der Kartenansicht.

- Die WoIst-Geräteauswahl hebt den zugehörigen Kartenmarker mit größerem Punkt und stärkerem Rand hervor und bringt ihn vor überlappende Marker. Die Listenwahl zentriert die Karte auf das Gerät; Datenupdates erhalten die gewählte Ansicht und Hervorhebung.

- Gemeinsame Mausrad-, Scroll- und Frame-Unterstützung aus Symcon_ModuleHelper eingebunden; IPSView-Ereignisse mit umadressiertem Ziel werden zentral behandelt.

- WoIst bindet die zentralen Kachelfarben und den vollständigen IPSView-Stileditor einschließlich Stilquellen, Transparenz und Typografie ein. Die Farbrollen wirken auf die Kartenoberfläche und werden ohne Neuladen aktualisiert; beide Helper-Pakete einschließlich ihrer Abhängigkeiten und Ressourcen sind für den automatischen Sync abonniert.

- Die native WoIst-Kachel stellt nach einem iframe-Neuaufbau bei Wiederverbindung Zoom, Kartenausschnitt, Geräteauswahl und Popup aus dem zentralen OpenFreeMap-Sitzungsspeicher wieder her. Entfernte Geräte werden nicht erneut ausgewählt; gesperrter Browser-Speicher verhindert die Kartenanzeige nicht.

- Das eingebundene OpenFreeMap-Helper-Manifest enthält jetzt auch die Abhängigkeiten `HelperTranslationHelper` und `VisualizationAssetHelper` einschließlich ihrer Ressourcen.

- Der Kartenrenderer wird lokal und komprimiert über einen öffentlichen Asset-Endpunkt ausgeliefert, damit native Kachel und HTTP-Seite unter Symcons Ausgabelimit bleiben. Konfliktmarkierungen im vorhandenen Helper-Manifest wurden mit den kanonischen Metadaten abgeglichen.

- Die eingebettete IPSView-Ausgabe ermittelt die Serveradresse über den zentralen HTTP-Ursprungsresolver automatisch aus dem einbettenden Kontext. Die HTTP-Kartenseite nutzt einen expliziten Asset-Pfad für den Symcon-WebHook-Kontext und meldet fehlende Assets mit HTTP 503.

- WoIst registriert seinen flüchtigen internen WebHook bedingungslos beim Laden der Instanz. Das Aktivieren und Deaktivieren der IPSView-Ausgabe steuert nur Zugriff und Token; fehlgeschlagene Registrierungen werden als Fehler gemeldet.

- Gemeinsame Erkennung und Platzreservierung der nativen Kachelüberschrift sowie der höhenfüllende Visualisierungsrahmen werden aus dem zentralen ResponsiveVisualizationHelper bezogen.

- WoIst berücksichtigt die native Kachelüberschrift und passt Karte und Geräteliste an Breite und Höhe der Kachel an. Schmale Kacheln stapeln die Bereiche; die Geräteliste scrollt innerhalb der verfügbaren Fläche. Größenänderungen erhalten Mittelpunkt, Zoom und Auswahl.

- IPSView lädt die WoIst-Karte als echte HTTP(S)-Seite aus Symcon. Kartenkacheln erhalten dadurch den tatsächlichen Server-Ursprung als Referer. Geräte- und Standortdaten bleiben geschützt; die Seite und die bestehende Ansicht werden bei Datenänderungen nicht neu geladen.

- WoIst aktualisiert geöffnete IPSView-Karten über einen geschützten, ausschließlich lesenden Zustandsabruf. Datenänderungen ersetzen das HTML nicht mehr; Zoom, Kartenausschnitt und Geräteauswahl bleiben erhalten. Verbindungsfehler führen zu einem Hinweis und Wiederholungen statt zu einem Neuladen.

- Der iCloud-Geräte-Konfigurator bleibt nach dem Übernehmen bei gültigem Kontocache aktiv und meldet fehlende Verbindung oder nicht verfügbare Daten mit dem passenden Status.
- WoIst-Konfigurator und Geräte lassen sich im Einrichtungsassistenten mit einem vorhandenen iCloud-Konto verbinden; die Ermittlung kompatibler übergeordneter Instanzen liefert jetzt das von Symcon erwartete Format.

## Neu

- Optionale Nominatim-Adressauflösung mit konfigurierbarer eigener Serveradresse und Sprache. Der zentrale ReverseGeocodingHelper löst Positionen im Hintergrund auf und speichert Ergebnisse persistent mit begrenztem Cache und Fehlerwartezeit. Adressen erscheinen in Geräteliste und Popup von Kachel und IPSView, ohne die Kartenansicht oder Auswahl zurückzusetzen.

- WoIst nutzt OpenFreeMap-Vektorkarten. Kachel- und IPSView-Designer besitzen eigene OpenFreeMap-Menüs mit Auto-/Hell-/Dunkelmodus, Kartenstilen, eigenen Stil-URLs, Kartendetails, Beschriftungssprache und getrennten Farbpaletten. Daten- und Designupdates erhalten die Kartenansicht und Geräteauswahl.

- WoIst erhält einen Kachel- und IPSView-Designer für Größe, Standortfarben und sichtbare Elemente. Die optionale IPSView-HTML-Variable aktualisiert sich mit den Kontodaten; ihr Design kann von der Kachel übernommen oder unabhängig eingestellt werden.

- Der Gerätekonfigurator wird ausdrücklich als iCloud Geräte Konfigurator angeboten; das Präfix ICDC bleibt unverändert.

- Neues Modul iCloud WoIst (ICWI) mit interaktiver Karte aller Geräte des verbundenen Kontos, Standortalter, Genauigkeit und Anzeige nicht ortbarer Geräte. Bei Apple aktualisieren fragt ausdrücklich das Konto ab; Kartenöffnung und Cache-Aktualisierung starten keine Apple-Anmeldung.
- OpenStreetMap-Kartenhintergrund mit lokal ausgeliefertem Leaflet; Kachel-URL und Quellenangabe sind konfigurierbar. Beim Kartenanbieter werden die sichtbare Kartenregion und die Browser-IP angefragt.
- Geräteinstanz und Konfigurator heißen jetzt iCloud Gerät und iCloud Geräte Konfigurator; die Präfixe sind ICID und ICDC. Achtung: Die bisherigen Test-APIs ICWD und ICWC entfallen. Testinstanzen können bei Bedarf neu angelegt werden.
- WoIst ist ausschließlich für ein späteres Kartenmodul vorgesehen; eine Karte ist in diesem Stand nicht enthalten.
- Erste SymconiCloud-Library mit eigenständigem iCloud-Konto und lesender WoIst-Geräteanbindung.
- Apple-Anmeldung mit SRP und sechsstelliger Zwei-Faktor-Bestätigung über ein vertrauenswürdiges Gerät.
- Persistente Sitzungen, kontrollierte Wiederherstellung und getrennte Gerätecaches je Konto.
- WoIst-Konfigurator und ausschließlich lesbare Gerätevariablen für Akku, Gerätestatus und vorhandene Standortdaten.
- Fehler- und Verfügbarkeitsanzeigen sowie Wartezeiten bei Apple-Anfragelimits.
