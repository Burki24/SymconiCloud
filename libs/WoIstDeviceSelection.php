<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Presentation selection from explicitly assigned account caches only. */
trait WoIstDeviceSelection
{
    private function RegisterWoIstDeviceSelection(): void
    {
        $this->RegisterPropertyBoolean('ShowAllDevices', true);
        $this->RegisterPropertyString('SelectedDevices', '[]');
    }

    private function WoIstDeviceSelectionKey(int $accountID, string $deviceID): string
    {
        return 'account:' . $accountID . ':' . hash('sha256', $deviceID);
    }

    /** @return list<string> Account-scoped identities, independent of primary/extra roles. */
    private function WoIstSelectedDeviceKeys(): array
    {
        $raw = $this->ReadPropertyString('SelectedDevices');
        if (strlen($raw) > 262144) {
            throw new \InvalidArgumentException('Invalid device selection.');
        }
        $rows = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || count($rows) > 1000) {
            throw new \InvalidArgumentException('Invalid device selection.');
        }
        $keys = [];
        foreach ($rows as $row) {
            if (!is_object($row) || !is_string($row->DeviceKey ?? null)
                || ($row->DeviceKey !== '' && preg_match('/\Aaccount:[1-9][0-9]{0,9}:[a-f0-9]{64}\z/D', $row->DeviceKey) !== 1)) {
                throw new \InvalidArgumentException('Invalid device selection.');
            }
            if ($row->DeviceKey !== '') {
                $keys[$row->DeviceKey] = true;
            }
        }
        return array_keys($keys);
    }

    private function FilterWoIstDeviceData(array $data): array
    {
        $devices = is_array($data['devices'] ?? null) ? $data['devices'] : [];
        $all = $this->ReadPropertyBoolean('ShowAllDevices');
        if (!$all) {
            $selected = array_fill_keys($this->WoIstSelectedDeviceKeys(), true);
            $data['devices'] = array_values(array_filter($devices, static fn ($device): bool => is_array($device) && isset($selected[$device['selectionKey'] ?? ''])));
        }
        $this->DebugFlowEvent(
            __FUNCTION__,
            __LINE__,
            'AKTION',
            $all
            ? 'Alle Geräte der zugewiesenen Konten für Karte und Liste anzeigen.'
            : 'Nur die gespeicherte Geräteauswahl für Karte und Liste verwenden. Eine leere Auswahl zeigt keine Geräte; neue Geräte werden nicht automatisch aufgenommen.',
            ['AlleGeräte' => $all, 'GeräteImCache' => count($devices), 'AngezeigteGeräte' => count($data['devices'] ?? [])]
        );
        return $data;
    }

    /** Builds dropdown choices without Apple requests or changes to stored selection. */
    private function WoIstDeviceSelectionForm(array $form): array
    {
        $choices = [];
        $invalid = false;
        try {
            foreach ($this->WoIstSelectedDeviceKeys() as $key) {
                $parts = explode(':', $key);
                $choices[$key] = ['caption' => sprintf($this->Translate('Unavailable device (%s)'), $parts[1] . ' / ' . substr($parts[2], 0, 8)), 'value' => $key];
            }
        } catch (\Throwable) {
            $invalid = true;
        }
        try {
            $inventory = $this->CollectWoIstAccounts('GetDevices');
            foreach ($inventory['devices'] as $device) {
                $key = $device['selectionKey'];
                $name = is_string($device['name'] ?? null) && $device['name'] !== '' ? $device['name'] : $this->Translate('Unnamed device');
                $choices[$key] = ['caption' => $name . ' — ' . $device['accountName'] . ' (#' . $device['accountId'] . ')', 'value' => $key];
            }
        } catch (\Throwable) {
            // Keep stored choices visible when an assigned cache is unavailable.
        }
        uasort($choices, static fn (array $left, array $right): int => strnatcasecmp($left['caption'], $right['caption']));
        $options = [['caption' => $this->Translate('Select a cached device'), 'value' => ''], ...array_values($choices)];
        foreach ($form['elements'] as &$panel) {
            if (($panel['name'] ?? '') !== 'DeviceSelectionPanel') {
                continue;
            }
            foreach ($panel['items'] as &$item) {
                if (($item['name'] ?? '') === 'SelectedDevices') {
                    $item['columns'][0]['edit']['options'] = $options;
                }
                if (($item['name'] ?? '') === 'DeviceSelectionWarning') {
                    $item['visible'] = $invalid;
                }
            }
            unset($item);
        }
        unset($panel);
        return $form;
    }
}
