# ADR 0011: Explizite Suchton-Aktion der Geräteinstanz

- Status: Angenommen
- Datum: 24.10.2026
- Ergänzt ADR 0001 um genau einen schreibenden Gerätebefehl

## Entscheidung

Die Geräteinstanz bietet `ICID_PlaySound(int $InstanceID): bool` und einen
Formularbutton. Die Ziel-ID stammt ausschließlich aus der angewendeten
Gerätekonfiguration. Der vorhandene DataFlowHelper transportiert den festen
Befehl `PlaySound` mit `DeviceID` an das physisch verbundene Kontomodul.
Zusätzliche Befehlsparameter, frei wählbare URLs und unbekannte Geräte werden
vor einem Netzwerkzugriff abgewiesen.

Das Konto prüft Konfiguration, Zugangsdatenidentität, Sitzung, Sperrfrist und
Zugehörigkeit zum eigenen Gerätecache unter seinem bestehenden Semaphore.
Die Apple-Protokolllogik bleibt librarylokal in AppleClient; HTTPS und Cookies
bleiben im Konto. Vorhandene zentrale Formular-, Datenfluss- und Parent-Helper
werden unverändert wiederverwendet. Eine generische HTTP-Proxyfunktion oder
ein neuer zentraler Helper entstehen nicht.

Ein expliziter Aufruf sendet höchstens einen POST an den festen
Find-My-Endpunkt `playSound`. Es gibt bei einem Fehler keine automatische
Wiederholung und keine automatische Passwortanmeldung. Auch ein Verbindungsfehler
kann nach bereits erfolgter Annahme auftreten; ein erneuter Aufruf bleibt eine
bewusste Benutzeraktion. Authentifizierungsfehler markieren die Sitzung als
abgelaufen, Apple-Sperrfristen gelten kontoweit. Reine Befehlsfehler invalidieren
keinen ansonsten nutzbaren Standortcache.

Ein erfolgreiches Ergebnis bestätigt die Annahme des Auftrags, nicht dessen
hörbare Ausführung. Geräte ohne Verbindung können den Ton später abspielen.
Rohantworten, Sitzung und Zugangsdaten gelangen nicht an Geräteinstanzen.
Die Aktion verändert keine vorhandenen Variablen-IDs oder Standortzeitpunkte.
Formularöffnung, ApplyChanges, Inventarabfragen und Timer bleiben ohne Suchton.

## Quellen und Nachweise

Protokollinformationen aus der
[Find-My-Dienstbeschreibung im pyicloud-Quelltext](https://github.com/picklepete/pyicloud/blob/master/pyicloud/services/findmyiphone.py)
werden als Schnittstellenreferenz verwendet; es wird keine Fremdimplementierung
übernommen. Die Bedeutung von Annahme, verzögerter Wiedergabe und Beenden am Gerät
folgt der [Apple-Anleitung](https://support.apple.com/en-gb/guide/icloud/mmfc0f19b5/icloud).

Offline-Tests prüfen das echte Konto-/Geräte-Routing mit synthetischem
HTTPS-Transport, Fehler- und Authentifizierungspfade, Sperrfristen, Datenschutz
und ausbleibende Wiederholungen. Praxisnachweise werden getrennt im
Projektkontext festgehalten; eine HTTP-Annahme bestätigt keine hörbare Wiedergabe.
