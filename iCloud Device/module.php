<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CloudDebug.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';
require_once __DIR__ . '/../libs/DeviceWidget.php';

class iCloudDevice extends IPSModuleStrict
{
    use \SymconiCloud\CloudDebug;
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\VariablePresentationHelper;
    use \SymconiCloud\DeviceWidget;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';
    private const MAP_MODULE = '{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}';
    // The availability flag was appended after the existing action at position 28.
    private const ACCURACY_POSITION = 29;
    private const OPTIONAL_FLAGS = [
        'LowPowerMode'     => 'lowPowerMode', 'LocationEnabled' => 'locationEnabled',
        'LocationCapable'  => 'locationCapable', 'IsLocating' => 'isLocating',
        'LostModeEnabled'  => 'lostModeEnabled', 'LostModeCapable' => 'lostModeCapable',
        'ActivationLocked' => 'activationLocked', 'FamilyShared' => 'fmlyShare'
    ];

    /** Registers immutable device identity configuration. */
    public function Create(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterPropertyString('DeviceID', '');
            $this->RegisterPropertyInteger('AddressSource', 0);
            $this->RegisterAttributeString('LastError', '');
            $this->RegisterAttributeString('SourceIdentity', '');
            $this->RegisterAttributeString('SoundResult', '');
            $this->RegisterAttributeString('LostModeResult', '');
            $this->RegisterAttributeString('WidgetLocation', 'null');
            $this->RegisterAttributeInteger('WidgetSoundRevision', 0);
            $this->SetVisualizationType(1);
        }, true);
    }

    /** Applies identity, registers status variables and the sound action, and reads cached data. */
    public function ApplyChanges(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->SetVisualizationType(1);
            $this->registerVariables();
            $identity = $this->GetParentID() . ':' . $this->ReadPropertyString('DeviceID');
            if ($identity !== $this->ReadAttributeString('SourceIdentity')) {
                $this->WriteAttributeString('WidgetLocation', 'null');
                $this->CloudDebugWrite('LocationAvailable', false, __FUNCTION__, __LINE__);
                $this->CloudDebugWrite('AccuracyAvailable', false, __FUNCTION__, __LINE__);
                $this->WriteAttributeString('SoundResult', '');
                $this->WriteAttributeString('LostModeResult', '');
                foreach (['Latitude', 'Longitude', 'Accuracy', 'BatteryLevel'] as $ident) {
                    $this->CloudDebugWrite($ident, 0.0, __FUNCTION__, __LINE__);
                }
                foreach (['LastUpdate', 'LocationTimestamp', 'DeviceStatus'] as $ident) {
                    $this->CloudDebugWrite($ident, 0, __FUNCTION__, __LINE__);
                }
                foreach (['DeviceName', 'Model', 'DeviceClass', 'BatteryStatus', 'Address'] as $ident) {
                    $this->CloudDebugWrite($ident, '', __FUNCTION__, __LINE__);
                }
                $this->applyDetails([]);
                $this->WriteAttributeString('SourceIdentity', $identity);
            }
            $this->Update();
        }, true);
    }

    /** @return string SDK parent descriptor allowing connections to shared accounts. */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** @return string Form without any external request. */
    public function GetConfigurationForm(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            $form = $this->LoadConfigurationForm();
            $form['actions'][0]['caption'] = $this->ReadAttributeString('LastError');
            foreach ($form['actions'] as &$action) {
                if (in_array($action['name'] ?? '', ['SoundResult', 'LostModeResult'], true)) {
                    $action['caption'] = $this->ReadAttributeString($action['name']);
                }
                if (($action['name'] ?? '') === 'LostMode') {
                    $action['items'][0]['caption'] = $this->Translate('Target device') . ': ' . $this->GetValue('DeviceName');
                }
            }
            unset($action);
            foreach (['SoundResult', 'LostModeResult'] as $field) {
                $form['actions'] = $this->SetFormFieldVisibility($form['actions'], [$field], $this->ReadAttributeString($field) !== '');
            }
            return $this->EncodeConfigurationForm($form);
        });
    }

    /** @return bool True when this device was found in the authenticated account cache. */
    public function Update(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            if (!$this->HasParent() || trim($this->ReadPropertyString('DeviceID')) === '') {
                $this->markUnavailable('Select an account and device.', 201);
                return false;
            }
            try {
                $response = $this->CloudDebugParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => 'GetDevices']), __FUNCTION__, __LINE__);
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data)) {
                    return $this->applyResult($data);
                }
            } catch (Throwable) {
                // The account owns transport diagnostics; raw responses are private.
            }
            $this->markUnavailable('Account data unavailable.', 202);
            return false;
        });
    }

    /** @param string $JSONString Account notification containing normalized devices. */
    public function ReceiveData(string $JSONString): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSONString): string
        {
            try {
                $this->applyResult($this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA));
            } catch (Throwable) {
                $this->markUnavailable('Account data unavailable.', 202);
            }
            return '';
        });
    }

    /** @return bool Apple accepted a single sound request; audible playback is not confirmed. */
    public function PlaySound(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            $success = false;
            $message = $this->Translate('Sound request could not be confirmed. Check the device before trying again.');
            if (!$this->HasParent() || trim($this->ReadPropertyString('DeviceID')) === '') {
                $message = $this->Translate('Select an account and device.');
            } else {
                try {
                    $result = json_decode($this->CloudDebugParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, [
                        'Command' => 'PlaySound', 'DeviceID' => $this->ReadPropertyString('DeviceID')
                    ]), __FUNCTION__, __LINE__), true, 512, JSON_THROW_ON_ERROR);
                    $success = ($result['success'] ?? false) === true;
                    if (is_string($result['message'] ?? null) && $result['message'] !== '') {
                        $message = $result['message'];
                    }
                } catch (Throwable) {
                    // The account owns transport errors; never repeat an uncertain sound request.
                }
            }
            $this->WriteAttributeString('SoundResult', $message);
            $this->UpdateFormField('SoundResult', 'caption', $message);
            $this->UpdateFormField('SoundResult', 'visible', true);
            $this->WriteAttributeInteger('WidgetSoundRevision', $this->ReadAttributeInteger('WidgetSoundRevision') + 1);
            $this->PublishDeviceWidget();
            return $success;
        });
    }

    /**
     * @param bool $Confirmed Explicit per-call confirmation; never saved as a setting.
     * @param string $PhoneNumber Optional contact number displayed on the Lock Screen.
     * @param string $Message Optional Lock Screen message, at most 160 characters.
     * @param string $NewPasscode Optional new 4/6 digit code; leave empty for an existing code.
     * @return bool Apple accepted the activation request, not proof of an activated device.
     */
    public function ActivateLostMode(bool $Confirmed, string $PhoneNumber, string $Message, string $NewPasscode): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Confirmed, $PhoneNumber, $Message, $NewPasscode): bool
        {
            return $this->changeLostMode($Confirmed, true, [
                'PhoneNumber' => $PhoneNumber, 'Message' => $Message, 'NewPasscode' => $NewPasscode
            ]);
        });
    }

    /**
     * @param bool $Confirmed Explicit per-call confirmation; never saved as a setting.
     * @return bool Apple accepted the stop request, not proof of an unlocked device.
     */
    public function DeactivateLostMode(bool $Confirmed): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Confirmed): bool
        {
            return $this->changeLostMode($Confirmed, false);
        });
    }

    /**
     * @param string $Ident Explicit cache or sound action.
     * @param mixed $Value Empty string for form actions; 1 triggers the sound button, 0 resets it.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            if ($Ident === 'GetState' && $Value === '') {
                $this->PublishDeviceWidget();
                return;
            }
            if ($Ident === 'Update') {
                $this->Update();
                return;
            }
            if ($Ident !== 'PlaySound' || !in_array($Value, ['', 0, 1], true)) {
                throw new InvalidArgumentException('Unsupported device action.');
            }
            if ($Value === 0) {
                $this->CloudDebugWrite('PlaySound', 0, __FUNCTION__, __LINE__);
                return;
            }
            try {
                $success = $this->PlaySound();
            } finally {
                if ($Value === 1) {
                    $this->CloudDebugWrite('PlaySound', 0, __FUNCTION__, __LINE__);
                }
            }
            if (!$success && $Value === 1) {
                throw new RuntimeException($this->ReadAttributeString('SoundResult'));
            }
        }, true);
    }

    public function Destroy(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        { parent::Destroy(); }, true);
    }

    /** Uses the same account boundary for two separately confirmed, fixed Lost Mode commands. */
    private function changeLostMode(bool $confirmed, bool $enabled, array $input = []): bool
    {
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', $confirmed ? 'Der Auftrag zum Verloren-Modus wurde ausdrücklich bestätigt. Eingaben bleiben geschützt.' : 'Bestätigung fehlt oder wurde abgebrochen. Es wird kein Auftrag zum Verloren-Modus gesendet.', ['Aktivieren' => $enabled, 'Bestätigt' => $confirmed]);
        $success = false;
        $message = $this->Translate($enabled ? 'Confirm Lost Mode activation explicitly.' : 'Confirm Lost Mode deactivation explicitly.');
        try {
            if ($confirmed) {
                $message = $this->Translate('Lost Mode request could not be confirmed. Check Find My before trying again.');
                if (!$this->HasParent() || trim($this->ReadPropertyString('DeviceID')) === '') {
                    $message = $this->Translate('Select an account and device.');
                } else {
                    $result = json_decode($this->CloudDebugParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, [
                        'Command'  => $enabled ? 'ActivateLostMode' : 'DeactivateLostMode',
                        'DeviceID' => $this->ReadPropertyString('DeviceID'), 'Confirmed' => true
                    ] + $input), __FUNCTION__, __LINE__), true, 512, JSON_THROW_ON_ERROR);
                    $success = ($result['success'] ?? false) === true;
                    if (is_string($result['message'] ?? null) && $result['message'] !== '') {
                        $message = $result['message'];
                    }
                }
            }
        } catch (Throwable) {
            // Never persist input/response bodies or retry an uncertain lock command.
        } finally {
            foreach (['LostModePhone', 'LostModeMessage', 'LostModePasscode'] as $field) {
                $this->UpdateFormField($field, 'value', '');
            }
        }
        $this->WriteAttributeString('LostModeResult', $message);
        $this->UpdateFormField('LostModeResult', 'caption', $message);
        $this->UpdateFormField('LostModeResult', 'visible', true);
        return $success;
    }

    /** @param array<string, mixed> $data @return bool Device cache availability. */
    private function applyResult(array $data): bool
    {
        $this->RememberDeviceLocation();
        if (($data['success'] ?? false) !== true || ($data['state'] ?? '') !== 'authenticated' || (int) ($data['updatedAt'] ?? 0) <= 0) {
            $this->markUnavailable((string) ($data['error'] ?? 'Account data unavailable.'), 202);
            return false;
        }
        foreach (($data['devices'] ?? []) as $device) {
            if (!is_array($device) || ($device['id'] ?? '') !== $this->ReadPropertyString('DeviceID')) {
                continue;
            }
            foreach (['DeviceName' => 'name', 'Model' => 'model', 'DeviceClass' => 'deviceClass', 'BatteryStatus' => 'batteryStatus'] as $ident => $key) {
                $this->CloudDebugWrite($ident, (string) ($device[$key] ?? ''), __FUNCTION__, __LINE__);
            }
            $this->CloudDebugWrite('DeviceStatus', (int) ($device['deviceStatus'] ?? 0), __FUNCTION__, __LINE__);
            $this->CloudDebugWrite('Online', (int) ($device['deviceStatus'] ?? 0) === 200, __FUNCTION__, __LINE__);
            $battery = $device['batteryLevel'] ?? null;
            $batteryAvailable = is_numeric($battery) && (float) $battery >= 0 && (float) $battery <= 1;
            $this->CloudDebugWrite('BatteryAvailable', $batteryAvailable, __FUNCTION__, __LINE__);
            if ($batteryAvailable) {
                $this->CloudDebugWrite('BatteryLevel', (float) $battery * 100, __FUNCTION__, __LINE__);
            }
            $location = $device['location'] ?? null;
            $valid = is_array($location) && is_numeric($location['latitude'] ?? null) && is_numeric($location['longitude'] ?? null)
                && abs((float) $location['latitude']) <= 90 && abs((float) $location['longitude']) <= 180;
            $this->CloudDebugWrite('LocationAvailable', $valid, __FUNCTION__, __LINE__);
            $this->CloudDebugWrite('LocationIsOld', !$valid || ($location['isOld'] ?? null) !== false, __FUNCTION__, __LINE__);
            $accuracy = $valid ? ($location['horizontalAccuracy'] ?? null) : null;
            $accuracyAvailable = is_numeric($accuracy) && is_finite((float) $accuracy) && (float) $accuracy >= 0;
            $this->CloudDebugWrite('AccuracyAvailable', $accuracyAvailable, __FUNCTION__, __LINE__);
            if ($valid) {
                $this->CloudDebugWrite('Latitude', (float) $location['latitude'], __FUNCTION__, __LINE__);
                $this->CloudDebugWrite('Longitude', (float) $location['longitude'], __FUNCTION__, __LINE__);
                if ($accuracyAvailable) {
                    $this->CloudDebugWrite('Accuracy', (float) $accuracy, __FUNCTION__, __LINE__);
                }
                $this->CloudDebugWrite('LocationTimestamp', max(0, (int) ($location['timestamp'] ?? 0)), __FUNCTION__, __LINE__);
            }
            $this->CloudDebugWrite('Address', $valid ? $this->readAddress((float) $location['latitude'], (float) $location['longitude']) : '', __FUNCTION__, __LINE__);
            $this->captureDeviceLocation();
            $this->applyDetails($device, $valid);
            $this->CloudDebugWrite('LastUpdate', (int) $data['updatedAt'], __FUNCTION__, __LINE__);
            $this->CloudDebugWrite('DataAvailable', true, __FUNCTION__, __LINE__);
            $this->WriteAttributeString('LastError', '');
            $this->CloudDebugStatus(IS_ACTIVE, __FUNCTION__, __LINE__);
            $this->PublishDeviceWidget();
            return true;
        }
        $this->markUnavailable('Device not in current account list.', 203);
        return false;
    }

    private function markUnavailable(string $error, int $status): void
    {
        $this->CloudDebugProblem($error, __FUNCTION__, __LINE__);
        $this->RememberDeviceLocation();
        foreach (['DataAvailable', 'LocationAvailable', 'AccuracyAvailable', 'BatteryAvailable', 'Online'] as $ident) {
            $this->CloudDebugWrite($ident, false, __FUNCTION__, __LINE__);
        }
        $this->CloudDebugWrite('LocationIsOld', true, __FUNCTION__, __LINE__);
        $this->CloudDebugWrite('Address', '', __FUNCTION__, __LINE__);
        $this->applyDetails([]);
        $this->WriteAttributeString('LastError', $this->Translate($error === '' ? 'Account data unavailable.' : $error));
        $this->CloudDebugStatus($status, __FUNCTION__, __LINE__);
        $this->PublishDeviceWidget();
    }

    private function registerVariables(): void
    {
        $position = 0;
        foreach (['DataAvailable', 'Online', 'LocationAvailable', 'LocationIsOld', 'BatteryAvailable'] as $ident) {
            $this->RegisterVariableBoolean($ident, $this->Translate($ident), $this->BooleanPresentation($this->Translate('Yes'), $this->Translate('No')), $position++);
        }
        foreach (['DeviceName', 'Model', 'DeviceClass', 'BatteryStatus'] as $ident) {
            $this->RegisterVariableString($ident, $this->Translate($ident), $this->TextPresentation(), $position++);
        }
        $this->RegisterVariableInteger('DeviceStatus', $this->Translate('DeviceStatus'), $this->IntegerPresentation(), $position++);
        $this->RegisterVariableFloat('BatteryLevel', $this->Translate('BatteryLevel'), $this->PercentPresentation(), $position++);
        $this->RegisterVariableFloat('Latitude', $this->Translate('Latitude'), $this->DecimalPresentation('°', 6, -90, 90), $position++);
        $this->RegisterVariableFloat('Longitude', $this->Translate('Longitude'), $this->DecimalPresentation('°', 6, -180, 180), $position++);
        $this->RegisterVariableFloat('Accuracy', $this->Translate('Accuracy'), $this->DecimalPresentation('m', 1), $position++);
        foreach (['LocationTimestamp', 'LastUpdate'] as $ident) {
            $this->RegisterVariableInteger($ident, $this->Translate($ident), $this->DateTimePresentation(), $position++);
        }
        $this->RegisterVariableString('Address', $this->Translate('Address'), $this->TextPresentation(true), $position++);
        $options = [
            ['Value' => -1, 'Caption' => $this->Translate('Not available')],
            ['Value' => 0, 'Caption' => $this->Translate('No')],
            ['Value' => 1, 'Caption' => $this->Translate('Yes')]
        ];
        foreach (array_merge(array_keys(self::OPTIONAL_FLAGS), ['LocationIsInaccurate', 'LocationFinished']) as $ident) {
            if ($this->RegisterVariableInteger($ident, $this->Translate($ident), $this->IntegerOptionsPresentation($options), $position++)) {
                $this->CloudDebugWrite($ident, -1, __FUNCTION__, __LINE__);
            }
        }
        if ($this->RegisterVariableString('ModelIdentifier', $this->Translate('ModelIdentifier'), $this->TextPresentation(), $position++)) {
            $this->CloudDebugWrite('ModelIdentifier', $this->Translate('Not available'), __FUNCTION__, __LINE__);
        }
        $this->RegisterVariableInteger('PlaySound', $this->Translate('Find phone'), $this->EnumerationPresentation([
            ['Value' => 1, 'Caption' => $this->Translate('Play sound')]
        ], 1), $position++);
        $this->CloudDebugWrite('PlaySound', 0, __FUNCTION__, __LINE__);
        $this->EnableAction('PlaySound');
        // Append the new read-only flag without moving or replacing existing variables.
        $this->registerAccuracyAvailability();
    }

    /** Idempotent upgrade initialization, also safe before the first ApplyChanges. */
    private function registerAccuracyAvailability(): void
    {
        try {
            $variableID = @$this->GetIDForIdent('AccuracyAvailable');
            if (is_int($variableID) && $variableID > 0 && IPS_VariableExists($variableID)) {
                return;
            }
        } catch (Throwable) {
            // A missing ident is expected on an installation created by the previous version.
        }
        $this->RegisterVariableBoolean('AccuracyAvailable', $this->Translate('AccuracyAvailable'), $this->BooleanPresentation($this->Translate('Yes'), $this->Translate('No')), self::ACCURACY_POSITION);
    }

    /** Missing optional fields are unknown, never a retained or inferred false. */
    private function applyDetails(array $device, bool $locationAvailable = false): void
    {
        $flags = [];
        foreach (self::OPTIONAL_FLAGS as $ident => $field) {
            $flags[$ident] = $device['details'][$field] ?? null;
        }
        $flags['LocationIsInaccurate'] = $locationAvailable ? ($device['location']['isInaccurate'] ?? null) : null;
        $flags['LocationFinished'] = $locationAvailable ? ($device['location']['finished'] ?? null) : null;
        foreach ($flags as $ident => $value) {
            $this->CloudDebugWrite($ident, is_bool($value) ? (int) $value : -1, __FUNCTION__, __LINE__);
        }
        $model = $device['modelIdentifier'] ?? null;
        $this->CloudDebugWrite('ModelIdentifier', is_string($model) && preg_match('/\A[^\x00-\x1F\x7F]{1,128}\z/uD', $model) === 1
            ? $model : $this->Translate('Not available'), __FUNCTION__, __LINE__);
    }

    /** Reads one account-scoped WoIst cache; no device-level HTTP or polling. */
    private function readAddress(float $latitude, float $longitude): string
    {
        try {
            $source = $this->ReadPropertyInteger('AddressSource');
            $candidates = [];
            foreach ($source === 0 ? IPS_GetInstanceListByModuleID(self::MAP_MODULE) : [$source] as $instanceID) {
                if (!IPS_InstanceExists($instanceID)) {
                    continue;
                }
                $instance = IPS_GetInstance($instanceID);
                if (($instance['ModuleInfo']['ModuleID'] ?? '') === self::MAP_MODULE
                    && (int) $instance['ConnectionID'] === $this->GetParentID()
                    && IPS_GetProperty($instanceID, 'EnableAddressResolution')) {
                    $candidates[] = $instanceID;
                }
            }
            if (count($candidates) === 1) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Eine passende Adresse wird nur aus dem WoIst-Cache gelesen; es wird keine Geräte-HTTP-Abfrage gestartet.', ['AdressquelleInstanz' => $candidates[0]]);
                $address = ICWI_GetDeviceAddress($candidates[0], $this->ReadPropertyString('DeviceID'), $latitude, $longitude);
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', $address === '' ? 'Für diese Messung liegt noch keine passende Adresse im Cache vor. Adressauflösung der WoIst-Instanz prüfen; Gerätewerte bleiben nutzbar.' : 'Eine passende Cacheadresse liegt vor; der Adressinhalt wird nicht protokolliert.');
                return $address;
            }
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Es wurde keine eindeutige freigegebene Adressquelle desselben Kontos gefunden. Eine WoIst-Instanz mit aktivierter Adressauflösung zuordnen; Gerätewerte bleiben nutzbar.', ['PassendeQuellen' => count($candidates)]);
        } catch (Throwable $exception) {
            // An unavailable optional address source must not disable device data.
            $this->DebugFlowException(__FUNCTION__, __LINE__, $exception);
        }
        return '';
    }
}
