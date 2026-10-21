# iCloud WoIst

WoIst zeigt die Positionen der Geräte eines iCloud-Kontos gemeinsam auf einer interaktiven Karte. Einzelne iCloud-Geräteinstanzen sind dafür nicht erforderlich. Voraussetzung sind Symcon 9.0 und ein angemeldetes iCloud-Konto dieser Library.

## Einrichtung

1. **iCloud WoIst Karte** anlegen und mit dem gewünschten iCloud-Konto verbinden.
2. Die Instanz zur Symcon-Kachelvisualisierung hinzufügen. Eine Instanz zeigt jeweils alle Geräte ihres verbundenen Kontos.
3. Ein Gerät aus der Liste wählen, um seine Position zu öffnen. **Alle Positionen anzeigen** passt den Kartenausschnitt an die verfügbaren Positionen an.

Die Karte erhält neue Daten automatisch vom Konto. **Bei Apple aktualisieren** löst eine ausdrückliche Geräteabfrage über das Konto aus und beachtet dessen Anmeldestatus und Wartezeiten. Das Öffnen der Karte oder des Formulars liest nur den Kontocache und löst keine Apple-Anmeldung aus.

## Kachel- und IPSView-Designer

Im **Kachel-Designer** lassen sich Schriftgröße, Radius und Farben der Standortmarkierungen sowie Überschrift, Geräteliste und Bedienelemente einstellen. Einstellungen anschließend übernehmen. Karte und Geräteliste nutzen die verfügbare Breite und Höhe der Kachel. Auf schmalen Kacheln steht die Liste unter der Karte und scrollt innerhalb ihres Bereichs. Bei ausgeblendeter Liste nutzt die Karte die gesamte freie Fläche. Die bisherige Kartenhöhe dient nur noch als Fallback; die tatsächliche Kachelgröße hat Vorrang. Neue Instanzen verwenden dafür automatisch `0`.

In der nativen Symcon-Kachel wird Platz für deren sichtbare Kachelüberschrift reserviert. Bei ausgeblendeter Kachelüberschrift entfällt dieser Abstand, soweit die Symcon-Version den Zustand bereitstellt. Die HTTP-Seite für IPSView reserviert keinen zusätzlichen nativen Titelbereich.

Für IPSView im **IPSView-Designer** die Ausgabe aktivieren und die Konfiguration übernehmen. Das Modul erzeugt die String-Variable **WoIst für IPSView** (`IPSViewWoIst`) mit einer kleinen HTML-Einbindung. Diese lädt die eigentliche Karte als echte HTTP(S)-Seite vom Symcon-Server. Diese Variable in IPSView als WebContent einbinden. Nach einem Modulupdate die Instanzkonfiguration einmal übernehmen, damit vorhandene HTML-Ausgaben den aktuellen Datenabruf enthalten. Standardmäßig gilt **Kachel-Design verwenden**. Für unabhängige Gestaltung diese Option deaktivieren oder **Gespeichertes Kachel-Design kopieren und unabhängig bearbeiten** wählen. Die Kopieraktion übernimmt die bereits gespeicherten Kachel-Einstellungen, speichert die IPSView-Einstellungen und lädt das Formular neu.

Die Kartenseite besitzt eine eigene HTTP(S)-Adresse. Dadurch können Kartenanfragen den tatsächlichen Symcon-Server-Ursprung als Referer mitsenden, auch wenn die äußere IPSView-Ausgabe als `data:`-Seite eingebettet ist. Der Kartenanbieter und dessen Nutzungsbedingungen gelten weiterhin; eine bereits bestehende Sperre wird dadurch nicht garantiert aufgehoben.

Die geöffnete IPSView-Karte liest alle fünf Sekunden ausschließlich den gespeicherten WoIst-Zustand über einen geschützten WebHook. Neue Kontodaten aktualisieren Marker und Liste innerhalb derselben Seite. Zoom, Kartenausschnitt und die Auswahl eines weiterhin gelisteten Geräts bleiben erhalten; auch eine bewegte ausgewählte Position zieht die Karte nicht automatisch mit. Geräte ohne Position bleiben auswählbar in der Liste. Wird ein Gerät aus der Kontoliste entfernt, wird seine Auswahl aufgehoben. **Alle Positionen anzeigen** und eine bewusste Geräteauswahl ändern weiterhin den Kartenausschnitt.

