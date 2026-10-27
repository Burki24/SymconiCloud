<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Explicitly assigned account caches; authentication and transport remain in each account. */
trait WoIstAccounts
{
    /** The primary account retains its official parent/child data-flow path. */
    protected function ReadWoIstAccount(int $accountID, string $command): array
    {
        // Kernel interfaces may emit a warning while being rebuilt on reload.
        // Treat that narrow boundary as unavailable data, never as form output.
        if ($accountID === $this->GetParentID()) {
            $reply = @$this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => $command]));
        } else {
            // Public, account-owned APIs on explicitly selected sources only.
            // Neither path creates a login or reads credentials/session attributes.
            $refreshed = $command !== 'RefreshDevices' || @\ICAC_RefreshDevices($accountID);
            $reply = @\ICAC_GetDevices($accountID);
        }
        $data = json_decode($reply, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_bool($data['success'] ?? null) || !is_array($data['devices'] ?? null) || !array_is_list($data['devices'])) {
            throw new \UnexpectedValueException('Account cache unavailable.');
        }
        if (isset($refreshed) && !$refreshed) {
            $data['success'] = false;
        }
        return $data;
    }
    private function RegisterWoIstAccounts(): void
    {
        $this->RegisterPropertyString('AdditionalAccounts', '[]');
        $this->RegisterTimer('UpdateAccounts', 0, 'ICWI_Update($_IPS["TARGET"]);');
    }

    /** @return list<int> Stable instance identities, with the existing parent first. */
    private function WoIstAccountIDs(): array
    {
        $raw = $this->ReadPropertyString('AdditionalAccounts');
        if (strlen($raw) > 65536) {
            throw new \InvalidArgumentException('Invalid account selection.');
        }
        $rows = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 20) {
            throw new \InvalidArgumentException('Invalid account selection.');
        }
        $ids = $this->GetParentID() > 0 ? [$this->GetParentID()] : [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['AccountID'] ?? null) || $row['AccountID'] < 0) {
                throw new \InvalidArgumentException('Invalid account selection.');
            }
            if ($row['AccountID'] > 0 && !in_array($row['AccountID'], $ids, true)) {
                $ids[] = $row['AccountID'];
            }
        }
        return $ids;
    }

    private function HasWoIstAccounts(): bool
    {
        try {
            return $this->WoIstAccountIDs() !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    /** One failure must not hide other assigned accounts or conflate duplicate device IDs. */
    private function CollectWoIstAccounts(string $command, ?array $notification = null): array
    {
        $accounts = [];
        $devices = [];
        $available = [];
        $primary = null;
        foreach ($this->WoIstAccountIDs() as $id) {
            $name = $this->Translate('Unavailable account');
            $data = $this->unavailable();
            try {
                if (!\IPS_InstanceExists($id) || \IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== self::ACCOUNT_MODULE) {
                    throw new \UnexpectedValueException();
                }
                $name = \IPS_GetName($id);
                $data = $id === $this->GetParentID() && $notification !== null ? $notification : $this->ReadWoIstAccount($id, $command);
            } catch (\Throwable) {
                // Source exceptions, session data and provider payloads never reach the map.
            }
            $ok = ($data['success'] ?? false) === true && ($data['state'] ?? '') === 'authenticated' && is_int($data['updatedAt'] ?? null) && $data['updatedAt'] > 0;
            $accounts[] = ['id' => (string) $id, 'name' => $name, 'success' => $ok, 'updatedAt' => max(0, (int) ($data['updatedAt'] ?? 0))];
            if ($ok) {
                $available[] = $data['updatedAt'];
            }
            if ($primary === null) {
                $primary = $data;
            }
            foreach (is_array($data['devices'] ?? null) ? $data['devices'] : [] as $device) {
                if (!is_array($device) || !is_string($device['id'] ?? null) || $device['id'] === '') {
                    continue;
                }
                // Existing primary IDs deliberately stay unchanged for saved selections.
                if ($id !== $this->GetParentID()) {
                    $device['id'] = 'account:' . $id . ':' . hash('sha256', $device['id']);
                }
                $devices[] = array_replace($device, ['accountId' => (string) $id, 'accountName' => $name, 'accountAvailable' => $ok]);
            }
        }
        $result = $primary ?? $this->unavailable('No iCloud account connected.');
        if (count($accounts) > 1) {
            $result = ['success' => $available !== [], 'state' => $available !== [] ? 'authenticated' : 'error',
                'updatedAt'      => $available !== [] ? min($available) : 0,
                'error'          => count($available) === count($accounts) ? '' : $this->Translate('Some accounts are unavailable. Their positions are last known.')];
        }
        return array_replace($result, ['devices' => $devices, 'accounts' => $accounts]);
    }
}
