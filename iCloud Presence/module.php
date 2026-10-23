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
    private const MEMBER_FIELDS = ['Presence', 'Zone', 'ZoneID', 'Reason', 'DataAvailable', 'DistanceAvailable', 'StayAvailable', 'TransitionPending', 'Distance', 'ZoneSince', 'StayDuration'];
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
        $this->RegisterPropertyString('DeviceID', ''); // Legacy migration only; not shown in the form.
        $this->RegisterPropertyString('Devices', '[]');
        $this->RegisterPropertyString('Zones', '[]');
        $this->RegisterPropertyInteger('MaxLocationAge', 900);
        $this->RegisterPropertyInteger('MaxAccuracy', 100);
        $this->RegisterPropertyInteger('Hysteresis', 25);
        $this->RegisterPropertyInteger('ConfirmationTime', 30);
        $this->RegisterPropertyInteger('MaxSpeed', 300);
        $this->RegisterAttributeString('EvaluationState', '{}'); // Legacy single-device state.
        $this->RegisterAttributeString('MemberStates', '{}');
        $this->RegisterAttributeString('MemberNames', '{}');
        $this->RegisterAttributeString('SourceIdentity', '');
        $this->RegisterAttributeInteger('LayoutVersion', 0);
        $this->RegisterAttributeString('LastError', '');
        $this->RegisterTimer('Evaluate', 0, 'ICPR_Update($_IPS["TARGET"]);');
    }

    /** Converts only a known single-device persistence, retaining explicit empty selections. */
    public function Migrate(string $JSONData): string
    {
        parent::Migrate($JSONData);
        $data = json_decode($JSONData, true, 512, JSON_THROW_ON_ERROR);
        $configuration = $data['configuration'] ?? [];
        if (!is_array($configuration) || array_key_exists('Devices', $configuration) || !is_string($configuration['DeviceID'] ?? null) || $configuration['DeviceID'] === '') {
            return '';
        }
        $data['configuration']['Devices'] = json_encode([['deviceID' => $configuration['DeviceID'], 'name' => '']], JSON_THROW_ON_ERROR);
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('Evaluate', 0);
        $this->migrateLegacyVariables();
        $this->registerSummary();
        $this->Update();
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** Reads the shared account cache once and evaluates each selected member independently. */
    public function Update(): bool
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.Presence.' . $this->InstanceID, 1000)) {
            return false;
        }
        try {
            try {
                $members = $this->selectedMembers();
                $this->synchronizeVariables($members);
            } catch (Throwable) {
                $this->synchronizeVariables([]);
                $this->unavailable([], 'Check the device selection.', 201);
                return false;
            }
            if (!$this->ReadPropertyBoolean('Enabled')) {
                $this->unavailable($members, 'Evaluation disabled.', IS_INACTIVE);
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
                $this->EvaluateGeofence($zones, $reference, null, [], $this->PresenceNow(), $settings);
            } catch (Throwable) {
                $limits = $this->zoneFormLimits($this->ReadPropertyString('Zones'), $this->ReadPropertyInteger('Hysteresis'));
                $this->unavailable($members, $limits['message'] !== '' ? $limits['message'] : 'Check the zones, reference zone and GPS settings.', 201);
                return false;
            }
            $identity = hash('sha256', json_encode([$this->GetParentID(), $zones, $reference, $settings], JSON_THROW_ON_ERROR));
            if ($identity !== $this->ReadAttributeString('SourceIdentity')) {
                // A known legacy source can retain its observed stay when only the layout changes.
                $legacy = $this->ReadPropertyString('DeviceID');
                $oldIdentity = hash('sha256', json_encode([$this->GetParentID(), $legacy, $zones, $reference, $settings], JSON_THROW_ON_ERROR));
                $states = $oldIdentity === $this->ReadAttributeString('SourceIdentity') && isset($members[$legacy])
                    ? [$legacy => $this->readState('EvaluationState')] : [];
                $this->writeState('MemberStates', $states);
                $this->WriteAttributeString('SourceIdentity', $identity);
            }
            if (!$this->HasParent() || $members === []) {
                $this->unavailable($members, 'Select an account and at least one device.', 201);
                return false;
            }
            $this->SetTimerInterval('Evaluate', 15000);
            try {
                return $this->applyResult($this->ReadPresenceCache(), $members, $zones, $reference, $settings);
            } catch (Throwable) {
                $this->unavailable($members, 'Account data unavailable.', 202);
                return false;
            }
        } finally {
            IPS_SemaphoreLeave('SymconiCloud.Presence.' . $this->InstanceID);
        }
    }

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
        $options = [['caption' => $this->Translate('Select device'), 'value' => '']];
        $known = [];
        try {
            $data = $this->HasParent() ? $this->ReadPresenceCache() : [];
            foreach ($data['devices'] ?? [] as $device) {
                if (is_array($device) && is_string($device['id'] ?? null) && $device['id'] !== '' && !isset($known[$device['id']])) {
                    $options[] = ['caption' => (string) ($device['name'] ?? $this->Translate('Device')), 'value' => $device['id']];
                    $known[$device['id']] = true;
                }
            }
            foreach ($this->selectedMembers() as $id => $name) {
                if (!isset($known[$id])) {
                    $options[] = ['caption' => $name !== '' ? $name : $this->Translate('Selected device (not in cache)'), 'value' => (string) $id];
                }
            }
        } catch (Throwable) {
            // No authentication, external request or configuration write while opening the form.
        }
        $form['elements'][1]['columns'][0]['edit']['options'] = $options;
        $limits = $this->zoneFormLimits($this->ReadPropertyString('Zones'), $this->ReadPropertyInteger('Hysteresis'));
        $form['elements'][3]['columns'][4]['edit']['minimum'] = $limits['minimum'];
        $form['elements'][5]['items'][2]['maximum'] = $limits['maximum'];
        $form['elements'][7]['caption'] = $limits['message'];
        $form['elements'][7]['visible'] = $limits['message'] !== '';
        $form['actions'][0]['caption'] = $this->ReadAttributeString('LastError');
        return $this->EncodeConfigurationForm($form);
    }

    /** Updates only the open form using unsaved list and spinner values. */
    public function ValidateZoneSettings(string $Zones, int $Hysteresis): void
    {
        $limits = $this->zoneFormLimits($Zones, $Hysteresis);
        $this->UpdateFormField('Zones', 'columns.4.edit', json_encode(['type' => 'NumberSpinner', 'minimum' => $limits['minimum'], 'maximum' => 1000000], JSON_THROW_ON_ERROR));
        $this->UpdateFormField('Hysteresis', 'maximum', $limits['maximum']);
        $this->UpdateFormField('ZoneSettingsError', 'caption', $limits['message']);
        $this->UpdateFormField('ZoneSettingsError', 'visible', $limits['message'] !== '');
    }

    /** Returns a stable member variable ident for scripts without exposing IDs in variable names. */
    public function GetDeviceVariableIdent(string $DeviceID, string $Ident): string
    {
        if ($DeviceID === '' || !in_array($Ident, self::MEMBER_FIELDS, true)) {
            throw new InvalidArgumentException('Unknown device variable field.');
        }
        return 'Device_' . substr(hash('sha256', $DeviceID), 0, 32) . '_' . $Ident;
    }

    protected function PresenceNow(): int
    {
        return time();
    }

    /** Stable cache boundary for deterministic runtime and offline tests. */
    protected function ReadPresenceCache(): array
    {
        return json_decode($this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => 'GetDevices'])), true, 512, JSON_THROW_ON_ERROR);
    }

    /** UI limits for the geofence helper's strict radius/buffer contract. */
    private function zoneFormLimits(string $json, int $buffer): array
    {
        $limits = ['minimum' => max(1, min(10000, $buffer) + 1), 'maximum' => 10000, 'message' => ''];
        try {
            $zones = $this->NormalizeGeofenceZones(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            return $limits;
        }
        foreach ($zones as $zone) {
            $limits['maximum'] = min($limits['maximum'], (int) ceil($zone['radius']) - 1);
            if ($zone['radius'] <= $buffer && $limits['message'] === '') {
                $limits['message'] = sprintf($this->Translate('Zone "%s": radius (%s m) must be greater than the boundary buffer (%s m). Increase the radius or reduce the buffer.'), $zone['name'], (string) $zone['radius'], (string) $buffer);
            }
        }
        return $limits;
    }

    private function selectedMembers(): array
    {
        $rows = json_decode($this->ReadPropertyString('Devices'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 64) {
            throw new InvalidArgumentException('Select at most 64 devices.');
        }
        $members = [];
        foreach ($rows as $row) {
            $id = $row['deviceID'] ?? null;
            $name = $row['name'] ?? '';
            if (!is_array($row) || !is_string($id) || $id === '' || strlen($id) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $id) !== 0
                || isset($members[$id]) || !is_string($name) || preg_match('/\A[^\x00-\x1F\x7F]{0,80}\z/uD', trim($name)) !== 1) {
                throw new InvalidArgumentException('Device IDs must be unique and labels valid.');
            }
            $members[$id] = trim($name);
        }
        return $members;
    }

    private function applyResult(array $data, array $members, array $zones, string $reference, array $settings): bool
    {
        if (($data['success'] ?? false) !== true || ($data['state'] ?? '') !== 'authenticated' || !is_array($data['devices'] ?? null)
            || !is_int($data['updatedAt'] ?? null) || $data['updatedAt'] < 1 || $data['updatedAt'] > $this->PresenceNow() + 30) {
            $this->unavailable($members, 'Account data unavailable.', 202);
            return false;
        }
        $devices = [];
        foreach ($data['devices'] as $device) {
            if (is_array($device) && is_string($device['id'] ?? null) && !isset($devices[$device['id']])) {
                $devices[$device['id']] = $device;
            }
        }
        $states = $this->readState('MemberStates');
        $names = $this->readState('MemberNames');
        $presence = [];
        $missing = 0;
        $zoneNames = array_column($zones, 'name', 'id');
        $index = 0;
        foreach ($members as $id => $label) {
            $id = (string) $id;
            $device = $devices[$id] ?? null;
            $name = $label !== '' ? $label : (string) ($device['name'] ?? $names[$id] ?? $this->Translate('Device'));
            $names[$id] = $name;
            $this->registerMember($id, $name, $index++);
            if ($device === null) {
                $states[$id] = $this->InvalidateGeofenceState(is_array($states[$id] ?? null) ? $states[$id] : []);
                $this->setUnknownMember($id, 'Device not in current account list.');
                $presence[] = -1;
                $missing++;
                continue;
            }
            $location = $device['location'] ?? null;
            $fix = is_array($location) ? ['latitude' => $location['latitude'] ?? null, 'longitude' => $location['longitude'] ?? null,
                'accuracy'                           => $location['horizontalAccuracy'] ?? null, 'timestamp' => $location['timestamp'] ?? null,
                'isOld'                              => $location['isOld'] ?? false, 'isInaccurate' => $location['isInaccurate'] ?? false] : null;
            $result = $this->EvaluateGeofence($zones, $reference, $fix, is_array($states[$id] ?? null) ? $states[$id] : [], $this->PresenceNow(), $settings);
            $states[$id] = $result['state'];
            $values = ['Presence'   => $result['presence'], 'ZoneID' => $result['zone'] ?? '',
                'Zone'              => $result['zone'] === null ? $this->Translate('Unknown') : ($zoneNames[$result['zone']] ?? $this->Translate('Outside all zones')),
                'ZoneSince'         => $result['since'], 'StayDuration' => $result['duration'], 'StayAvailable' => $result['since'] > 0,
                'Distance'          => $result['distance'] ?? 0.0, 'DistanceAvailable' => $result['distance'] !== null,
                'TransitionPending' => $result['pending'], 'DataAvailable' => true,
                'Reason'            => $this->Translate(self::REASONS[$result['reason']] ?? 'No valid position available.')];
            foreach ($values as $field => $value) {
                $this->SetValue($this->GetDeviceVariableIdent($id, $field), $value);
            }
            $presence[] = $result['presence'];
        }
        $this->writeState('MemberStates', array_intersect_key($states, $members));
        $this->writeState('MemberNames', array_intersect_key($names, $members));
        $this->publishPresenceSummary($presence, true, $missing > 0 ? 'Some selected devices are missing from the account cache.' : 'Shared zones evaluated.');
        $this->SetStatus($missing === count($members) ? 203 : IS_ACTIVE);
        return true;
    }

    private function unavailable(array $members, string $message, int $status): void
    {
        $this->SetTimerInterval('Evaluate', $status === 202 || $status === 203 ? 15000 : 0);
        $states = $this->readState('MemberStates');
        foreach ($members as $id => $name) {
            $states[$id] = $this->InvalidateGeofenceState(is_array($states[$id] ?? null) ? $states[$id] : []);
            $this->setUnknownMember((string) $id, $message);
        }
        $this->writeState('MemberStates', array_intersect_key($states, $members));
        $this->publishPresenceSummary(array_fill(0, count($members), -1), false, $message);
        $this->WriteAttributeString('LastError', $this->Translate($message));
        $this->SetStatus($status);
    }

    private function setUnknownMember(string $id, string $reason): void
    {
        foreach (self::MEMBER_FIELDS as $field) {
            $value = match ($field) {
                'Presence' => -1, 'Zone' => $this->Translate('Unknown'), 'ZoneID' => '', 'Reason' => $this->Translate($reason),
                'Distance' => 0.0, 'ZoneSince', 'StayDuration' => 0, default => false
            };
            $this->SetValue($this->GetDeviceVariableIdent($id, $field), $value);
        }
    }

    private function publishPresenceSummary(array $presence, bool $available, string $reason): void
    {
        $this->SetValue('Presence', $this->AggregateGeofencePresence($presence));
        $this->SetValue('SelectedCount', count($presence));
        foreach (['PresentCount' => 1, 'AbsentCount' => 0, 'UnknownCount' => -1] as $ident => $state) {
            $this->SetValue($ident, count(array_filter($presence, static fn (int $value): bool => $value === $state)));
        }
        $this->SetValue('DataAvailable', $available);
        $this->SetValue('Reason', $this->Translate($reason));
        $this->WriteAttributeString('LastError', '');
    }

    private function readState(string $attribute): array
    {
        try {
            $state = json_decode($this->ReadAttributeString($attribute), true, 512, JSON_THROW_ON_ERROR);
            return is_array($state) ? $state : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function writeState(string $attribute, array $state): void
    {
        $this->WriteAttributeString($attribute, json_encode((object) $state, JSON_THROW_ON_ERROR));
    }

    /** Preserve old variable IDs by assigning them to the original device before adding the group summary. */
    private function migrateLegacyVariables(): void
    {
        if ($this->ReadAttributeInteger('LayoutVersion') >= 2) {
            return;
        }
        $legacy = $this->ReadPropertyString('DeviceID');
        if ($legacy !== '') {
            foreach (self::MEMBER_FIELDS as $field) {
                $old = @$this->GetIDForIdent($field);
                $new = @$this->GetIDForIdent($this->GetDeviceVariableIdent($legacy, $field));
                if ($old !== false && $new === false) {
                    IPS_SetIdent($old, $this->GetDeviceVariableIdent($legacy, $field));
                }
            }
        }
        $this->WriteAttributeInteger('LayoutVersion', 2);
    }

    private function synchronizeVariables(array $members): void
    {
        $active = [];
        $names = $this->readState('MemberNames');
        $index = 0;
        foreach ($members as $id => $label) {
            $id = (string) $id;
            $this->registerMember($id, $label !== '' ? $label : (string) ($names[$id] ?? $this->Translate('Device') . ' ' . ($index + 1)), $index++);
            foreach (self::MEMBER_FIELDS as $field) {
                $active[$this->GetDeviceVariableIdent($id, $field)] = true;
            }
        }
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $child) {
            $object = IPS_GetObject($child);
            $ident = $object['ObjectIdent'];
            if ($object['ObjectType'] === 2 && preg_match('/\ADevice_[a-f0-9]{32}_(.+)\z/D', $ident, $match) === 1 && in_array($match[1], self::MEMBER_FIELDS, true) && !isset($active[$ident])) {
                $value = match ($match[1]) {
                    'Presence' => -1, 'Zone' => $this->Translate('Unknown'), 'ZoneID' => '', 'Reason' => $this->Translate('Device no longer selected.'),
                    'Distance' => 0.0, 'ZoneSince', 'StayDuration' => 0, default => false
                };
                $this->SetValue($ident, $value);
                IPS_SetHidden($child, true);
            }
        }
        $this->writeState('MemberStates', array_intersect_key($this->readState('MemberStates'), $members));
    }

    private function presencePresentation(): array
    {
        return $this->IntegerOptionsPresentation([
            ['Value' => -1, 'Caption' => $this->Translate('Unknown')], ['Value' => 0, 'Caption' => $this->Translate('Absent')], ['Value' => 1, 'Caption' => $this->Translate('Present')]
        ]);
    }

    private function registerSummary(): void
    {
        if ($this->RegisterVariableInteger('Presence', $this->Translate('Overall presence'), $this->presencePresentation(), 0)) {
            $this->SetValue('Presence', -1);
        }
        $position = 1;
        foreach (['SelectedCount', 'PresentCount', 'AbsentCount', 'UnknownCount'] as $ident) {
            $this->RegisterVariableInteger($ident, $this->Translate($ident), $this->IntegerPresentation(), $position++);
        }
        $this->RegisterVariableBoolean('DataAvailable', $this->Translate('DataAvailable'), $this->BooleanPresentation($this->Translate('Yes'), $this->Translate('No')), $position++);
        $this->RegisterVariableString('Reason', $this->Translate('Reason'), $this->TextPresentation(), $position);
    }

    private function registerMember(string $id, string $name, int $index): void
    {
        $position = 100 + $index * 20;
        foreach (self::MEMBER_FIELDS as $field) {
            $ident = $this->GetDeviceVariableIdent($id, $field);
            $caption = $name . ': ' . $this->Translate($field);
            $created = match ($field) {
                'Presence'                 => $this->RegisterVariableInteger($ident, $caption, $this->presencePresentation(), $position++),
                'Zone', 'ZoneID', 'Reason' => $this->RegisterVariableString($ident, $caption, $this->TextPresentation(), $position++),
                'Distance'                 => $this->RegisterVariableFloat($ident, $caption, $this->DecimalPresentation('m', 1), $position++),
                'ZoneSince'                => $this->RegisterVariableInteger($ident, $caption, $this->DateTimePresentation(), $position++),
                'StayDuration'             => $this->RegisterVariableInteger($ident, $caption, $this->DurationPresentation(), $position++),
                default                    => $this->RegisterVariableBoolean($ident, $caption, $this->BooleanPresentation($this->Translate('Yes'), $this->Translate('No')), $position++)
            };
            if ($created && $field === 'Presence') {
                $this->SetValue($ident, -1);
            }
            IPS_SetHidden($this->GetIDForIdent($ident), false);
        }
    }
}