Unter **Symcon-Server-URL für IPSView** bei Bedarf die aus IPSView erreichbare Server-Adresse einschließlich HTTP(S) und gegebenenfalls Port, aber ohne Pfad eintragen. Beispiele: `http://symcon-server:3777` (Servernamen ersetzen) oder `https://symcon.example.org` bei einem Reverse-Proxy. Kein `/hook/...` anhängen; diesen Pfad ergänzt das Modul selbst. Das ist insbesondere bei einer nativen IPSView-App oder einer eingebetteten `data:`-/`about:`-Seite ohne eigene HTTP(S)-Adresse erforderlich. In einer HTTP(S)-Seite auf demselben Symcon-Server darf das Feld leer bleiben. HTTPS verwenden, wenn der Zugriff nicht über ein vertrauenswürdiges lokales Netzwerk erfolgt. Der Browser muss diese Adresse erreichen können; HTTPS-Seiten können HTTP-Abfragen blockieren.

Bei Verbindungsproblemen zeigt die Karte einen Hinweis, behält den letzten Zustand und versucht die Abfrage erneut, ohne die Seite neu zu laden. Bei ausgeblendeter Seite pausiert sie die Abfragen. Diese Abfragen lösen weder eine Apple-Anmeldung noch einen zusätzlichen Geräteabruf aus. Die Apple-Aktualisierung erfolgt im Konto oder in der nativen Kachel. Nur Änderungen an der Instanzkonfiguration oder eine ausdrücklich angeforderte Neuerzeugung können das HTML-Dokument ersetzen.

Der WebHook akzeptiert ausschließlich lesende, mit einem eigenen zufälligen Zugriffstoken geschützte Abfragen. Dieser Token ist unabhängig von der Apple-Sitzung und wird in der IPSView-Einbindung gespeichert; diese Ausgabe deshalb vertraulich behandeln. Die Einbindung übergibt ihn als URL-Fragment an die Kartenseite. Das Fragment wird vor den ersten Abfragen aus der Seitenadresse entfernt und nicht an den HTTP-Server oder Kartenanbieter gesendet. Datenabrufe verwenden anschließend ausschließlich den Token-Header. Die öffentlich abrufbare Kartenseite selbst enthält weder Token noch Geräte-, Standort- oder private Anbieterparameter. Beim Deaktivieren der Ausgabe oder beim Wechsel des verbundenen Kontos verliert der bisherige Token seine Gültigkeit.

Beim Deaktivieren bleibt die HTML-Variable mit ihrem letzten Inhalt erhalten und wird nicht weiter aktualisiert. Sie kann über die dann angebotene Löschfunktion des Ausgabe-Helpers entfernt werden. Die gespeicherte HTML-Einbindung enthält den lesenden Zugriffstoken; die üblichen Symcon-Zugriffsrechte und der Schutz von Backups gelten auch für diese Variable. Die native Kachel verwendet weiterhin das Symcon-HTML-SDK.

## Daten und Aktualität

Die Karte verwendet die von Apple zuletzt gelieferten Koordinaten. Aktuelle und ältere Positionen sind unterschiedlich markiert. Zu jeder Position werden Zeitpunkt und, soweit verfügbar, Genauigkeit angezeigt. Geräte ohne Position bleiben in der Liste sichtbar, erhalten aber keinen erfundenen Kartenpunkt. Offline-Geräte können eine letzte bekannte Position besitzen.

Ein aktueller Kontoabruf bedeutet nicht automatisch eine aktuelle Geräteposition. Nach Ablauf der Altersgrenze oder bei Kontofehlern gelten angezeigte Positionen als letzte bekannte Standorte. Die vorhandene Kontoabfrage fordert keine aktive Neuortung an; eine Live-GPS-Verfolgung oder die vollständige Funktionalität der Apple-App wird nicht zugesagt.

