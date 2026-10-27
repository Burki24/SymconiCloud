<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/helper/DebugFlowHelper.php';

/** iCloud domain explanations. Correlation, formatting and masking belong to the central helper. */
trait CloudDebug
{
    use \Burki24\SymconModuleHelper\DebugFlowHelper;

    protected function CloudDebugCall(string $function, int $line, \Closure $operation, bool $withoutReturnValue = false): mixed
    {
        $meaning = match ($function) {
            'Login', 'LoginFromForm', 'VerifyCode'                   => ['Anmeldung nicht vollständig bestätigt; Ursache und Kontoschutz siehe Ablauf.', 'Kontositzung und Geräteabruf sind bestätigt; das Konto ist angemeldet.'],
            'ResendCode'                                             => ['Neue Codeanforderung nicht bestätigt; Grenzen oder Apple-Antwort siehe Ablauf.', 'Apple hat die Codeanforderung angenommen; die Zustellung ist nicht bestätigt.'],
            'PlaySound'                                              => ['Suchtonauftrag nicht bestätigt; er wird nicht automatisch wiederholt.', 'Apple hat den Suchtonauftrag angenommen; ein hörbarer Ton ist damit noch nicht bestätigt.'],
            'ActivateLostMode', 'DeactivateLostMode'                 => ['Auftrag zum Verloren-Modus nicht bestätigt; keine automatische Wiederholung.', 'Apple hat den Auftrag zum Verloren-Modus angenommen; die Gerätewirkung ist nicht bestätigt.'],
            'PreviewRecipientTransfer'                               => ['Empfängerübernahme konnte nicht vorbereitet werden; Ziel und Kontaktauswahl prüfen.', 'Vorschau vorbereitet; Empfängereinstellungen wurden noch nicht übernommen und keine Nachricht versendet.'],
            'ApplyRecipientTransfer'                                 => ['Empfängerübernahme nicht ausgeführt; Bestätigung, Vorschau und Ziel prüfen.', 'Empfängereinstellungen übernommen; keine Nachricht versendet.'],
            'Update', 'Refresh', 'RefreshDevices', 'Reload'          => ['Aktualisierung nicht erfolgreich; Datenquelle, Konfiguration und Verfügbarkeit siehe Ablauf.', 'Die im Modul ausgewerteten Daten sind verfügbar; dies bestätigt keine neue GPS-Messung.'],
            'ResolveAddresses'                                       => ['Keine Position bearbeitet; Konfiguration, Cache und Wartezeit prüfen.', 'Eine Position wurde bearbeitet; auch ein gespeicherter Anbieterfehler zählt dazu. Eine aufgelöste Adresse ist damit noch nicht bestätigt.'],
            'RefreshAlbums', 'RefreshPhotoAlbums', 'RefreshContacts' => ['Angeforderte Daten nicht aktualisiert; Ursache und Abfragelimits siehe Ablauf.', 'Die angeforderte Datenverarbeitung wurde erfolgreich abgeschlossen.'],
            default                                                  => ['Bedingung nicht erfüllt oder Auftrag nicht bestätigt; Einzelheiten siehe Ablauf.', 'Die im Funktionsvertrag beschriebene Bedingung ist erfüllt.']
        };
        return $this->DebugFlowCall($function, $line, $operation, $withoutReturnValue, [], $meaning);
    }

    /** Never send arbitrary legacy helper categories or exception messages to the native channel. */
    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        if (str_starts_with($Message, static::class . ' | ')) {
            return parent::SendDebug($Message, $Data, $Format);
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $this->DebugFlowEvent(
            $trace[1]['function'] ?? __FUNCTION__,
            (int) ($trace[0]['line'] ?? __LINE__),
            'FEHLER',
            'Eine gemeinsame Modulhilfe konnte den Vorgang nicht abschließen. Konfiguration und benötigte Visualisierungsdateien prüfen; private Fehlertexte werden nicht protokolliert.'
        );
        return true;
    }

