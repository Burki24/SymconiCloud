<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';

class iCloudWoIstDevice extends IPSModuleStrict
{
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\VariablePresentationHelper;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    /** Registers immutable device identity configuration. */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterAttributeString('LastError', '');
        $this->RegisterAttributeString('SourceIdentity', '');
    }

    /** Applies the identity, creates read-only variables, and reads cached data. */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->registerVariables();
        $identity = $this->GetParentID() . ':' . $this->ReadPropertyString('DeviceID');
        if ($identity !== $this->ReadAttributeString('SourceIdentity')) {
            foreach (['Latitude', 'Longitude', 'Accuracy', 'BatteryLevel'] as $ident) {
                $this->SetValue($ident, 0.0);
            }
            foreach (['LastUpdate', 'LocationTimestamp', 'DeviceStatus'] as $ident) {
                $this->SetValue($ident, 0);
            }
            foreach (['DeviceName', 'Model', 'DeviceClass', 'BatteryStatus'] as $ident) {
                $this->SetValue($ident, '');
            }
            $this->WriteAttributeString('SourceIdentity', $identity);
        }
        $this->Update();
    }

    /** @return string JSON list of supported account modules. */
    public function GetCompatibleParents(): string
    {
        return json_encode([self::ACCOUNT_MODULE], JSON_THROW_ON_ERROR);
    }

    /** @return string Form without any external request. */
    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $form['actions'][0]['caption'] = $this->ReadAttributeString('LastError');
        return $this->EncodeConfigurationForm($form);
    }

    /** @return bool True when this device was found in the authenticated account cache. */
    public function Update(): bool
    {
        if (!$this->HasParent() || trim($this->ReadPropertyString('DeviceID')) === '') {
            $this->markUnavailable('Select an account and device.', 201);
            return false;
        }
        try {
            $response = $this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => 'GetDevices']));
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data)) {
                return $this->applyResult($data);
            }
        } catch (Throwable) {
            // The account owns transport diagnostics; raw responses are private.
        }
        $this->markUnavailable('Account data unavailable.', 202);
        return false;
    }

    /** @param string $JSONString Account notification containing normalized devices. */
    public function ReceiveData(string $JSONString): string
    {
        try {
            $this->applyResult($this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA));
        } catch (Throwable) {
            $this->markUnavailable('Account data unavailable.', 202);
        }
        return '';
    }

    /**
     * @param string $Ident Supported cache action.
     * @param mixed $Value Unused action value.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident !== 'Update') {
            throw new InvalidArgumentException('This device is read only.');
        }
        $this->Update();
    }

    /** @param array<string, mixed> $data @return bool Device cache availability. */
    private function applyResult(array $data): bool
    {
        if (($data['success'] ?? false) !== true || ($data['state'] ?? '') !== 'authenticated' || (int) ($data['updatedAt'] ?? 0) <= 0) {
            $this->markUnavailable((string) ($data['error'] ?? 'Account data unavailable.'), 202);
            return false;
        }
        foreach (($data['devices'] ?? []) as $device) {
            if (!is_array($device) || ($device['id'] ?? '') !== $this->ReadPropertyString('DeviceID')) {
                continue;
            }
            foreach (['DeviceName' => 'name', 'Model' => 'model', 'DeviceClass' => 'deviceClass', 'BatteryStatus' => 'batteryStatus'] as $ident => $key) {
                $this->SetValue($ident, (string) ($device[$key] ?? ''));
            }
            $this->SetValue('DeviceStatus', (int) ($device['deviceStatus'] ?? 0));
            $this->SetValue('Online', (int) ($device['deviceStatus'] ?? 0) === 200);
            $battery = $device['batteryLevel'] ?? null;
            $batteryAvailable = is_numeric($battery) && (float) $battery >= 0 && (float) $battery <= 1;
            $this->SetValue('BatteryAvailable', $batteryAvailable);
            if ($batteryAvailable) {
                $this->SetValue('BatteryLevel', (float) $battery * 100);
            }
            $location = $device['location'] ?? null;
            $valid = is_array($location) && is_numeric($location['latitude'] ?? null) && is_numeric($location['longitude'] ?? null)
                && abs((float) $location['latitude']) <= 90 && abs((float) $location['longitude']) <= 180;
            $this->SetValue('LocationAvailable', $valid);
            $this->SetValue('LocationIsOld', !$valid || (bool) ($location['isOld'] ?? false));
            if ($valid) {
                $this->SetValue('Latitude', (float) $location['latitude']);
                $this->SetValue('Longitude', (float) $location['longitude']);
                $this->SetValue('Accuracy', max(0.0, (float) ($location['horizontalAccuracy'] ?? 0)));
                $this->SetValue('LocationTimestamp', max(0, (int) ($location['timestamp'] ?? 0)));
            }
            $this->SetValue('LastUpdate', (int) $data['updatedAt']);
            $this->SetValue('DataAvailable', true);
            $this->WriteAttributeString('LastError', '');
            $this->SetStatus(IS_ACTIVE);
            return true;
        }
        $this->markUnavailable('Device not in current account list.', 203);
        return false;
    }

    private function markUnavailable(string $error, int $status): void
    {
        foreach (['DataAvailable', 'LocationAvailable', 'BatteryAvailable', 'Online'] as $ident) {
            $this->SetValue($ident, false);
        }
        $this->SetValue('LocationIsOld', true);
        $this->WriteAttributeString('LastError', $this->Translate($error === '' ? 'Account data unavailable.' : $error));
        $this->SetStatus($status);
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
    }
}
