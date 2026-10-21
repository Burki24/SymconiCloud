<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';

class iCloudConfigurator extends IPSModuleStrict
{
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const DEVICE_MODULE = '{DEA72AD7-7877-4392-B64F-055B63D1E5C4}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    /** Applies the configuration without starting an Apple request. */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->updateForm($this->request('GetDevices'));
    }

    /** @return string SDK parent descriptor allowing connections to shared accounts. */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** @return string Configuration form populated from the account cache only. */
    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $result = $this->request('GetDevices');
        $this->SetStatus(!$this->HasParent() ? 201 : (($result['success'] ?? false) === true ? IS_ACTIVE : 202));
        $form['elements'][0]['values'] = $this->buildValues($result);
        $form['actions'][0]['caption'] = $this->resultCaption($result);
        return $this->EncodeConfigurationForm($form);
    }

    /** @return bool True if an authenticated account cache was available. */
    public function Reload(): bool
    {
        return $this->updateForm($this->request('GetDevices'));
    }

    /** @return bool True if the explicit Apple device request succeeded. */
    public function RefreshDevices(): bool
    {
        return $this->updateForm($this->request('RefreshDevices'));
    }

    /**
     * @param string $Ident Supported form action.
     * @param mixed $Value Action payload, unused.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        match ($Ident) {
            'Reload'         => $this->Reload(),
            'RefreshDevices' => $this->RefreshDevices(),
            default          => throw new InvalidArgumentException('Unsupported action.')
        };
    }

    /** @param string $JSONString Account notification; never triggers a request. */
    public function ReceiveData(string $JSONString): string
    {
        try {
            $this->updateForm($this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA));
        } catch (Throwable) {
            $this->SetStatus(202);
        }
        return '';
    }

    /** @return array<string, mixed> Account result or a local transport error. */
    private function request(string $command): array
    {
        if (!$this->HasParent()) {
            return ['success' => false, 'devices' => [], 'error' => $this->Translate('No iCloud account connected.')];
        }
        try {
            $response = $this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => $command]));
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
        $this->SetStatus(!$this->HasParent() ? 201 : ($success ? IS_ACTIVE : 202));
        $this->UpdateFormField('Devices', 'values', json_encode($this->buildValues($result), JSON_THROW_ON_ERROR));
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

    /** @param array<string, mixed> $result @return list<array<string, mixed>> Account-scoped device rows. */
    private function buildValues(array $result): array
    {
        $parent = $this->HasParent() ? $this->GetParentID() : 0;
        $existing = [];
        if ($parent > 0) {
            foreach (IPS_GetInstanceListByModuleID(self::DEVICE_MODULE) as $instanceID) {
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
                    'moduleID'      => self::DEVICE_MODULE,
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