    protected function CloudDebugReason(string $reason): string
    {
        return match ($reason) {
            'ok', 'sessionReused'                                                     => 'Vorgang verarbeitet; eine vorhandene Sitzung wird ohne neue Passwortanmeldung verwendet.',
            'verificationRequired', 'awaiting2FA', 'challengePending'                 => 'Ein Bestätigungscode ist erforderlich. Den aktuellen Gerätecode bestätigen, statt erneut eine Anmeldung zu starten.',
            'authenticationIncomplete'                                                => 'Apple hat die Authentifizierung mit HTTP 202 noch nicht abgeschlossen. Das ist keine erfolgreiche Anmeldung und kein Beweis für einen falschen Code. Konto im iCloud-Browser prüfen; keine automatische Wiederholung.',
            'authenticationDisabled'                                                  => 'Neue Anmeldungen sind auf diesem System ausgeschaltet. Die bewusste Systemeinstellung gilt auch für Codeanforderungen.',
            'requestsPaused'                                                          => 'Apple-Abfragen sind bewusst pausiert. Die Sitzung bleibt erhalten; zum Fortsetzen die Pause in der Kontokonfiguration ausschalten.',
            'accountBlocked'                                                          => 'Eine lokale Schutzsperre stoppt Apple-Anfragen. Nach Prüfung des Apple-Kontos die Sperre ausdrücklich freigeben; sie beweist keine Apple-Kontosperre.',
            'accountLocked'                                                           => 'Apple hat eine Sperrantwort geliefert. Konto bei Apple prüfen oder entsperren; bis zur manuellen Freigabe bleiben Anfragen gestoppt.',
            'accountReviewRequired'                                                   => 'Apple verlangt eine zusätzliche Kontoprüfung. Die angeforderten Schritte zuerst im iCloud-Browser abschließen.',
            'termsRequired'                                                           => 'Apple verlangt die Prüfung aktualisierter Nutzungsbedingungen im Browser. Das Modul stimmt nicht automatisch zu.',
            'credentials'                                                             => 'Apple hat den Austausch der Zugangsdaten abgelehnt. Das beweist kein falsches Passwort; Konto im Browser prüfen.',
            'authentication', 'expired'                                               => 'Die Kontositzung ist nicht mehr nutzbar. Nach Kontoprüfung ausdrücklich anmelden; Timer starten keine Passwortanmeldung.',
            'signedOut'                                                               => 'Das Konto ist abgemeldet. Zugangsdaten im Konto prüfen und die Anmeldung ausdrücklich starten; Cacheabfragen melden nicht automatisch an.',
            'invalidCode'                                                             => 'Apple hat den Bestätigungscode abgelehnt. Den aktuellen Code prüfen; keine automatische Wiederholung.',
            'codeFormat'                                                              => 'Der Bestätigungscode muss aus genau sechs Ziffern bestehen. Die Eingabe wurde nicht an Apple gesendet.',
            'challengeExpired'                                                        => 'Die Codebestätigung ist abgelaufen oder nicht offen. Eine neue Anmeldung ist nur ausdrücklich möglich.',
            'loginLimited', 'codeLimited', 'resendLimited', 'rateLimited'             => 'Eine Versuchssperre oder Apple-Wartezeit verhindert weitere Anfragen. Die angegebene Restzeit abwarten; Abmelden setzt Limits nicht zurück.',
            'retryBackoff', 'network', 'server'                                       => 'Eine Verbindungs- oder Serverstörung verhindert den Abruf. Die bestehende Sitzung bleibt soweit möglich erhalten; die nächste Anfrage beachtet die Wartezeit.',
            'accessDenied'                                                            => 'Apple hat den Zugriff abgelehnt. Berechtigungen und Konto im iCloud-Browser prüfen.',
            'configuration'                                                           => 'Die Konfiguration ist unvollständig oder ungültig. Konto aktivieren, erforderliche Felder prüfen und Änderungen übernehmen.',
            'protectionState'                                                         => 'Der gespeicherte Kontoschutz ist ungültig. Anfragen bleiben gestoppt; keine Umgehung der Sicherheitsgrenzen.',
            'dependency'                                                              => 'Eine benötigte PHP-Erweiterung fehlt. curl und bcmath in der Symcon-Laufzeit prüfen.',
            'srp', 'endpoint', 'response', 'internal'                                 => 'Die erwartete Antwort konnte nicht sicher verarbeitet werden. Es wird kein Erfolg angenommen und kein privater Antwortinhalt protokolliert.',
            'contactsDisabled'                                                        => 'Der optionale Kontaktzugriff ist im Konto ausgeschaltet.',
            'contactsAccess', 'contactsUnavailable'                                   => 'Das Kontaktverzeichnis ist nicht abrufbar. Kontaktfreigabe und Sitzung im Konto prüfen; andere Dienste bleiben bei einer reinen Kontaktablehnung nutzbar.',
            'contactsWait', 'contactsLimit', 'contactsConfiguration'                  => 'Kontaktintervall, Größenlimit oder Wartezeit verhindert den Abruf. Vorherige vollständige Daten bleiben erhalten.',
            'photosDisabled'                                                          => 'Der optionale Fotozugriff ist im Konto ausgeschaltet.',
            'photosAccess', 'photosUnavailable', 'photosIndexing', 'photosAlbum'      => 'Fotos oder das ausgewählte Album sind nicht verfügbar. iCloud-Webfreigabe, Albumwahl und gegebenenfalls Apples laufende Indexierung prüfen.',
            'photosCache', 'photoDownload', 'photoFormat'                             => 'Ein Foto konnte nicht geladen oder im begrenzten Cache aufbereitet werden. Dateiformat, Speicherbudget und Webfreigabe prüfen.',
            'soundRejected', 'lostModeRejected', 'lostModeStopRejected'               => 'Apple hat den Geräteauftrag nicht bestätigt. Den Zustand in Wo ist prüfen; ein unsicherer Auftrag wird nicht automatisch wiederholt.',
            'deviceUnknown', 'lostModeUnsupported', 'lostModeActive', 'lostModeInput' => 'Der Geräteauftrag ist wegen Gerätezuordnung, Unterstützung, aktuellem Zustand oder Eingaben nicht zulässig. Konfiguration und Bestätigung prüfen.',
            'serviceUnavailable'                                                      => 'Wo ist steht für dieses Konto nicht zur Verfügung. Die Apple-Webfreigabe prüfen.',
            'busy'                                                                    => 'Eine andere Verarbeitung derselben Instanz läuft bereits. Es wurde kein paralleler Auftrag gestartet.',
            default                                                                   => 'Die Datenquelle oder Konfiguration ist nicht verwendbar. Den zugehörigen Kontostatus und die vorangehenden Ablaufschritte prüfen; private Detailtexte bleiben geschützt.'
        };
    }