## Konfiguration

| Eigenschaft | Standard | Bedeutung |
| --- | --- | --- |
| `StaleAfter` | 900 | Positionen nach dieser Anzahl Sekunden als älter markieren; 60 bis 86400 |
| `TileURL` | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` | HTTPS-URL des Kartenanbieters mit den drei Platzhaltern |
| `TileAttribution` | © OpenStreetMap contributors | Sichtbare Quellenangabe des Kartenanbieters |
| `IPSViewServerURL` | leer | Aus IPSView erreichbare HTTP(S)-Server-Adresse ohne Pfad; leer nutzt die eigene HTTP(S)-Seitenadresse |

OpenStreetMap ist der Standardhintergrund. Die Kartendarstellung benötigt Internetzugriff im verwendeten Browser. Dieser lädt die Kacheln für den sichtbaren Kartenausschnitt beim Anbieter; der Anbieter erhält damit IP-Adresse und angefragte Kartenregion. Gerätenamen, Apple-Zugangsdaten und Tokens werden nicht an ihn gesendet. Bei eigenem Kartenanbieter dessen Bedingungen und Quellenangaben beachten. Leaflet wird lokal mit der Library ausgeliefert; es wird kein JavaScript von einem CDN nachgeladen.

Ein eigener Apple-Maps-Hintergrund ist nicht enthalten. Apple MapKit JS benötigt einen separaten Maps-Token aus einem Apple-Developer-Account; die iCloud-Anmeldung ersetzt diesen nicht.

## Öffentliche Funktionen

| Funktion | Rückgabe | Wirkung |
| --- | --- | --- |
| `ICWI_Update(int $InstanceID)` | bool | Kontocache lesen und offene Karten aktualisieren |
| `ICWI_RefreshDevices(int $InstanceID)` | bool | Einmalige ausdrückliche Abfrage über das Konto |
| `ICWI_GetState(int $InstanceID)` | string | JSON mit Kartendaten, Beschriftungen und Kartenoptionen aus dem Cache |

Das Modul erzeugt keine Gerätevariablen; die optionale IPSView-HTML-Variable dient nur der Darstellung. Status 201 bezeichnet ein fehlendes Konto, 202 nicht verfügbare Kontodaten und 203 eine ungültige Kartenkonfiguration. Alte Positionen können bei einem Kontofehler weiterhin zur Einordnung angezeigt werden.

## Lizenz

Eigenes Modul: [PolyForm Noncommercial License 1.0.0](../LICENSE). Leaflet 1.9.4: BSD-2-Clause, siehe [Leaflet-Lizenz](../libs/vendor/leaflet/LICENSE). Kartendaten: [© OpenStreetMap contributors](https://www.openstreetmap.org/copyright).

Der interne WebHook `SymconiCloudWoIst<Instanz-ID>` wird beim Laden der Instanz unabhängig vom IPSView-Schalter registriert und nach jedem Symcon-Neustart erneut angelegt. Bei deaktivierter IPSView-Ausgabe liefert er 404; vorhandene Zugriffstokens werden ungültig. Nach einem Update auf diese Registrierung muss die Modulinstanz neu geladen werden, beispielsweise durch einen Symcon-Neustart.

Eine Server-IP wird nicht grundsätzlich benötigt: In einer HTTP(S)-Seite auf demselben Server verwendet die Einbindung automatisch den aktuellen Ursprung. Native IPSView-Apps und `data:`-/`about:`-Einbettungen besitzen keinen verwendbaren HTTP(S)-Ursprung; dort ist die vom Anzeigegerät erreichbare Serveradresse erforderlich. Das kann auch ein DNS-Name oder eine HTTPS-Adresse über einen Reverse-Proxy sein. Eine automatisch ermittelte lokale IP wäre außerhalb des lokalen Netzes unter Umständen nicht erreichbar.
