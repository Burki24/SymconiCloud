# iCloud Contacts / iCloud Kontakte

Lesendes Empfängerverzeichnis für Kontakte aus dem verbundenen iCloud-Konto.
Die Instanz zeigt Namen, Firma sowie beschriftete Telefonnummern und
E-Mail-Adressen. Kontakte werden nicht geändert; die Instanz versendet keine
Nachrichten. Kontakte anderer Konten, etwa Google oder Exchange, werden nicht
automatisch übernommen.

## Einrichtung

1. Das vorhandene **iCloud Konto** verwenden und dort unter **iCloud Kontakte
   (optional)** den **lesenden Kontaktzugriff** aktivieren.
2. Das Kontakt-Aktualisierungsintervall festlegen: standardmäßig 3600 Sekunden,
   zulässig sind 900 bis 604800 Sekunden.
3. **iCloud Kontakte** anlegen und mit diesem Konto verbinden.
4. Bei bereits angemeldetem Konto **Kontakte aktualisieren** wählen. Die Instanz
   benötigt keine zusätzlichen Zugangsdaten. Bei abgelaufener Sitzung das Konto
   ausdrücklich erneut anmelden; Kontaktabfragen starten keine Passwortanmeldung.

Der Kontotimer lädt den Bestand unabhängig von der Geräteabfrage. Mehrere
Kontaktinstanzen desselben Kontos verwenden denselben Cache. Zwischen manuellen
Abrufversuchen liegen mindestens 60 Sekunden. Kontoschutz und Apple-Wartefristen
können die Aktualisierung zusätzlich verzögern. Das Öffnen des Formulars, die
Suche, das Blättern und die öffentlichen Lesefunktionen fragen Apple nicht ab.

## Anzeige und Verfügbarkeit

**Kontakte suchen** durchsucht Namen, Firma, Telefonnummern und E-Mail-Adressen.
Mehrere Telefonnummern beziehungsweise E-Mail-Adressen werden mit ihren
Bezeichnungen gemeinsam angezeigt.
**Nächste Seite** zeigt weitere Treffer; **Erste Seite** setzt das Blättern zurück.
Fehlende Nummern und E-Mail-Adressen erscheinen als **Nicht verfügbar**.
Es werden keine bevorzugte Nummer und kein Versandkanal automatisch geraten.

Falls vor einem Modulupdate vorhandene Adressen als **Nicht verfügbar** angezeigt
wurden, den Bestand danach mit **Kontakte aktualisieren** erneut laden. Der
bisherige Cache enthält die zuvor ausgelassenen Adresswerte nicht.

Status 201 bedeutet, dass kein Konto verbunden ist. Status 202 bedeutet, dass
noch kein Kontaktbestand verfügbar ist. Status 203 kennzeichnet einen
gespeicherten, derzeit nicht aktuellen Bestand. Zeitpunkt und Fehlerhinweis
werden im Formular angezeigt. Ein erfolgreich geladenes leeres Adressbuch ist
gültig und entfernt die zuvor gespeicherten Kontakte.

Bei einer Abfragepause bleiben Sitzung und Cache erhalten. Nach Fehlern bleibt
der letzte vollständige Kontaktbestand lesbar und wird als nicht aktuell
gekennzeichnet. Das Abschalten des Kontaktzugriffs, Abmelden, Deaktivieren des
Kontos oder Übernehmen anderer Zugangsdaten entfernt den Kontaktcache.

Unterstützt werden bis zu 10000 Kontakte, 4 MiB Antwort- beziehungsweise
normalisierte Cachegröße und je 20 Telefonnummern und E-Mail-Adressen pro
Kontakt. Ein normalisierter Kontakt darf höchstens 30000 JSON-Bytes umfassen;
Textfelder sind auf 256 Unicode-Zeichen begrenzt. Lange Namen und Beschriftungen
werden gekürzt; überlange Versandadressen werden nicht abgeschnitten, sondern
führen zur Ablehnung der Aktualisierung. Größere Bestände oder
unvollständige/ungültige Antworten ersetzen den bisherigen Cache nicht.
Kontaktbilder, Gruppen, Notizen, Geburtstage und Postadressen sind nicht Teil
dieses Verzeichnisses.

## Öffentliche Funktionen

| Funktion | Bedeutung |
| --- | --- |
| `ICCT_GetContacts(int $InstanceID, string $Query = '', int $Offset = 0, int $Limit = 100)` | JSON-Seite aus dem Cache; Suchtext maximal 256 Bytes, Offset 0–10000, Limit 1–100 |
| `ICCT_GetContact(int $InstanceID, string $ContactID)` | Exakter Kontakt aus dem Cache; `contact: null`, wenn die Kennung fehlt |
| `ICCT_Refresh(int $InstanceID)` | Gemeinsamen Kontocache ausdrücklich aktualisieren; boolesches Erfolgsergebnis |
| `ICCT_SearchContacts(int $InstanceID, string $Query)` | Formularsuche setzen und zur ersten Seite wechseln |
| `ICCT_FirstContacts(int $InstanceID)` / `ICCT_NextContacts(int $InstanceID)` | Formularseiten wechseln |
| `ICCT_Reload(int $InstanceID)` | Formular aus dem vorhandenen Cache neu aufbauen |

Lesefunktionen liefern `success`, `stale`, `paused`, `updatedAt` und `error`.
`success` bedeutet, dass ein erfolgreicher Cache vorhanden ist; nur zusammen
mit `stale: false` ist dieser aktuell. `GetContacts` ergänzt `contacts`, `total`,
`offset`, `limit` und `nextOffset`. Seiten sind zusätzlich auf etwa 32 kB
Kontaktdaten begrenzt und können weniger als `Limit` Einträge enthalten. Zum
Weiterlesen immer `nextOffset` verwenden; `null` kennzeichnet das Ende.

Ein Kontakt enthält `id`, `displayName`, `firstName`, `lastName`, `company`,
`phones` und `emails`. Die Adresslisten enthalten jeweils `label` und `value`;
fehlende Listen sind leer. Das Verzeichnis besitzt keine Statusvariablen mit
dem vollständigen Kontaktbestand.

Für das geplante Nachrichten-Modul einen Empfänger durch **Kontaktinstanz-ID
und Kontakt-ID gemeinsam** referenzieren und Nummer beziehungsweise E-Mail
ausdrücklich auswählen. Namen sind keine technischen Schlüssel. Fehlende
Kontakte dürfen nicht durch gleichnamige Personen ersetzt werden. Bei
veraltetem Bestand muss der Verbraucher eine bewusste Versandregel anwenden.
Die Verfügbarkeit einer Telefonnummer ist kein Nachweis einer Registrierung
bei einem Messenger; dafür werden separate Versanddienste benötigt.

Das Modul verwendet Apples Web-Endpunkte. Änderungen bei Apple können den
Zugriff beeinträchtigen. Telefonnummern und E-Mail-Adressen sind vertrauliche
Daten; die Lesefunktionen nur vertrauenswürdigen Skripten bereitstellen.