    protected function CloudDebugWrite(string $ident, mixed $value, string $function, int $line): mixed
    {
        $function = $this->CloudDebugFunction($function);
        $safe = is_bool($value) || is_int($value) || is_float($value);
        if (preg_match('/Latitude|Longitude|Distance|Address|Timestamp|Since|LastUpdate|DeviceName|Model|Name|Reason/i', $ident)) {
            $safe = false;
        }
        $field = preg_replace('/^Device_[a-f0-9]{32}_/', '', $ident);
        $context = match ($field) {
            'BatteryLevel' => ['Bedeutung' => 'Akkustand', 'Einheit' => 'Prozent'],
            'Accuracy'     => ['Bedeutung' => 'Von Apple gemeldeter Genauigkeitsradius', 'Einheit' => 'Meter'],
            'StayDuration' => ['Bedeutung' => 'Bestätigte Aufenthaltsdauer', 'Einheit' => 'Sekunden'],
            'Presence'     => ['Bedeutung' => match ($value) {
                -1 => 'Unbekannt', 0 => 'Abwesend', 1 => 'Anwesend', default => 'Nicht zugeordnet'
            }],
            'DataAvailable'                                                                                                                                                                          => ['Bedeutung' => $value ? 'Datenstand ist verfügbar' : 'Kein nutzbarer Datenstand'],
            'LocationAvailable'                                                                                                                                                                      => ['Bedeutung' => $value ? 'Position ist verfügbar; dies ist keine neue GPS-Messung' : 'Keine gültige Position verfügbar'],
            'LocationIsOld'                                                                                                                                                                          => ['Bedeutung' => $value ? 'Standort ist veraltet oder fehlt' : 'Standort ist nicht als veraltet markiert'],
            'Online'                                                                                                                                                                                 => ['Bedeutung' => $value ? 'Apple meldet das Gerät online' : 'Apple meldet das Gerät nicht online'],
            'TransitionPending'                                                                                                                                                                      => ['Bedeutung' => $value ? 'Zonenwechsel wartet auf Bestätigung' : 'Kein offener Zonenwechsel'],
            'LowPowerMode', 'LocationEnabled', 'LocationCapable', 'IsLocating', 'LostModeEnabled', 'LostModeCapable', 'ActivationLocked', 'FamilyShared', 'LocationIsInaccurate', 'LocationFinished' => ['Bedeutung' => match ($value) {
                -1 => 'Nicht verfügbar', 0 => 'Nein', 1 => 'Ja', default => 'Nicht zugeordnet'
            }],
            default => []
        };
        return $this->DebugFlowWriteValue($ident, $value, $function, $line, $safe, $context);
    }

