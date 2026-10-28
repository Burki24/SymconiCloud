<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CloudDebug.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';
require_once __DIR__ . '/../libs/helper/GeofenceHelper.php';
require_once __DIR__ . '/../libs/PresenceWidget.php';

class iCloudPresence extends IPSModuleStrict
{
    use \SymconiCloud\CloudDebug;
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\VariablePresentationHelper;
    use \Burki24\SymconModuleHelper\GeofenceHelper;
    use \SymconiCloud\PresenceWidget;
    use \Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
    use \Burki24\SymconModuleHelper\VisualizationAssetHelper;
    use \Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;
    use \Burki24\SymconModuleHelper\IPSViewStyleConfigurationHelper;
    use \Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;
    use \Burki24\SymconModuleHelper\HttpResponseHelper;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';
    private const MEMBER_FIELDS = ['Presence', 'Zone', 'ZoneID', 'Reason', 'DataAvailable', 'DistanceAvailable', 'StayAvailable', 'TransitionPending', 'Distance', 'StateSince', 'ZoneSince', 'StayDuration'];
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
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterStatusWidget();
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
            $this->RegisterAttributeString('InactiveVariableVisibility', '{}');
            $this->RegisterAttributeString('SourceIdentity', '');
            $this->RegisterAttributeInteger('LayoutVersion', 0);
            $this->RegisterAttributeString('LastError', '');
            $this->RegisterTimer('Evaluate', 0, 'ICPR_Update($_IPS["TARGET"]);');
        }, true);
    }

    /** Converts only a known single-device persistence, retaining explicit empty selections. */
    public function Migrate(string $JSONData): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSONData): string
        {
            parent::Migrate($JSONData);
            $data = json_decode($JSONData, true, 512, JSON_THROW_ON_ERROR);
            $configuration = $data['configuration'] ?? [];
            if (!is_array($configuration) || array_key_exists('Devices', $configuration) || !is_string($configuration['DeviceID'] ?? null) || $configuration['DeviceID'] === '') {
                return '';
            }
            $data['configuration']['Devices'] = json_encode([['deviceID' => $configuration['DeviceID'], 'name' => '']], JSON_THROW_ON_ERROR);
            return json_encode($data, JSON_THROW_ON_ERROR);
        });
    }

    public function ApplyChanges(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->CloudDebugTimer('Evaluate', 0, __FUNCTION__, __LINE__);
            $this->migrateLegacyVariables();
            $this->registerSummary();
            $this->ConfigureStatusWidget('IPSViewPresence', $this->Translate('Presence for IPSView'));
            $this->Update();
            if ($this->IsIPSViewHTMLPageEnabled()) {
                $this->RegenerateIPSViewHTMLPages();
            }
        }, true);
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** Reads the shared account cache once and evaluates each selected member independently. */
    public function Update(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            if (!IPS_SemaphoreEnter('SymconiCloud.Presence.' . $this->InstanceID, 1000)) {
                $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
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
                    $this->ResolveGeofenceZoneSettings($zones, $settings);
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
                $this->CloudDebugTimer('Evaluate', 15000, __FUNCTION__, __LINE__);
                try {
                    return $this->applyResult($this->ReadPresenceCache(), $members, $zones, $reference, $settings);
                } catch (Throwable) {
                    $this->unavailable($members, 'Account data unavailable.', 202);
                    return false;
                }
            } finally {
                try {
                    $this->publishWidget();
                } finally {
                    IPS_SemaphoreLeave('SymconiCloud.Presence.' . $this->InstanceID);
                }
            }
        });
    }

    /** Native widget variant, sharing the already evaluated values. */
    public function GetVisualizationTile(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->RenderStatusWidget(false, $this->ReadPropertyString('TileWidgetVariant') === 'WidgetCompact');
        });
    }

    /** Existing full WebContent widget; cached updates do not reload its document. */
    public function GetIPSViewHTML(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->RenderStatusWidget(true);
        });
    }

    /** Additional compact output; the existing full widget remains independent. */
    public function GetIPSViewCompactHTML(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->RenderStatusWidget(true, true);
        });
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($TimeStamp, $SenderID, $Message, $Data): void
        {
            parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
            if ($this->IsIPSViewStyleMediaUpdate($SenderID, $Message)) {
                $this->publishWidget();
            }
        }, true);
    }

    public function ReceiveData(string $JSONString): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSONString): string
        {
            try {
                $this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA);
            } catch (Throwable) {
                return '';
            }
            $this->Update();
            return '';
        });
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            if ($this->HandleIPSViewHTMLPageAction($Ident, $Value)) {
                return;
            }
            if ($Ident !== 'Update') {
                throw new InvalidArgumentException('This evaluation is read only.');
            }
            $this->Update();
        }, true);
    }

    public function GetConfigurationForm(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
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
            // Keep uploaded bytes out of the table while retaining a native local file
            // picker in the row dialog, including the current cached device options.
            $form['elements'][1]['form'] = array_map(static fn (array $column): array => array_merge($column['edit'], [
                'name' => $column['name'], 'caption' => $column['caption']
            ]), $form['elements'][1]['columns']);
            $form['elements'][1]['form'][] = ['type' => 'Label', 'caption' => $this->StatusWidgetText('imageHint')];
            $form['elements'][1]['form'][] = ['type' => 'Label', 'name' => 'PictureUploadError', 'caption' => '', 'visible' => false];
            $limits = $this->zoneFormLimits($this->ReadPropertyString('Zones'), $this->ReadPropertyInteger('Hysteresis'));
            $form['elements'][3]['columns'][4]['edit']['minimum'] = $limits['minimum'];
            $form['elements'][3]['form'] = 'return json_decode(ICPR_GetZoneEditForm($id, $Hysteresis, $MaxAccuracy, $ConfirmationTime, $Zones[\'customGPS\'] ?? false, $Zones[\'radius\'] ?? 100, $Zones[\'hysteresis\'] ?? $Hysteresis), true);';
            $form['elements'][5]['items'][2]['maximum'] = $limits['maximum'];
            $form['elements'][7]['caption'] = $limits['message'];
            $form['elements'][7]['visible'] = $limits['message'] !== '';
            $form['actions'][0]['caption'] = $this->ReadAttributeString('LastError');
            return $this->EncodeConfigurationForm($this->StatusWidgetForm($form));
        });
    }

    /** Reports rejected picture drafts without changing configuration or GPS state. */
    public function ValidatePictureUpload(string $PictureSource, string $PictureUpload): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($PictureSource, $PictureUpload): void
        {
            $issue = $PictureSource === 'upload' ? $this->StatusWidgetUploadIssue($PictureUpload) : '';
            if ($PictureSource === 'upload' && $PictureUpload !== '' && $issue === '') {
                $image = $this->StatusWidgetUpload($PictureUpload);
                $prepared = substr($image, strpos($image, ',') + 1);
                if ($prepared !== $PictureUpload) {
                    $this->UpdateFormField('pictureUpload', 'value', $prepared);
                }
            }
            $this->UpdateFormField('PictureUploadError', 'caption', $issue === '' ? '' : $this->Translate($issue));
            $this->UpdateFormField('PictureUploadError', 'visible', $issue !== '');
        }, true);
    }

    /** Updates only the open form using unsaved list and spinner values. */
    public function ValidateZoneSettings(string $Zones, int $Hysteresis): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Zones, $Hysteresis): void
        {
            $limits = $this->zoneFormLimits($Zones, $Hysteresis);
            $this->UpdateFormField('Zones', 'columns.4.edit', json_encode(['type' => 'NumberSpinner', 'minimum' => $limits['minimum'], 'maximum' => 1000000], JSON_THROW_ON_ERROR));
            $this->UpdateFormField('Hysteresis', 'maximum', $limits['maximum']);
            $this->UpdateFormField('ZoneSettingsError', 'caption', $limits['message']);
            $this->UpdateFormField('ZoneSettingsError', 'visible', $limits['message'] !== '');
        }, true);
    }

    /** Builds a fresh native row dialog using the unsaved global defaults. */
    public function GetZoneEditForm(int $GlobalBuffer, int $GlobalAccuracy, int $GlobalConfirmation, bool $CustomGPS, int $Radius, int $ZoneBuffer): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($GlobalBuffer, $GlobalAccuracy, $GlobalConfirmation, $CustomGPS, $Radius, $ZoneBuffer): string
        {
            $form = $this->LoadConfigurationForm()['elements'][3]['form'];
            $effective = $CustomGPS ? $ZoneBuffer : $GlobalBuffer;
            $form[4]['minimum'] = max(1, $effective + 1);
            $form[7]['visible'] = $CustomGPS;
            $form[7]['items'][0]['value'] = $GlobalBuffer;
            $form[7]['items'][0]['maximum'] = max(0, $Radius - 1);
            $form[7]['items'][1]['value'] = $GlobalAccuracy;
            $form[7]['items'][2]['value'] = $GlobalConfirmation;
            foreach ([4, 6] as $index) {
                // The row also has an "id" field; keep the instance ID unambiguous.
                $form[$index]['onChange'] = 'ICPR_ValidateZoneDraft(' . $this->InstanceID . ', $radius, $customGPS, $hysteresis, ' . $GlobalBuffer . ');';
            }
            $form[7]['items'][0]['onChange'] = $form[4]['onChange'];
            $form[8]['caption'] = $Radius <= $effective ? $this->Translate('Increase the zone radius or reduce its effective boundary buffer.') : '';
            $form[8]['visible'] = $Radius <= $effective;
            // This public row-form function bypasses GetConfigurationForm's native
            // translation step, so translate the module's captions explicitly.
            array_walk_recursive($form, function (mixed &$value, string|int $key): void
            {
                if ($key === 'caption' && is_string($value)) {
                    $value = $this->Translate($value);
                }
            });
            return json_encode($form, JSON_THROW_ON_ERROR);
        });
    }

    /** Validates only the open row, including a switch back to inherited values. */
    public function ValidateZoneDraft(int $Radius, bool $CustomGPS, int $Buffer, int $GlobalBuffer): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Radius, $CustomGPS, $Buffer, $GlobalBuffer): void
        {
            $effective = $CustomGPS ? $Buffer : $GlobalBuffer;
            $this->UpdateFormField('radius', 'minimum', max(1, $effective + 1));
            $this->UpdateFormField('hysteresis', 'maximum', max(0, $Radius - 1));
            $this->UpdateFormField('ZoneGPS', 'visible', $CustomGPS);
            $this->UpdateFormField('ZoneDraftError', 'caption', $Radius <= $effective ? $this->Translate('Increase the zone radius or reduce its effective boundary buffer.') : '');
            $this->UpdateFormField('ZoneDraftError', 'visible', $Radius <= $effective);
        }, true);
    }

    /** Returns a stable member variable ident for scripts without exposing IDs in variable names. */
    public function GetDeviceVariableIdent(string $DeviceID, string $Ident): string
    {
        if ($DeviceID === '' || !in_array($Ident, self::MEMBER_FIELDS, true)) {
            throw new InvalidArgumentException('Unknown device variable field.');
        }
        return 'Device_' . substr(hash('sha256', $DeviceID), 0, 32) . '_' . $Ident;
    }

    public function Destroy(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        { parent::Destroy(); }, true);
    }

    /** Render each variant through the central helper's retained-variable writer. */
    protected function RegenerateIPSViewHTMLPages(): bool
    {
        if (!$this->IsIPSViewHTMLPageEnabled()) {
            return false;
        }
        try {
            $normal = $this->UpdateIPSViewHTMLVariable('IPSViewPresence', $this->GetIPSViewHTML());
            $compact = $this->UpdateIPSViewHTMLVariable('IPSViewPresenceCompact', $this->GetIPSViewCompactHTML());
            return $normal && $compact;
        } catch (Throwable $exception) {
            $this->DebugFlowException(__FUNCTION__, __LINE__, $exception);
            return false;
        }
    }

    protected function VisualizationAssetDirectory(): string
    {
        return __DIR__ . '/visualization';
    }

    /** Native presentation formatting boundary (duration/value presentations are absent from upstream stubs). */
    protected function PresenceFormattedValue(string $ident): string
    {
        return GetValueFormatted($this->GetIDForIdent($ident));
    }

    protected function PresenceNow(): int
    {
        return time();
    }

    /** Stable cache boundary for deterministic runtime and offline tests. */
    protected function ReadPresenceCache(): array
    {
        return json_decode($this->CloudDebugParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => 'GetDevices']), __FUNCTION__, __LINE__), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Keep the public distance value in metres while making long distances readable in widgets. */
    private function formatWidgetDistance(string $ident, float $metres): string
    {
        if ($metres < 1000) {
            return $this->PresenceFormattedValue($ident);
        }
        $decimal = str_starts_with(strtolower(IPS_GetSystemLanguage()), 'de') ? ',' : '.';
        return number_format($metres / 1000, 1, $decimal, '') . ' km';
    }

    /** Domain adapter only: no new account reads, GPS evaluations or coordinates in the widget. */
    private function publishWidget(): void
    {
        try {
            $members = $this->selectedMembers();
        } catch (Throwable) {
            $members = [];
        }
        $names = $this->readState('MemberNames');
        $pictures = [];
        $configured = json_decode($this->ReadPropertyString('Devices'), true);
        foreach (is_array($configured) ? $configured : [] as $row) {
            if (is_array($row) && is_string($row['deviceID'] ?? null)) {
                $pictures[$row['deviceID']] = ($row['pictureSource'] ?? 'media') === 'upload'
                    ? $this->StatusWidgetUpload(is_string($row['pictureUpload'] ?? null) ? $row['pictureUpload'] : '')
                    : $this->StatusWidgetImage(is_int($row['picture'] ?? null) ? $row['picture'] : 0);
            }
        }
        $labels = [-1 => $this->Translate('Unknown'), 0 => $this->Translate('Absent'), 1 => $this->Translate('Present')];
        $tones = [-1 => 'unknown', 0 => 'neutral', 1 => 'positive'];
        $rows = [];
        foreach ($members as $deviceID => $name) {
            $id = (string) $deviceID;
            $read = fn (string $field): mixed => $this->GetValue($this->GetDeviceVariableIdent($id, $field));
            $presence = $read('Presence');
            $pending = $read('TransitionPending');
            $details = [];
            if ($read('DistanceAvailable')) {
                $distanceIdent = $this->GetDeviceVariableIdent($id, 'Distance');
                $details[] = $this->Translate('Distance to reference zone') . ': ' . $this->formatWidgetDistance($distanceIdent, (float) $read('Distance'));
            }
            if ($read('StayAvailable')) {
                $details[] = $this->Translate('Stay') . ': ' . $this->PresenceFormattedValue($this->GetDeviceVariableIdent($id, 'StayDuration'));
            }
            $rows[] = ['id' => substr(hash('sha256', $id), 0, 32), 'name' => $name !== '' ? $name : ($names[$id] ?? $this->Translate('Device')),
                'status'    => ($presence === -1 ? $labels[$presence] : $labels[$presence] . ' · ' . $read('Zone')) . ($pending ? ' · ' . $this->Translate('Change awaiting confirmation') : ''), 'tone' => $tones[$presence],
                'zone'      => $presence === -1 ? $labels[-1] : $read('Zone'),
                'detail'    => implode(' · ', $details), 'reason' => $presence === -1 || $pending ? $read('Reason') : '',
                'image'     => $pictures[$id] ?? ''];
        }
        $presence = $this->GetValue('Presence');
        $pending = $this->GetValue('PendingCount');
        $summary = sprintf($this->Translate('%d present · %d absent · %d unknown'), $this->GetValue('PresentCount'), $this->GetValue('AbsentCount'), $this->GetValue('UnknownCount'));
        if ($pending > 0) {
            $summary .= ' · ' . sprintf($this->Translate($pending === 1 ? '%d change awaiting confirmation' : '%d changes awaiting confirmation'), $pending);
        }
        $this->PublishStatusWidget(['title' => $this->Translate('Overall presence'), 'summary' => ['label' => $labels[$presence], 'tone' => $tones[$presence],
            'detail'                                                                                       => $summary], 'members' => $rows]);
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
            $effective = $zone['hysteresis'] ?? $buffer;
            if (array_key_exists('hysteresis', $zone)) {
                $limits['minimum'] = 1;
            } else {
                $limits['maximum'] = min($limits['maximum'], (int) ceil($zone['radius']) - 1);
            }
            if ($zone['radius'] <= $effective && $limits['message'] === '') {
                $limits['message'] = sprintf($this->Translate('Zone "%s": radius (%s m) must be greater than the boundary buffer (%s m). Increase the radius or reduce the buffer.'), $zone['name'], (string) $zone['radius'], (string) $effective);
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
        $pending = 0;
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
            $this->CloudDebugProblem($result['reason'], __FUNCTION__, __LINE__, ['GerätInAuswahlliste' => $index, 'Anwesenheit' => $result['presence'], 'WechselWartet' => $result['pending']]);
            $states[$id] = $result['state'];
            $values = ['Presence'   => $result['presence'], 'ZoneID' => $result['zone'] ?? '',
                'Zone'              => $result['zone'] === null ? $this->Translate('Unknown') : ($zoneNames[$result['zone']] ?? $this->Translate('Outside all zones')),
                'StateSince'        => $result['state']['zone'] === null ? 0 : $result['state']['since'],
                'ZoneSince'         => $result['since'], 'StayDuration' => $result['duration'], 'StayAvailable' => $result['since'] > 0,
                'Distance'          => $result['distance'] ?? 0.0, 'DistanceAvailable' => $result['distance'] !== null,
                'TransitionPending' => $result['pending'], 'DataAvailable' => true,
                'Reason'            => $this->Translate(self::REASONS[$result['reason']] ?? 'No valid position available.')];
            foreach ($values as $field => $value) {
                $this->CloudDebugWrite($this->GetDeviceVariableIdent($id, $field), $value, __FUNCTION__, __LINE__);
            }
            $presence[] = $result['presence'];
            $pending += (int) $result['pending'];
        }
        $this->writeState('MemberStates', array_intersect_key($states, $members));
        $this->writeState('MemberNames', array_intersect_key($names, $members));
        $this->publishPresenceSummary($presence, $pending, true, $missing > 0 ? 'Some selected devices are missing from the account cache.' : ($pending > 0 ? 'One or more zone changes await another newer Apple location.' : 'Shared zones evaluated.'));
        $this->CloudDebugStatus($missing === count($members) ? 203 : IS_ACTIVE, __FUNCTION__, __LINE__);
        return true;
    }

    private function unavailable(array $members, string $message, int $status): void
    {
        $this->CloudDebugProblem($message, __FUNCTION__, __LINE__);
        $this->CloudDebugTimer('Evaluate', $status === 202 || $status === 203 ? 15000 : 0, __FUNCTION__, __LINE__);
        $states = $this->readState('MemberStates');
        foreach ($members as $id => $name) {
            $states[$id] = $this->InvalidateGeofenceState(is_array($states[$id] ?? null) ? $states[$id] : []);
            $this->setUnknownMember((string) $id, $message);
        }
        $this->writeState('MemberStates', array_intersect_key($states, $members));
        $this->publishPresenceSummary(array_fill(0, count($members), -1), 0, false, $message);
        $this->WriteAttributeString('LastError', $this->Translate($message));
        $this->CloudDebugStatus($status, __FUNCTION__, __LINE__);
    }

    private function setUnknownMember(string $id, string $reason): void
    {
        $this->CloudDebugProblem($reason, __FUNCTION__, __LINE__);
        foreach (self::MEMBER_FIELDS as $field) {
            $value = match ($field) {
                'Presence' => -1, 'Zone' => $this->Translate('Unknown'), 'ZoneID' => '', 'Reason' => $this->Translate($reason),
                'Distance' => 0.0, 'StateSince', 'ZoneSince', 'StayDuration' => 0, default => false
            };
            $this->CloudDebugWrite($this->GetDeviceVariableIdent($id, $field), $value, __FUNCTION__, __LINE__);
        }
    }

    private function publishPresenceSummary(array $presence, int $pending, bool $available, string $reason): void
    {
        $this->CloudDebugWrite('Presence', $this->AggregateGeofencePresence($presence), __FUNCTION__, __LINE__);
        $this->CloudDebugWrite('SelectedCount', count($presence), __FUNCTION__, __LINE__);
        foreach (['PresentCount' => 1, 'AbsentCount' => 0, 'UnknownCount' => -1] as $ident => $state) {
            $this->CloudDebugWrite($ident, count(array_filter($presence, static fn (int $value): bool => $value === $state)), __FUNCTION__, __LINE__);
        }
        $this->CloudDebugWrite('PendingCount', max(0, $pending), __FUNCTION__, __LINE__);
        $this->CloudDebugWrite('DataAvailable', $available, __FUNCTION__, __LINE__);
        $this->CloudDebugWrite('Reason', $this->Translate($reason), __FUNCTION__, __LINE__);
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
        $visibility = $this->readState('InactiveVariableVisibility');
        $inactive = [];
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
            if ($object['ObjectType'] !== 2 || preg_match('/\ADevice_[a-f0-9]{32}_(.+)\z/D', $ident, $match) !== 1 || !in_array($match[1], self::MEMBER_FIELDS, true)) {
                continue;
            }
            $previous = $visibility[$ident] ?? null;
            $recorded = is_array($previous) && ($previous['id'] ?? null) === $child && is_bool($previous['hidden'] ?? null);
            if (isset($active[$ident])) {
                // Undo only our temporary hide; never force selected variables visible.
                if ($recorded && !$previous['hidden'] && ($previous['restore'] ?? false) === true && $object['ObjectIsHidden']) {
                    IPS_SetHidden($child, false);
                }
            } else {
                $value = match ($match[1]) {
                    'Presence' => -1, 'Zone' => $this->Translate('Unknown'), 'ZoneID' => '', 'Reason' => $this->Translate('Device no longer selected.'),
                    'Distance' => 0.0, 'StateSince', 'ZoneSince', 'StayDuration' => 0, default => false
                };
                $this->CloudDebugWrite($ident, $value, __FUNCTION__, __LINE__);
                $inactive[$ident] = $recorded ? $previous : ['id' => $child, 'hidden' => $object['ObjectIsHidden'], 'restore' => !$object['ObjectIsHidden']];
                if ($recorded && !$object['ObjectIsHidden']) {
                    $inactive[$ident]['restore'] = false;
                }
                // Selection changes hide once. Later user overrides remain untouched.
                if (!$recorded && !$object['ObjectIsHidden']) {
                    IPS_SetHidden($child, true);
                }
            }
        }
        if ($inactive !== $visibility) {
            $this->writeState('InactiveVariableVisibility', $inactive);
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
            $this->CloudDebugWrite('Presence', -1, __FUNCTION__, __LINE__);
        }
        $position = 1;
        foreach (['SelectedCount', 'PresentCount', 'AbsentCount', 'UnknownCount', 'PendingCount'] as $ident) {
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
                'StateSince', 'ZoneSince'  => $this->RegisterVariableInteger($ident, $caption, $this->DateTimePresentation(), $position++),
                'StayDuration'             => $this->RegisterVariableInteger($ident, $caption, $this->DurationPresentation(), $position++),
                default                    => $this->RegisterVariableBoolean($ident, $caption, $this->BooleanPresentation($this->Translate('Yes'), $this->Translate('No')), $position++)
            };
            if ($created && $field === 'Presence') {
                $this->CloudDebugWrite($ident, -1, __FUNCTION__, __LINE__);
            }
        }
    }
}
