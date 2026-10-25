<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/ContactsDirectory.php';
require_once __DIR__ . '/../libs/ContactsRecipientTransfer.php';

class iCloudContacts extends IPSModuleStrict
{
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \SymconiCloud\ContactsRecipientTransfer;

    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    public function Create(): void
    {
        parent::Create();
        $this->RegisterRecipientTransfer();
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => ['{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}']], JSON_THROW_ON_ERROR);
    }

    /** Form opening, reconnection and pushes only read the existing account cache. */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->InvalidateRecipientTransfer();
        $this->Reload();
    }

    public function ReceiveData(string $JSONString): string
    {
        try {
            $this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA);
            $this->Reload();
        } catch (Throwable) {
            $this->SetStatus(202);
        }
        return '';
    }

    /** Returns a bounded local page; never starts an Apple request. */
    public function GetContacts(string $Query = '', int $Offset = 0, int $Limit = 100): string
    {
        \SymconiCloud\ContactsDirectory::page([], $Query, $Offset, $Limit);
        return json_encode($this->request('GetContacts', ['Query' => $Query, 'Offset' => $Offset, 'Limit' => $Limit]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Scope persisted recipient references by this instance ID and the contact ID together. */
    public function GetContact(string $ContactID): string
    {
        if ($ContactID === '' || strlen($ContactID) > 1024) {
            throw new InvalidArgumentException('Invalid contact ID.');
        }
        return json_encode($this->request('GetContact', ['ContactID' => $ContactID]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Explicit, protected refresh of the account's shared directory. */
    public function Refresh(): bool
    {
        $result = $this->request('RefreshContacts');
        $this->Reload();
        return ($result['success'] ?? false) === true;
    }

    public function SearchContacts(string $Query): void
    {
        \SymconiCloud\ContactsDirectory::page([], $Query, 0, 1);
        $this->SetBuffer('ContactSearch', $Query);
        $this->FirstContacts();
    }

    public function FirstContacts(): void
    {
        $this->SetBuffer('ContactOffset', '0');
        $this->Reload();
    }

    public function NextContacts(): void
    {
        $next = $this->GetBuffer('ContactNext');
        if ($next !== '') {
            $this->SetBuffer('ContactOffset', $next);
            $this->Reload();
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        match ($Ident) {
            'Refresh'        => $this->Refresh(),
            'SearchContacts' => $this->SearchContacts(is_string($Value) ? $Value : ''),
            'FirstContacts'  => $this->FirstContacts(),
            'NextContacts'   => $this->NextContacts(),
            default          => throw new InvalidArgumentException('Unsupported directory action.')
        };
    }

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $result = $this->currentPage();
        $form['actions'][0]['caption'] = $this->caption($result);
        $form['actions'][1]['value'] = $this->GetBuffer('ContactSearch');
        $form['actions'][2]['values'] = $this->rows($result);
        $form['actions'][4]['enabled'] = $result['nextOffset'] !== null;
        return $this->EncodeConfigurationForm($this->ConfigureRecipientForm($form, $result));
    }

    public function Reload(): bool
    {
        $result = $this->currentPage();
        $this->UpdateFormField('ContactResult', 'caption', $this->caption($result));
        $this->UpdateFormField('ContactList', 'values', json_encode($this->rows($result), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateFormField('ContactNextPage', 'enabled', $result['nextOffset'] !== null);
        $this->UpdateFormField('TransferContactID', 'options', json_encode($this->RecipientContactOptions($result), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $result['success'];
    }

    private function currentPage(): array
    {
        $result = json_decode($this->GetContacts($this->GetBuffer('ContactSearch'), (int) $this->GetBuffer('ContactOffset')), true, 512, JSON_THROW_ON_ERROR);
        $this->SetBuffer('ContactNext', $result['nextOffset'] === null ? '' : (string) $result['nextOffset']);
        $this->SetStatus(!$this->HasParent() ? 201 : (!$result['success'] ? 202 : ($result['stale'] ? 203 : IS_ACTIVE)));
        return $result;
    }

    private function request(string $command, array $arguments = []): array
    {
        if ($this->HasParent()) {
            try {
                $reply = $this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => $command] + $arguments));
                $result = json_decode($reply, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($result) && is_bool($result['success'] ?? null)) {
                    if ($command === 'RefreshContacts' || ($command === 'GetContact' && array_key_exists('contact', $result))
                        || (is_array($result['contacts'] ?? null) && is_int($result['total'] ?? null) && is_bool($result['stale'] ?? null) && array_key_exists('nextOffset', $result))) {
                        return $result;
                    }
                }
            } catch (Throwable) {
                // Never forward provider payloads, transport errors or exception arguments.
            }
        }
        return ['success' => false, 'stale' => true, 'paused' => false, 'contacts' => [], 'contact' => null, 'total' => 0, 'offset' => 0,
            'limit'       => 100, 'nextOffset' => null, 'updatedAt' => 0, 'error' => $this->Translate('Contact directory unavailable. Check the connected account.')];
    }

    private function caption(array $result): string
    {
        $text = $this->Translate($result['success'] ? ($result['stale'] ? 'Cached contacts are not current.' : 'Contact directory is current.') : 'No contact snapshot available.');
        return $text . ' ' . $this->Translate('Matches') . ': ' . $result['total'] . '. '
            . $this->Translate('Last successful refresh') . ': ' . ($result['updatedAt'] > 0 ? date('Y-m-d H:i:s', $result['updatedAt']) : $this->Translate('Not available'))
            . '. ' . $result['error'];
    }

    private function rows(array $result): array
    {
        return array_map(fn ($contact) => ['ContactID' => $contact['id'], 'Name' => $contact['displayName'] === 'Unnamed contact' ? $this->Translate('Unnamed contact') : $contact['displayName'],
            'Company'                                  => $contact['company'], 'Emails' => $this->addressList($contact['emails'])] + $this->phoneColumns($contact['phones']), $result['contacts']);
    }

    /** Presentation only: recipient values and original labels in the public API remain unchanged. */
    private function phoneColumns(array $phones): array
    {
        $groups = array_fill_keys(['Mobile1', 'Mobile2', 'Mobile3', 'Work', 'Main', 'Home', 'IPhone', 'Fax', 'Pager', 'OtherPhones'], []);
        $mobileSlot = 1;
        foreach ($phones as $phone) {
            $label = trim($phone['label']);
            if (preg_match('/\A_\$!<([^<>]+)>!\$_\z/D', $label, $wrapped) === 1) {
                $label = $wrapped[1];
            }
            $label = strtoupper(trim(preg_replace('/[\s_-]+/u', ' ', $label)));
            $column = match ($label) {
                'MOBILE'                                   => 'Mobile', 'WORK' => 'Work', 'MAIN' => 'Main', 'HOME' => 'Home', 'IPHONE' => 'IPhone',
                'FAX', 'HOME FAX', 'WORK FAX', 'OTHER FAX' => 'Fax', 'PAGER' => 'Pager', default => 'OtherPhones'
            };
            if ($column !== 'OtherPhones') {
                $phone['label'] = match ($label) {
                    'HOME FAX' => $this->Translate('Home fax'), 'WORK FAX' => $this->Translate('Work fax'), 'OTHER FAX' => $this->Translate('Other fax'), default => ''
                };
            } else {
                // User-defined display rule, applied only when no explicit category matched.
                $number = preg_replace('/[\s().\/-]+/u', '', $phone['value']);
                if (preg_match('/\A(?:\+491|01)[0-9]+\z/D', $number) === 1) {
                    $column = 'Mobile';
                }
            }
            if ($column === 'Mobile') {
                // Keep every mobile destination in the mobile columns, including overflow.
                $column .= min($mobileSlot++, 3);
            }
            $groups[$column][] = $phone;
        }
        return array_map(fn ($entries) => $this->addressList($entries), $groups);
    }

    private function addressList(array $entries): string
    {
        return $entries === [] ? $this->Translate('Not available') : implode('; ', array_map(static fn ($entry) => ($entry['label'] === '' ? '' : $entry['label'] . ': ') . $entry['value'], $entries));
    }
}