    protected function CloudDebugStatus(int $status, string $function, int $line): void
    {
        $function = $this->CloudDebugFunction($function);
        $this->SetStatus($status);
        $this->DebugFlowEvent($function, $line, 'SENDEN', $status === 102
            ? 'Instanzstatus auf aktiv gesetzt. Die ausgewerteten Daten stehen zur Verfügung.'
            : 'Instanzstatus aktualisiert. Bei fehlender Verfügbarkeit bleiben Daten entsprechend dem Modulvertrag als alt oder unbekannt gekennzeichnet.', ['Status' => $status]);
    }

    protected function CloudDebugTimer(string $ident, int $interval, string $function, int $line): void
    {
        $function = $this->CloudDebugFunction($function);
        $this->SetTimerInterval($ident, $interval);
        $this->DebugFlowEvent($function, $line, 'AKTION', $interval === 0 ? 'Timer angehalten; dieser Timer löst keine weitere Abfrage aus.' : 'Timerintervall eingestellt; dies startet keine zusätzliche sofortige Abfrage.', ['Timer' => $ident, 'IntervallMillisekunden' => $interval]);
    }

    protected function CloudDebugParent(string $request, string $function, int $line): string
    {
        $function = $this->CloudDebugFunction($function);
        $data = json_decode($request, true);
        $commands = ['GetDevices', 'RefreshDevices', 'GetContacts', 'GetContact', 'RefreshContacts', 'GetPhotoAlbums', 'RefreshPhotoAlbums', 'GetPhotoPage', 'GetPhotoImage', 'GetPhotoImagePage', 'ClearPhotoImageTransfer', 'PlaySound', 'ActivateLostMode', 'DeactivateLostMode'];
        $command = in_array($data['Command'] ?? null, $commands, true) ? $data['Command'] : 'Nicht freigegebener Befehl';
        $this->DebugFlowEvent($function, $line, 'SENDEN', 'Eine definierte Anfrage wird an das verbundene Konto übergeben. Zugangsdaten und Anfrageinhalt bleiben geschützt.', ['Schnittstelle' => 'Kontodatenfluss', 'KontoInstanz' => $this->GetParentID(), 'Befehl' => $command]);
        try {
            $reply = $this->SendDataToParent($request);
        } catch (\Throwable $exception) {
            $this->DebugFlowException($function, $line, $exception);
            throw $exception;
        }
        $this->CloudDebugSnapshot(json_decode($reply, true), $function, $line);
        return $reply;
    }

    protected function CloudDebugSnapshot(mixed $data, string $function, int $line): void
    {
        $function = $this->CloudDebugFunction($function);
        if (!is_array($data)) {
            $this->DebugFlowEvent($function, $line, 'FEHLER', 'Die Kontoschnittstelle hat keine auswertbare Datenstruktur geliefert. Vorherige Daten werden nicht als neue Messung ausgegeben.');
            return;
        }
        $context = ['Erfolg' => ($data['success'] ?? false) === true];
        foreach (['devices' => 'Geräteanzahl', 'accounts' => 'Kontenanzahl', 'contacts' => 'Kontaktanzahl', 'albums' => 'Albumanzahl', 'items' => 'Bildanzahl'] as $key => $label) {
            if (is_array($data[$key] ?? null)) {
                $context[$label] = count($data[$key]);
            }
        }
        if (is_bool($data['stale'] ?? null)) {
            $context['Veraltet'] = $data['stale'];
        }
        if (is_bool($data['paused'] ?? null)) {
            $context['Pausiert'] = $data['paused'];
        }
        $this->DebugFlowEvent($function, $line, ($data['success'] ?? false) ? 'EMPFANG' : 'FEHLER', ($data['success'] ?? false)
            ? (($data['stale'] ?? false) ? 'Die Schnittstelle liefert einen veralteten Cache. Diese Daten sind keine neue Apple- oder GPS-Messung.' : 'Die Kontoschnittstelle hat einen Datenstand geliefert. Ein Cacheabruf bestätigt keine neue Apple- oder GPS-Messung.')
            : $this->CloudDebugReason(($data['paused'] ?? false) ? 'requestsPaused' : (is_string($data['state'] ?? null) ? $data['state'] : '')), $context);
    }

