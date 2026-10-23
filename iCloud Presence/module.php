<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';
require_once __DIR__ . '/../libs/helper/GeofenceHelper.php';

class iCloudPresence extends IPSModuleStrict
{
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\VariablePresentationHelper;
    use \Burki24\SymconModuleHelper\GeofenceHelper;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';
    private const REASONS = [
        'valid'      => 'Reliable position.', 'held' => 'Boundary area: keeping the confirmed zone.',
        'pending'    => 'Zone change awaiting a new confirming position.', 'boundary' => 'Zone cannot be determined reliably.',
        'missing'    => 'No valid position available.', 'timestamp' => 'Invalid location timestamp.',
        'stale'      => 'Location is too old.', 'inaccurate' => 'Location is not accurate enough.',
        'outOfOrder' => 'Older location ignored.', 'conflicting' => 'Conflicting location ignored.',
        'jump'       => 'Implausible GPS movement ignored.'
    ];

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Enabled', true);
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyString('Zones', '[]');
        $this->RegisterPropertyInteger('MaxLocationAge', 900);
        $this->RegisterPropertyInteger('MaxAccuracy', 100);
        $this->RegisterPropertyInteger('Hysteresis', 25);
        $this->RegisterPropertyInteger('ConfirmationTime', 30);
        $this->RegisterPropertyInteger('MaxSpeed', 300);
        $this->RegisterAttributeString('EvaluationState', '{}');
        $this->RegisterAttributeString('SourceIdentity', '');
        $this->RegisterAttributeString('LastError', '');
        $this->RegisterTimer('Evaluate', 0, 'ICPR_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->registerVariables();
        $this->SetTimerInterval('Evaluate', 0);
        $this->Update();
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** Evaluates cached data only. Repeated reads cannot confirm a transition. */
    public function Update(): bool
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.Presence.' . $this->InstanceID, 1000)) {
            return false;
        }
        try {
            if (!$this->ReadPropertyBoolean('Enabled')) {
                $this->SetTimerInterval('Evaluate', 0);
                $this->unavailable('Evaluation disabled.', IS_INACTIVE);
                return false;
            }
            try {
                $configured = json_decode($this->ReadPropertyString('Zones'), true, 512, JSON_THROW_ON_ERROR);
                $zones = $this->NormalizeGeofenceZones($configured);
                $references = array_values(array_filter($configured, static fn (array $zone): bool => ($zone['reference'] ?? false) === true));
                if (count($references) !== 1) {
                    throw new InvalidArgumentException('Select exactly one reference zone.');
                }
                $reference = $references[0]['id'];
                $settings = ['maxAge' => $this->ReadPropertyInteger('MaxLocationAge'), 'maxAccuracy' => $this->ReadPropertyInteger('MaxAccuracy'),
                    'hysteresis'      => $this->ReadPropertyInteger('Hysteresis'), 'confirmSeconds' => $this->ReadPropertyInteger('ConfirmationTime'),
                    'maxSpeed'        => $this->ReadPropertyInteger('MaxSpeed')];
                // Validate all settings even when no usable location is available.
                $this->EvaluateGeofence($zones, $reference, null, [], $this->PresenceNow(), $settings);
            } catch (Throwable) {
                $this->SetTimerInterval('Evaluate', 0);
                $this->unavailable('Check the device, zones, reference zone and GPS settings.', 201);
                return false;
            }
            $identity = hash('sha256', json_encode([$this->GetParentID(), $this->ReadPropertyString('DeviceID'), $zones, $reference, $settings], JSON_THROW_ON_ERROR));
            if ($identity !== $this->ReadAttributeString('SourceIdentity')) {
                $this->WriteAttributeString('EvaluationState', '{}');
                $this->WriteAttributeString('SourceIdentity', $identity);
            }
            if (!$this->HasParent() || trim($this->ReadPropertyString('DeviceID')) === '') {
                $this->SetTimerInterval('Evaluate', 0);
                $this->unavailable('Select an account and device.', 201);
                return false;
            }
            $this->SetTimerInterval('Evaluate', 15000);
            try {
                $data = json_decode($this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => 'GetDevices'])), true, 512, JSON_THROW_ON_ERROR);
                return $this->applyResult($data, $zones, $reference, $settings);
            } catch (Throwable) {
                $this->unavailable('Account data unavailable.', 202);
                return false;
            }
        } finally {
            IPS_SemaphoreLeave('SymconiCloud.Presence.' . $this->InstanceID);
        }
    }

    /** Parent pushes cause a fresh cache evaluation, never an external request. */
    public function ReceiveData(string $JSONString): string
    {
        try {
            $this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA);
        } catch (Throwable) {
            return '';
        }
        $this->Update();
        return '';
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident !== 'Update') {
            throw new InvalidArgumentException('This evaluation is read only.');
        }
        $this->Update();
    }

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $devices = [['caption' => $this->Translate('Select device'), 'value' => '']];
        $selected = $this->ReadPropertyString('DeviceID');
        $found = $selected === '';
        try {
            $data = $this->HasParent() ? json_decode($this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => 'GetDevices'])), true, 512, JSON_THROW_ON_ERROR) : [];
            foreach ($data['devices'] ?? [] as $device) {
                if (!is_array($device) || !is_string($device['id'] ?? null) || $device['id'] === '') {
                    continue;
                }
                $devices[] = ['caption' => (string) ($device['name'] ?? $this->Translate('Device')), 'value' => $device['id']];
                $found = $found || $device['id'] === $selected;
            }
        } catch (Throwable) {
            // Opening configuration never starts Apple authentication or a refresh.
        }
        if (!$found) {
            $devices[] = ['caption' => $this->Translate('Selected device (not in cache)'), 'value' => $selected];
        }
        $form['elements'][1]['options'] = $devices;
        $form['actions'][0]['caption'] = $this->ReadAttributeString('LastError');
        return $this->EncodeConfigurationForm($form);
    }

    protected function PresenceNow(): int
    {
        return time();
    }

    private function applyResult(array $data, array $zones, string $reference, array $settings): bool
    {
        if (($data['success'] ?? false) !== true || ($data['state'] ?? '') !== 'authenticated'
            || !is_int($data['updatedAt'] ?? null) || $data['updatedAt'] < 1 || $data['updatedAt'] > $this->PresenceNow() + 30) {
            $this->unavailable('Account data unavailable.', 202);
            return false;
        }
        foreach ($data['devices'] ?? [] as $device) {
            if (!is_array($device) || ($device['id'] ?? null) !== $this->ReadPropertyString('DeviceID')) {
                continue;
            }
            try {
                $state = json_decode($this->ReadAttributeString('EvaluationState'), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $state = [];
            }
            $location = $device['location'] ?? null;
            $fix = is_array($location) ? ['latitude' => $location['latitude'] ?? null, 'longitude' => $location['longitude'] ?? null,
                'accuracy'                           => $location['horizontalAccuracy'] ?? null, 'timestamp' => $location['timestamp'] ?? null,
                'isOld'                              => $location['isOld'] ?? false, 'isInaccurate' => $location['isInaccurate'] ?? false] : null;
            $result = $this->EvaluateGeofence($zones, $reference, $fix, is_array($state) ? $state : [], $this->PresenceNow(), $settings);
            $this->WriteAttributeString('EvaluationState', json_encode($result['state'], JSON_THROW_ON_ERROR));
            $zoneNames = array_column($zones, 'name', 'id');
            $this->SetValue('Presence', $result['presence']);
            $this->SetValue('ZoneID', $result['zone'] ?? '');
            $this->SetValue('Zone', $result['zone'] === null ? $this->Translate('Unknown') : ($zoneNames[$result['zone']] ?? $this->Translate('Outside all zones')));
            $this->SetValue('ZoneSince', $result['since']);
            $this->SetValue('StayDuration', $result['duration']);
            $this->SetValue('StayAvailable', $result['since'] > 0);
            $this->SetValue('Distance', $result['distance'] ?? 0.0);
            $this->SetValue('DistanceAvailable', $result['distance'] !== null);
            $this->SetValue('TransitionPending', $result['pending']);
            $this->SetValue('DataAvailable', true);
            $this->SetValue('Reason', $this->Translate(self::REASONS[$result['reason']] ?? 'No valid position available.'));
            $this->WriteAttributeString('LastError', '');
            $this->SetStatus(IS_ACTIVE);
            return true;
        }
        $this->unavailable('Device not in current account list.', 203);
        return false;
    }

    private function unavailable(string $message, int $status): void
    {
        try {
            $state = json_decode($this->ReadAttributeString('EvaluationState'), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $state = [];
        }
        $this->WriteAttributeString('EvaluationState', json_encode($this->InvalidateGeofenceState(is_array($state) ? $state : []), JSON_THROW_ON_ERROR));
        $this->SetValue('Presence', -1);
        $this->SetValue('Zone', $this->Translate('Unknown'));
        $this->SetValue('ZoneID', '');
        foreach (['ZoneSince', 'StayDuration'] as $ident) {
            $this->SetValue($ident, 0);
        }
        $this->SetValue('Distance', 0.0);
        foreach (['DataAvailable', 'DistanceAvailable', 'StayAvailable', 'TransitionPending'] as $ident) {
            $this->SetValue($ident, false);
        }
        $this->SetValue('Reason', $this->Translate($message));
        $this->WriteAttributeString('LastError', $this->Translate($message));
        $this->SetStatus($status);
    }

    private function registerVariables(): void
    {
        $position = 0;
        if ($this->RegisterVariableInteger('Presence', $this->Translate('Presence'), $this->IntegerOptionsPresentation([
            ['Value' => -1, 'Caption' => $this->Translate('Unknown')], ['Value' => 0, 'Caption' => $this->Translate('Absent')],
            ['Value' => 1, 'Caption' => $this->Translate('Present')]
        ]), $position++)) {
            $this->SetValue('Presence', -1);
        }
        foreach (['Zone', 'ZoneID', 'Reason'] as $ident) {
            $this->RegisterVariableString($ident, $this->Translate($ident), $this->TextPresentation(), $position++);
        }
        foreach (['DataAvailable', 'DistanceAvailable', 'StayAvailable', 'TransitionPending'] as $ident) {
            $this->RegisterVariableBoolean($ident, $this->Translate($ident), $this->BooleanPresentation($this->Translate('Yes'), $this->Translate('No')), $position++);
        }
        $this->RegisterVariableFloat('Distance', $this->Translate('Distance'), $this->DecimalPresentation('m', 1), $position++);
        $this->RegisterVariableInteger('ZoneSince', $this->Translate('ZoneSince'), $this->DateTimePresentation(), $position++);
        $this->RegisterVariableInteger('StayDuration', $this->Translate('StayDuration'), $this->DurationPresentation(), $position++);
    }
}
