<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Shared account-owned Contacts cache. No mutations or credentials are exposed to consumers. */
trait ContactsAccount
{
    /** Local read only; stale data stays accessible and is explicitly marked. */
    public function GetContacts(string $Query = '', int $Offset = 0, int $Limit = 100): string
    {
        return json_encode($this->contactsEnvelope($Query, $Offset, $Limit), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Exact lookup; a deleted contact never falls back to a same-named recipient. */
    public function GetContact(string $ContactID): string
    {
        if ($ContactID === '' || strlen($ContactID) > 1024) {
            throw new \InvalidArgumentException('Invalid contact ID.');
        }
        $result = $this->contactsEnvelope('', 0, 1);
        unset($result['contacts'], $result['total'], $result['offset'], $result['limit'], $result['nextOffset']);
        $result['contact'] = null;
        if ($result['success']) {
            $cache = json_decode($this->ReadAttributeString('ContactsCache'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($cache['contacts'] as $contact) {
                if ($contact['id'] === $ContactID) {
                    $result['contact'] = $contact;
                    break;
                }
            }
        }
        return json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Uses only an authenticated session. Account protection is checked before creating a transport. */
    public function RefreshContacts(): bool
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return false;
        }
        $client = null;
        $success = false;
        try {
            if (!$this->configurationValid() || !$this->ReadPropertyBoolean('EnableContacts')
                || $this->credentialFingerprint() !== $this->ReadAttributeString('CredentialFingerprint')) {
                throw new AppleException('contactsDisabled');
            }
            if (!$this->contactsIntervalValid()) {
                throw new AppleException('contactsConfiguration');
            }
            $this->AssertAccountRequestsAllowed();
            if (($this->readSession()['state'] ?? '') !== 'authenticated') {
                throw new AppleException('authentication');
            }
            // Repeated clicks and consumers cannot turn directory reads into high-frequency polling.
            if ($this->ReadAttributeInteger('ContactsAttemptAt') > $this->AccountProtectionTime() - 60) {
                throw new AppleException('contactsWait');
            }
            $this->WriteAttributeInteger('ContactsAttemptAt', $this->AccountProtectionTime());
            $client = $this->CreateAppleClient($this->readSession());
            $contacts = $client->contacts();
            $cache = json_encode(['contacts' => $contacts, 'updatedAt' => $this->AccountProtectionTime()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (strlen($cache) > 4 * 1024 * 1024) {
                throw new AppleException('contactsLimit');
            }
            // Replace atomically only after the complete snapshot was validated.
            $this->WriteAttributeString('ContactsCache', $cache);
            $this->WriteAttributeString('ContactsError', '');
            $this->WriteAttributeInteger('TransientFailures', 0);
            $this->RecordAccountDiagnostic('contacts', 0, 'ok');
            $success = true;
        } catch (AppleException $error) {
            if ($error->reason !== 'contactsWait') {
                $this->WriteAttributeString('ContactsError', $error->reason);
            }
            $this->AccountRequestFailed('contacts', $error, $client !== null);
        } catch (\Throwable) {
            $this->WriteAttributeString('ContactsError', 'internal');
            $this->RecordAccountDiagnostic('contacts', 0, 'internal');
        } finally {
            if ($client !== null) {
                $this->WriteAttributeString('Session', json_encode($client->session(), JSON_THROW_ON_ERROR));
            }
            $this->updateState();
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        $this->notifyChildren();
        return $success;
    }
    protected function RegisterContactsAccount(): void
    {
        $this->RegisterPropertyBoolean('EnableContacts', false);
        $this->RegisterPropertyInteger('ContactsRefreshInterval', 3600);
        $this->RegisterAttributeString('ContactsCache', '{"contacts":[],"updatedAt":0}');
        $this->RegisterAttributeString('ContactsError', '');
        $this->RegisterAttributeInteger('ContactsAttemptAt', 0);
        $this->RegisterTimer('ContactsRefresh', 0, 'ICAC_RefreshContacts($_IPS["TARGET"]);');
    }

    protected function ClearContactsState(): void
    {
        $this->WriteAttributeString('ContactsCache', '{"contacts":[],"updatedAt":0}');
        $this->WriteAttributeString('ContactsError', '');
        $this->WriteAttributeInteger('ContactsAttemptAt', 0);
        $this->SetTimerInterval('ContactsRefresh', 0);
    }

    protected function ForwardContactsRequest(array $request): array
    {
        if ($request['Command'] === 'GetContact') {
            if (array_diff(array_keys($request), ['DataID', 'Command', 'ContactID']) !== [] || !is_string($request['ContactID'] ?? null)) {
                throw new \InvalidArgumentException('Invalid contact lookup.');
            }
            return json_decode($this->GetContact($request['ContactID']), true, 512, JSON_THROW_ON_ERROR);
        }
        if (array_diff(array_keys($request), ['DataID', 'Command', 'Query', 'Offset', 'Limit']) !== []
            || (array_key_exists('Query', $request) && !is_string($request['Query'])) || (array_key_exists('Offset', $request) && !is_int($request['Offset']))
            || (array_key_exists('Limit', $request) && !is_int($request['Limit']))) {
            throw new \InvalidArgumentException('Invalid directory request.');
        }
        if ($request['Command'] === 'RefreshContacts') {
            if (count($request) !== 2) {
                throw new \InvalidArgumentException('Invalid refresh request.');
            }
            return ['success' => $this->RefreshContacts()];
        }
        return $this->contactsEnvelope($request['Query'] ?? '', $request['Offset'] ?? 0, $request['Limit'] ?? 100);
    }

    /** Called only after the shared account has passed its configuration and protection checks. */
    protected function UpdateContactsTimer(): void
    {
        if ($this->ReadPropertyBoolean('EnableContacts') && $this->contactsIntervalValid()) {
            $wait = max(
                60,
                $this->ReadAttributeInteger('ContactsAttemptAt') + $this->ReadPropertyInteger('ContactsRefreshInterval') - $this->AccountProtectionTime(),
                $this->ReadAttributeInteger('RetryAt') - $this->AccountProtectionTime()
            );
            $this->SetTimerInterval('ContactsRefresh', $wait * 1000);
        }
    }

    private function contactsIntervalValid(): bool
    {
        return $this->ReadPropertyInteger('ContactsRefreshInterval') >= 900 && $this->ReadPropertyInteger('ContactsRefreshInterval') <= 604800;
    }

    private function contactsEnvelope(string $query, int $offset, int $limit): array
    {
        // Validate even when disabled so public query contracts are deterministic.
        ContactsDirectory::page([], $query, $offset, $limit);
        $enabled = $this->ReadPropertyBoolean('EnableContacts') && $this->ReadPropertyBoolean('Enabled')
            && $this->credentialFingerprint() === $this->ReadAttributeString('CredentialFingerprint');
        $cache = $enabled ? json_decode($this->ReadAttributeString('ContactsCache'), true, 512, JSON_THROW_ON_ERROR) : ['contacts' => [], 'updatedAt' => 0];
        $updatedAt = (int) $cache['updatedAt'];
        $paused = $this->ReadPropertyBoolean('PauseRequests');
        $reason = !$enabled ? 'contactsDisabled' : (!$this->configurationValid() ? 'configuration' : (!$this->contactsIntervalValid() ? 'contactsConfiguration' : $this->ReadAttributeString('ContactsError')));
        if ($reason === '' && ($this->readSession()['state'] ?? '') !== 'authenticated') {
            $reason = 'authentication';
        }
        if ($reason === '' && $this->ReadAttributeString('AccountBlock') !== '') {
            $reason = 'accountBlocked';
        }
        if ($reason === '' && $paused) {
            $reason = 'requestsPaused';
        }
        $stale = $updatedAt <= 0 || $reason !== '' || $this->AccountProtectionTime() - $updatedAt >= $this->ReadPropertyInteger('ContactsRefreshInterval');
        return ['success' => $enabled && $updatedAt > 0, 'stale' => $stale, 'paused' => $paused, 'updatedAt' => $updatedAt,
            'error'       => $this->errorMessage($reason)] + ContactsDirectory::page($cache['contacts'], $query, $offset, $limit);
    }
}