    protected function CloudDebugProblem(string $reason, string $function, int $line, array $context = []): void
    {
        $message = match ($reason) {
            'Select an account and device.', 'No iCloud account connected.', 'Connect an account first.' => 'Es fehlt eine gültige Kontoverbindung oder Gerätezuordnung. Ein vorhandenes Konto verbinden und das Gerät in der Instanz auswählen.',
            'Account data unavailable.', 'Account data unavailable', 'Sign in required.'                 => 'Es liegt kein nutzbarer Kontodatenstand vor. Anmeldung, Pause und Schutzstatus der Kontoinstanz prüfen; die Instanz meldet sich nicht selbständig neu an.',
            'Invalid map configuration.'                                                                 => 'Die Kartenkonfiguration ist ungültig. Kontenauswahl und Zeitgrenze für alte Positionen prüfen und übernehmen.',
            'Check the device selection.', 'Select an account and at least one device.'                  => 'Die Geräteauswahl ist ungültig oder leer. Konto verbinden und mindestens ein verfügbares Gerät auswählen.',
            'Evaluation disabled.'                                                                       => 'Die Anwesenheitsauswertung ist ausgeschaltet. Zum Auswerten die Instanz aktivieren und die Konfiguration übernehmen.',
            'Photo instance disabled or album missing.'                                                  => 'Die Foto-Instanz ist ausgeschaltet oder es ist kein Album ausgewählt. Instanz aktivieren und Album auswählen; es wurde kein Fotoabruf gestartet.',
            'Device not in current account list.', 'Device not in account inventory.'                    => 'Das ausgewählte Gerät fehlt im aktuellen Kontocache. Gerätezuordnung und Bestand des verbundenen Kontos prüfen; der Zustand wird unbekannt.',
            'Check the zones, reference zone and GPS settings.'                                          => 'Die Zonen- oder GPS-Konfiguration ist ungültig. Genau eine Referenzzone, positiven Radius sowie Grenzpuffer kleiner als Radius prüfen; gegebenenfalls zoneneigene Vorgaben berücksichtigen.',
            'No valid position available.', 'missing'                                                    => 'Es liegt keine gültige Position vor. Es wird keine Anwesenheit aus fehlenden Koordinaten abgeleitet.',
            'stale'                                                                                      => 'Die Standortmessung ist zu alt oder von Apple als alt markiert. Anwesenheit bleibt unbekannt; die konfigurierte maximale Standortdauer prüfen.',
            'inaccurate'                                                                                 => 'Die Standortmessung ist zu ungenau. Anwesenheit bleibt unbekannt; globale oder zoneneigene Genauigkeitsgrenzen prüfen.',
            'timestamp'                                                                                  => 'Die Standortzeit ist ungültig. Aus dieser Messung werden weder Anwesenheit noch Aufenthaltsdauer abgeleitet.',
            'boundary'                                                                                   => 'Die Position liegt zu unsicher an einer Zonengrenze. Die Anwesenheit bleibt unbekannt, bis die GPS-Regeln eine eindeutige Zuordnung erlauben.',
            'outOfOrder', 'conflicting'                                                                  => 'Eine ältere oder widersprüchliche Messung wird ignoriert. Sie darf bestätigte Zonen und die Aufenthaltsdauer nicht überschreiben.',
            'pending'                                                                                    => 'Ein möglicher Zonenwechsel wartet auf eine neue bestätigende Messung nach Ablauf der Stabilisierung. Aufenthaltsdauer wird nicht vorschnell neu gestartet.',
            'held'                                                                                       => 'Die Position liegt im Grenzbereich. Die zuletzt bestätigte Zone bleibt erhalten; Eintritt und Austritt sind noch nicht sicher.',
            'jump', 'implausible'                                                                        => 'Der Standortwechsel ist für die konfigurierte Geschwindigkeit unplausibel. Der GPS-Sprung wird nicht ungeprüft als Zonenwechsel übernommen.',
            'valid'                                                                                      => 'Die Position wurde nach den aktiven GPS-Regeln ausgewertet. Zonenentscheidung und Aufenthaltsdauer beziehen sich auf bestätigte Messungen.',
            'Device location unavailable.'                                                               => 'Für das Gerät ist keine Position verfügbar. Zuletzt bekannte Werte werden nicht als neue Messung behandelt.',
            'busy'                                                                                       => $this->CloudDebugReason('busy'),
            default                                                                                      => 'Die Verarbeitung kann wegen ungültiger Konfiguration oder fehlender Daten nicht fortgesetzt werden. Geräteauswahl, Zonen, genau eine Referenzzone und GPS-Grenzen prüfen; private Detailtexte bleiben geschützt.'
        };
        $this->DebugFlowEvent($this->CloudDebugFunction($function), $line, in_array($reason, ['pending', 'held', 'valid'], true) ? 'AKTION' : 'FEHLER', $message, $context);
    }

