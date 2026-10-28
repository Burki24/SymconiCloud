<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CloudDebug.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';

class iCloudDeviceConfigurator extends IPSModuleStrict
{
    use \SymconiCloud\CloudDebug;
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const DEVICE_MODULE = '{DEA72AD7-7877-4392-B64F-055B63D1E5C4}';
    private const PRESENCE_MODULE = '{00438FA2-CF5C-45B0-AFF0-A5A5CE66595E}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    /** Applies the configuration without starting an Apple request. */
    public function ApplyChanges(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->updateForm($this->request('GetDevices'));
        }, true);
    }

    /** @return string SDK parent descriptor allowing connections to shared accounts. */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** @return string Configuration form populated from the account cache only. */
    public function GetConfigurationForm(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            $form = $this->LoadConfigurationForm();
            $result = $this->request('GetDevices');
            $this->CloudDebugStatus(!$this->HasParent() ? 201 : (($result['success'] ?? false) === true ? IS_ACTIVE : 202), __FUNCTION__, __LINE__);
            $form['elements'][0]['values'] = $this->buildValues($result);
            $form['elements'][1]['values'] = $this->buildPresenceValues($result);
            $form['actions'][0]['caption'] = $this->resultCaption($result);
            return $this->EncodeConfigurationForm($form);
        });
    }

    /** @return bool True if an authenticated account cache was available. */
    public function Reload(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            return $this->updateForm($this->request('GetDevices'));
        });
    }

    /** @return bool True if the explicit Apple device request succeeded. */
    public function RefreshDevices(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            return $this->updateForm($this->request('RefreshDevices'));
        });
    }

    /**
     * @param string $Ident Supported form action.
     * @param mixed $Value Action payload, unused.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            match ($Ident) {
                'Reload'         => $this->Reload(),
                'RefreshDevices' => $this->RefreshDevices(),
                default          => throw new InvalidArgumentException('Unsupported action.')
            };
        }, true);
    }

    /** @param string $JSONString Account notification; never triggers a request. */
    public function ReceiveData(string $JSONString): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSONString): string
        {
            try {
                $this->updateForm($this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA));
            } catch (Throwable) {
                $this->CloudDebugStatus(202, __FUNCTION__, __LINE__);
            }
            return '';
        });
    }

    public function Create(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        { parent::Create(); }, true);
    }

    public function Destroy(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        { parent::Destroy(); }, true);
    }

    /** @return array<string, mixed> Account result or a local transport error. */
    private function request(string $command): array
    {
        if (!$this->HasParent()) {
            $this->CloudDebugProblem('No iCloud account connected.', __FUNCTION__, __LINE__);
            return ['success' => false, 'devices' => [], 'error' => $this->Translate('No iCloud account connected.')];
        }
        try {
            $response = $this->CloudDebugParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => $command]), __FUNCTION__, __LINE__);
            $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($result) && is_bool($result['success'] ?? null) && is_array($result['devices'] ?? null)) {
                return $result;
            }
        } catch (Throwable) {
            // Never expose raw transport data or exception messages containing account data.
        }
        return ['success' => false, 'devices' => [], 'error' => $this->Translate('Account data unavailable.')];
    }

    /** @param array<string, mixed> $result @return bool Result success state. */
    private function updateForm(array $result): bool
    {
        $success = ($result['success'] ?? false) === true;
        $this->CloudDebugStatus(!$this->HasParent() ? 201 : ($success ? IS_ACTIVE : 202), __FUNCTION__, __LINE__);
        $this->UpdateFormField('Devices', 'values', json_encode($this->buildValues($result), JSON_THROW_ON_ERROR));
        $this->UpdateFormField('PresenceInstances', 'values', json_encode($this->buildPresenceValues($result), JSON_THROW_ON_ERROR));
        $this->UpdateFormField('Result', 'caption', $this->resultCaption($result));
        return $success;
    }

    /** @param array<string, mixed> $result @return string Localized result caption. */
    private function resultCaption(array $result): string
    {
        return ($result['success'] ?? false) === true
            ? $this->Translate('Cached device list. Refresh explicitly to contact Apple.')
            : (string) ($result['error'] ?? $this->Translate('Account data unavailable.'));
    }

    /** One group entry per existing account-scoped instance; an empty group can be created once. */
    private function buildPresenceValues(array $result): array
    {
        if (!$this->HasParent()) {
            return [];
        }
        $rows = [];
        foreach (IPS_GetInstanceListByModuleID(self::PRESENCE_MODULE) as $instanceID) {
            if ((int) IPS_GetInstance($instanceID)['ConnectionID'] === $this->GetParentID()) {
                $status = (int) IPS_GetInstance($instanceID)['InstanceStatus'];
                $model = match ($status) {
                    201     => $this->Translate('Configure devices, zones and one reference zone.'),
                    202     => $this->Translate('Waiting for the first successful account update.'),
                    203     => $this->Translate('Selected devices are missing from the current account cache.'),
                    default => $this->Translate('Shared zones and multiple devices')
                };
                $rows[] = ['name' => IPS_GetName($instanceID), 'model' => $model, 'instanceID' => $instanceID];
            }
        }
        if ($rows === [] && ($result['success'] ?? false) === true) {
            $rows[] = ['name' => $this->Translate('Presence group'), 'model' => $this->Translate('Shared zones and multiple devices'), 'instanceID' => 0,
                'create'      => ['moduleID' => self::PRESENCE_MODULE, 'configuration' => ['Devices' => '[]']]];
        }
        return $rows;
    }

    /** @param array<string, mixed> $result @return list<array<string, mixed>> Account-scoped device rows. */
    private function buildValues(array $result, string $moduleID = self::DEVICE_MODULE): array
    {
        $parent = $this->HasParent() ? $this->GetParentID() : 0;
        $existing = [];
        if ($parent > 0) {
            foreach (IPS_GetInstanceListByModuleID($moduleID) as $instanceID) {
                if ((int) IPS_GetInstance($instanceID)['ConnectionID'] === $parent) {
                    $existing[(string) IPS_GetProperty($instanceID, 'DeviceID')][] = $instanceID;
                }
            }
        }
        $rows = [];
        $seen = [];
        foreach (($result['devices'] ?? []) as $device) {
            if (!is_array($device) || !is_string($device['id'] ?? null) || $device['id'] === '' || isset($seen[$device['id']])) {
                continue;
            }
            $id = $device['id'];
            $seen[$id] = true;
            $rows[] = [
                'name'       => (string) ($device['name'] ?? $id),
                'model'      => (string) ($device['model'] ?? ''),
                'instanceID' => $existing[$id][0] ?? 0,
            ];
            if ($parent > 0 && ($result['success'] ?? false) === true) {
                $rows[array_key_last($rows)]['create'] = [
                    'moduleID'      => $moduleID,
                    'configuration' => ['DeviceID' => $id]
                ];
            }
            if (isset($existing[$id])) {
                array_shift($existing[$id]);
            }
        }
        foreach ($existing as $instances) {
            foreach ($instances as $instanceID) {
                $rows[] = ['name' => IPS_GetName($instanceID), 'model' => $this->Translate('Not in current list'), 'instanceID' => $instanceID];
            }
        }
        return $rows;
    }
}