    protected function CloudDebugAppleSending(string $operation): void
    {
        $purpose = match ($operation) {
            'signinInit', 'signinComplete'   => 'Ausdrücklich gestartete Passwortanmeldung an Apple übergeben. Es werden keine Zugangsdaten protokolliert.',
            'verifyCode'                     => 'Den ausdrücklich eingegebenen Bestätigungscode prüfen lassen. Der Code wird weder protokolliert noch für Debug gespeichert.',
            'requestCode'                    => 'Zustellung eines Bestätigungscodes anfordern; Versuchslimits bleiben wirksam.',
            'trustBrowser', 'sessionRenewal' => 'Vertrauen oder Kontositzung mit bestehenden Sitzungsdaten bestätigen lassen; keine neue Passwortanmeldung.',
            'devices'                        => 'Gerätebestand bei Apple lesen. Der Abruf fordert keine neue aktive GPS-Ortung an.',
            'contacts'                       => 'Lesenden Kontaktabruf an Apple übergeben. Namen, Nummern und E-Mail-Adressen bleiben geschützt.',
            'photos'                         => 'Lesende Fotoabfrage an Apple übergeben. Album-, Bild- und Sitzungskennungen bleiben geschützt.',
            'deviceAction'                   => 'Den ausdrücklich angeforderten Geräteauftrag einmal an Apple übergeben; die Gerätewirkung ist noch nicht bestätigt.',
            default                          => 'Eine definierte Apple-Anfrage ausführen; private Parameter werden nicht protokolliert.'
        };
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', $purpose, ['Schnittstelle' => 'Apple iCloud']);
    }

    protected function CloudDebugResponse(int $status, string $type, string $body, string $function, int $line): void
    {
        $this->CloudDebugHTTP($status, strlen($body), $function, $line);
        $this->SendResponse($status, $type, $body);
    }

    protected function CloudDebugPlainResponse(int $status, string $body, string $function, int $line): void
    {
        $this->CloudDebugHTTP($status, strlen($body), $function, $line);
        $this->SendPlainTextResponse($status, $body);
    }

    private function CloudDebugHTTP(int $status, int $bytes, string $function, int $line): void
    {
        $message = match ($status) {
            400     => 'Die Anfrage hat kein unterstütztes Format. Die Ausgabe erneut öffnen; Anfrageinhalt bleibt geschützt.',
            403     => 'WebHook-Zugriff abgelehnt: Die Seitenberechtigung fehlt oder ist ungültig. IPSView-Ausgabe mit der aktuellen Konfiguration neu öffnen; Token werden nicht protokolliert.',
            404     => 'Der WebHook ist für diese Anfrage nicht verfügbar. Aktivierung der IPSView-Ausgabe, Serveradresse und Kontozuordnung prüfen.',
            405     => 'Die HTTP-Methode ist für diesen Endpunkt nicht erlaubt. Nur die vorgesehene Modul-/Widget-Schnittstelle verwenden.',
            503     => 'Der angeforderte Datenstand oder das Dokument ist noch nicht verfügbar. Kontostatus, Ausgabe-Konfiguration und vorangehende Fehler prüfen.',
            default => $status < 400 ? 'HTTP-Antwort an die Visualisierung übergeben. Inhalt und Seitenberechtigung werden nicht protokolliert.' : 'Die Visualisierungsanfrage konnte nicht abgeschlossen werden; HTTP-Status und vorangehende Fehler prüfen.'
        };
        $this->DebugFlowEvent($this->CloudDebugFunction($function), $line, $status < 400 ? 'SENDEN' : 'FEHLER', $message, ['Schnittstelle' => 'Visualisierungs-WebHook', 'HTTP-Status' => $status, 'AusgabeBytes' => $bytes]);
    }

    /** Callback frames carry no arguments; retain the actual module entry name. */
    private function CloudDebugFunction(string $function): string
    {
        if (!str_contains($function, '{closure')) {
            return $function;
        }
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $name = $frame['function'];
            if (!str_contains($name, '{closure') && !str_starts_with($name, 'CloudDebug') && !str_starts_with($name, 'DebugFlow')) {
                return $name;
            }
        }
        return 'Modulablauf';
    }
}
