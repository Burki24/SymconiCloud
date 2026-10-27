<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Library-local configuration bridge. Never calls a delivery or notification-level function. */
trait ContactsRecipientTransfer
{
    private const NOTICE_BASE_MODULE = '{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}';
    private const NOTIFICATION_MODULE = '{57677DB8-4C2A-4673-A267-B083A0755CDE}';

    /** Prepares one bounded transfer from unsaved form inputs, without modifying either target. */
    public function PreviewRecipientTransfer(int $TargetID, string $ContactID, string $Phone, string $Email, string $UserID, int $Level, int $Action): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($TargetID, $ContactID, $Phone, $Email, $UserID, $Level, $Action): bool
        {
            if (!IPS_SemaphoreEnter('SymconiCloud.RecipientTransfer.' . $this->InstanceID, 1000)) {
                $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
                return false;
            }
            $this->SetBuffer('RecipientTransferPending', '');
            try {
                $source = $this->recipientSource($ContactID);
                $contact = $source['contact'];
                foreach ([[$Phone, 'phones'], [$Email, 'emails']] as [$selected, $field]) {
                    if ($selected !== '' && !in_array($selected, array_column($contact[$field], 'value'), true)) {
                        throw new \RuntimeException('Selected address is no longer available.');
                    }
                }
                if ($Phone === '' && $Email === '') {
                    throw new \RuntimeException('Select at least one phone number or email address.');
                }
                $target = $this->ReadRecipientTarget($TargetID);
                $plan = $this->recipientPlan($target, $contact, $Phone, $Email, $UserID, $Level, $Action);
                $changes = $plan['changes'];
                $pending = ['target' => $TargetID, 'module' => $target['module'], 'configurationHash' => $this->recipientHash($target['configuration']),
                    'arguments'      => [$Phone, $Email, $UserID, $Level, $Action], 'sourceID' => $ContactID, 'sourceHash' => $this->recipientHash($contact),
                    'parent'         => $this->GetParentID(), 'createdAt' => $this->RecipientTransferTime(), 'changes' => $changes];
                $this->SetBuffer('RecipientTransferPending', json_encode($pending, JSON_THROW_ON_ERROR));
                $this->recipientTransferFeedback('Preview ready. Review the changes before confirming.', $changes);
                return true;
            } catch (\Throwable $error) {
                $this->recipientTransferFeedback($this->recipientTransferError($error), []);
                return false;
            } finally {
                IPS_SemaphoreLeave('SymconiCloud.RecipientTransfer.' . $this->InstanceID);
            }
        });
    }

    /** One-use confirmation, tied to the previewed source and target snapshots. */
    public function ApplyRecipientTransfer(bool $Confirmed): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Confirmed): bool
        {
            if (!$Confirmed) {
                $this->DebugFlowEvent($this->CloudDebugFunction(__FUNCTION__), __LINE__, 'AKTION', 'Empfängerübernahme nicht bestätigt oder abgebrochen. Zielkonfiguration bleibt unverändert; keine Nachricht wird versendet.');
                return false;
            }
            if (!IPS_SemaphoreEnter('SymconiCloud.RecipientTransfer.' . $this->InstanceID, 1000)) {
                $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
                return false;
            }
            $targetLock = null;
            try {
                $raw = $this->GetBuffer('RecipientTransferPending');
                if ($raw === '') {
                    throw new \RuntimeException('Create a preview before applying recipient changes.');
                }
                $pending = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                $this->SetBuffer('RecipientTransferPending', '');
                if (!is_array($pending) || $this->RecipientTransferTime() - $pending['createdAt'] > 300
                    || $this->RecipientTransferTime() < $pending['createdAt'] || $pending['parent'] !== $this->GetParentID()) {
                    throw new \RuntimeException('Preview expired or changed. Create a new preview.');
                }
                $targetLock = 'SymconiCloud.RecipientTarget.' . $pending['target'];
                if (!IPS_SemaphoreEnter($targetLock, 1000)) {
                    $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
                    $targetLock = null;
                    throw new \RuntimeException('Target is busy. Create a new preview and try again.');
                }
                $source = $this->recipientSource($pending['sourceID']);
                $target = $this->ReadRecipientTarget($pending['target']);
                if ($target['module'] !== $pending['module'] || $this->recipientHash($source['contact']) !== $pending['sourceHash']
                    || $this->recipientHash($target['configuration']) !== $pending['configurationHash']) {
                    throw new \RuntimeException('Preview expired or changed. Create a new preview.');
                }
                $plan = $this->recipientPlan($target, $source['contact'], ...$pending['arguments']);
                $this->WriteRecipientTarget($pending['target'], $plan['property'], $plan['before'], $plan['after']);
                $this->recipientTransferFeedback('Recipient configuration applied. No message was sent.', $pending['changes']);
                return true;
            } catch (\Throwable $error) {
                $this->SetBuffer('RecipientTransferPending', '');
                $this->recipientTransferFeedback($this->recipientTransferError($error), []);
                return false;
            } finally {
                if ($targetLock !== null) {
                    IPS_SemaphoreLeave($targetLock);
                }
                IPS_SemaphoreLeave('SymconiCloud.RecipientTransfer.' . $this->InstanceID);
            }
        });
    }

    /** Returns only the visible preview, never the target's complete configuration. */
    public function GetRecipientTransferPreview(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->GetBuffer('RecipientTransferVisible') ?: '{"ready":false,"message":"","changes":[]}';
        });
    }

    /** Refreshes address options from the local cache without any configuration write. */
    public function UpdateRecipientChoices(string $ContactID): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($ContactID): void
        {
            $this->SetBuffer('RecipientContactSelection', $ContactID);
            $this->SetBuffer('RecipientTransferPending', '');
            $this->recipientTransferFeedback('Create a preview before applying recipient changes.', []);
            $source = json_decode($this->GetContact($ContactID ?: 'none'), true, 64, JSON_THROW_ON_ERROR);
            foreach (['TransferPhone' => 'phones', 'TransferEmail' => 'emails'] as $name => $key) {
                $this->UpdateFormField($name, 'options', json_encode($this->recipientAddressOptions($source['contact'][$key] ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                $this->UpdateFormField($name, 'value', '');
            }
        }, true);
    }

    public function InvalidateRecipientTransfer(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            $this->SetBuffer('RecipientTransferPending', '');
            $this->recipientTransferFeedback('Create a preview before applying recipient changes.', []);
        }, true);
    }

    protected function RegisterRecipientTransfer(): void
    {
        $this->RegisterPropertyInteger('TransferTargetID', 0);
        $this->RegisterPropertyString('TransferContactID', '');
        $this->RegisterPropertyString('TransferPhone', '');
        $this->RegisterPropertyString('TransferEmail', '');
        $this->RegisterPropertyString('TransferUserID', '');
        $this->RegisterPropertyInteger('TransferLevel', 1);
        $this->RegisterPropertyInteger('TransferAction', 1);
    }

    protected function RecipientTransferTime(): int
    {
        return time();
    }

    protected function ReadRecipientTarget(int $targetID): array
    {
        if ($targetID <= 0 || !IPS_InstanceExists($targetID)) {
            throw new \RuntimeException('Select an installed recipient target.');
        }
        $module = IPS_GetInstance($targetID)['ModuleInfo']['ModuleID'];
        if (!in_array($module, [self::NOTICE_BASE_MODULE, self::NOTIFICATION_MODULE], true)) {
            throw new \RuntimeException('Select an installed recipient target.');
        }
        if (IPS_HasChanges($targetID)) {
            throw new \RuntimeException('The target has unapplied changes. Apply or discard them first.');
        }
        return ['module' => $module, 'configuration' => json_decode(IPS_GetConfiguration($targetID), true, 64, JSON_THROW_ON_ERROR)];
    }

    protected function WriteRecipientTarget(int $targetID, string $property, string $before, string $after): void
    {
        if (IPS_HasChanges($targetID) || IPS_GetProperty($targetID, $property) !== $before) {
            throw new \RuntimeException('Preview expired or changed. Create a new preview.');
        }
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Bestätigte Empfängereinstellungen werden an die Zielinstanz übergeben. Empfängerwerte und sonstige Einstellungen bleiben im Debug geschützt; kein Nachrichtenversand.', ['Zielinstanz' => $targetID]);
        try {
            IPS_SetProperty($targetID, $property, $after);
            IPS_ApplyChanges($targetID);
            if (IPS_GetProperty($targetID, $property) !== $after) {
                throw new \RuntimeException();
            }
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Die Zielinstanz enthält die erwarteten Empfängereinstellungen. Eine Nachricht wurde nicht versendet.', ['Zielinstanz' => $targetID]);
        } catch (\Throwable) {
            try {
                IPS_SetProperty($targetID, $property, $before);
                IPS_ApplyChanges($targetID);
            } catch (\Throwable) {
                throw new \RuntimeException('Target update and restoration failed. Check the target configuration.');
            }
            throw new \RuntimeException('Target update failed. The previous configuration was restored.');
        }
    }

    protected function ConfigureRecipientForm(array $form, array $page): array
    {
        $targets = [['caption' => $this->Translate('Select target'), 'value' => 0]];
        foreach ([self::NOTICE_BASE_MODULE => 'Mitteilungen', self::NOTIFICATION_MODULE => 'Benachrichtigung'] as $module => $label) {
            foreach (IPS_GetInstanceListByModuleID($module) as $id) {
                $targets[] = ['caption' => $label . ': ' . IPS_GetName($id) . ' (' . $id . ')', 'value' => $id];
            }
        }
        $contacts = $this->RecipientContactOptions($page);
        $sourceID = $this->ReadPropertyString('TransferContactID');
        $source = $sourceID !== '' ? json_decode($this->GetContact($sourceID), true, 64, JSON_THROW_ON_ERROR) : [];
        if (is_array($source['contact'] ?? null) && !in_array($sourceID, array_column($contacts, 'value'), true)) {
            $contacts[] = ['caption' => $source['contact']['displayName'], 'value' => $sourceID];
        }
        foreach ($form['elements'][2]['items'] as &$item) {
            $item['options'] = match ($item['name'] ?? '') {
                'TransferTargetID' => $targets, 'TransferContactID' => $contacts,
                'TransferPhone'    => $this->recipientAddressOptions($source['contact']['phones'] ?? []),
                'TransferEmail'    => $this->recipientAddressOptions($source['contact']['emails'] ?? []),
                default            => $item['options'] ?? null
            };
            if ($item['options'] === null) {
                unset($item['options']);
            }
        }
        unset($item);
        $visible = json_decode($this->GetRecipientTransferPreview(), true, 64, JSON_THROW_ON_ERROR);
        $form['actions'][6]['items'][0]['caption'] = $visible['message'];
        $form['actions'][6]['items'][1]['values'] = $visible['changes'];
        return $form;
    }

    protected function RecipientContactOptions(array $page): array
    {
        $options = [['caption' => $this->Translate('Select contact'), 'value' => '']];
        foreach ($page['contacts'] as $contact) {
            $options[] = ['caption' => $contact['displayName'], 'value' => $contact['id']];
        }
        $selected = $this->GetBuffer('RecipientContactSelection') ?: $this->ReadPropertyString('TransferContactID');
        if ($selected !== '' && !in_array($selected, array_column($options, 'value'), true)) {
            $source = json_decode($this->GetContact($selected), true, 64, JSON_THROW_ON_ERROR);
            if (is_array($source['contact'] ?? null)) {
                $options[] = ['caption' => $source['contact']['displayName'], 'value' => $selected];
            }
        }
        return $options;
    }

    private function recipientPlan(array $target, array $contact, string $Phone, string $Email, string $UserID, int $Level, int $Action): array
    {
        $property = $target['module'] === self::NOTICE_BASE_MODULE ? 'users' : 'NotificationLevels';
        $old = $target['configuration'][$property] ?? null;
        if (!is_string($old) || strlen($old) > 1048576) {
            throw new \RuntimeException('Unsupported recipient configuration.');
        }
        $data = json_decode($old, true, 64, JSON_THROW_ON_ERROR);
        // Keep unknown JSON objects as objects, including empty objects in future settings.
        $preserved = json_decode($old, false, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !array_is_list($data)) {
            throw new \RuntimeException('Unsupported recipient configuration.');
        }
        $changes = [];
        if ($property === 'users') {
            if (preg_match('/\A[0-9A-Za-z]{1,64}\z/D', $UserID) !== 1) {
                throw new \RuntimeException('Enter a stable Notice user ID using letters and digits.');
            }
            $index = null;
            $ids = [];
            foreach ($data as $i => $user) {
                if (!is_array($user) || !is_string($user['id'] ?? null) || isset($ids[strtoupper($user['id'])])) {
                    throw new \RuntimeException('Unsupported recipient configuration.');
                }
                $ids[strtoupper($user['id'])] = true;
                if (strcasecmp($user['id'], $UserID) === 0) {
                    $index = $i;
                }
            }
            if ($index === null) {
                $index = count($data);
                $data[] = ['id' => $UserID, 'name' => '', 'webfront_instID' => 0, 'mail_addr' => '', 'sms_telno' => '', 'script_params' => '', 'inactive' => true, 'immobile' => false];
                $preserved[] = (object) $data[$index];
                $changes[] = ['Field' => $this->Translate('New Notice user (inactive)'), 'Before' => '', 'After' => $UserID];
            }
            foreach (['name' => $contact['displayName'], 'sms_telno' => $Phone, 'mail_addr' => $Email] as $field => $value) {
                if ($field !== 'name' && $value === '') {
                    continue;
                }
                $before = $data[$index][$field] ?? '';
                if (!is_string($before)) {
                    throw new \RuntimeException('Unsupported recipient configuration.');
                }
                if ($before !== $value) {
                    $label = match ($field) {
                        'name' => 'Name', 'sms_telno' => 'Phone number', 'mail_addr' => 'Email address'
                    };
                    $changes[] = ['Field' => $this->Translate($label), 'Before' => $before, 'After' => $value];
                    $data[$index][$field] = $value;
                    $preserved[$index]->{$field} = $value;
                }
            }
        } else {
            if ($Level < 1 || $Action < 1 || !is_array($data[$Level - 1]['actions'][$Action - 1] ?? null)) {
                throw new \RuntimeException('Select an existing notification level and action.');
            }
            $entry = &$data[$Level - 1]['actions'][$Action - 1];
            $value = match ($entry['actionType'] ?? null) {
                3       => $Email, 4 => $Phone,
                default => throw new \RuntimeException('Only SMTP and SMS recipient actions are supported.')
            };
            if ($value === '' || !is_string($entry['recipientAddress'] ?? null)) {
                throw new \RuntimeException('Select the address required by this action.');
            }
            if ($entry['recipientAddress'] !== $value) {
                $changes[] = ['Field' => $this->Translate('Recipient address') . ' (' . $Level . '/' . $Action . ')', 'Before' => $entry['recipientAddress'], 'After' => $value];
                $entry['recipientAddress'] = $value;
                $preserved[$Level - 1]->actions[$Action - 1]->recipientAddress = $value;
            }
            unset($entry);
        }
        $new = json_encode($preserved, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($new) > 1048576 || $changes === []) {
            throw new \RuntimeException('No recipient changes to apply.');
        }
        foreach ($changes as $change) {
            if (strlen($change['Before']) > 4096 || strlen($change['After']) > 4096) {
                throw new \RuntimeException('Unsupported recipient configuration.');
            }
        }
        return ['property' => $property, 'before' => $old, 'after' => $new, 'changes' => $changes];
    }

    private function recipientSource(string $id): array
    {
        $source = json_decode($this->GetContact($id), true, 64, JSON_THROW_ON_ERROR);
        if (($source['success'] ?? false) !== true || ($source['stale'] ?? true) !== false || ($source['paused'] ?? true) !== false || !is_array($source['contact'] ?? null)) {
            throw new \RuntimeException('A current, unpaused contact is required.');
        }
        return $source;
    }

    private function recipientAddressOptions(array $entries): array
    {
        $options = [['caption' => $this->Translate('Do not transfer this address'), 'value' => '']];
        foreach ($entries as $entry) {
            $options[] = ['caption' => ($entry['label'] === '' ? '' : $entry['label'] . ': ') . $entry['value'], 'value' => $entry['value']];
        }
        return $options;
    }

    private function recipientHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function recipientTransferFeedback(string $message, array $changes): void
    {
        $ready = $this->GetBuffer('RecipientTransferPending') !== '';
        $visible = ['ready' => $ready, 'message' => $this->Translate($message), 'changes' => $changes];
        $this->SetBuffer('RecipientTransferVisible', json_encode($visible, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->UpdateFormField('RecipientTransferResult', 'caption', $visible['message']);
        $this->UpdateFormField('RecipientTransferPreview', 'values', json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** Exception text is never forwarded unless it belongs to this fixed allowlist. */
    private function recipientTransferError(\Throwable $error): string
    {
        $this->DebugFlowException(__FUNCTION__, __LINE__, $error);
        $explanation = match ($error->getMessage()) {
            'Create a preview before applying recipient changes.', 'Preview expired or changed. Create a new preview.' => 'Die Vorschau fehlt, ist abgelaufen oder passt nicht mehr zu Quelle und Ziel. Eine neue Vorschau erstellen und danach bestätigen.',
            'Target is busy. Create a new preview and try again.'                                                      => 'Die Zielinstanz wird gerade bearbeitet. Später eine neue Vorschau erstellen; es wurde keine parallele Änderung erzwungen.',
            'Selected address is no longer available.', 'A current, unpaused contact is required.'                     => 'Der ausgewählte Kontakt ist nicht aktuell verfügbar oder die Quelle ist pausiert. Kontaktverzeichnis und Auswahl prüfen.',
            'Select at least one phone number or email address.', 'Select the address required by this action.'        => 'Es fehlt die für die Zielaktion benötigte Telefonnummer oder E-Mail-Auswahl. Es werden keine Empfängerwerte erraten.',
            'Select an installed recipient target.'                                                                    => 'Es ist keine unterstützte installierte Zielinstanz ausgewählt. Im Formular zuerst Mitteilungen oder Benachrichtigung auswählen.',
            'The target has unapplied changes. Apply or discard them first.'                                           => 'Die Zielinstanz hat ungespeicherte Änderungen. Diese zuerst übernehmen oder verwerfen; die Übernahme überschreibt sie nicht.',
            'Target update failed. The previous configuration was restored.'                                           => 'Die Empfängeränderung ist fehlgeschlagen. Die vorherige Zielkonfiguration wurde wiederhergestellt; keine Nachricht wurde versendet.',
            'Target update and restoration failed. Check the target configuration.'                                    => 'Sowohl Änderung als auch Wiederherstellung sind fehlgeschlagen. Die Zielkonfiguration jetzt prüfen; es wurde keine Nachricht versendet.',
            'No recipient changes to apply.'                                                                           => 'Es gibt keine Empfängeränderung zu übernehmen. Das Ziel bleibt unverändert.',
            default                                                                                                    => 'Die gewählte Zielaktion oder Empfängerkonfiguration ist nicht zulässig. Zieltyp, Benutzerkennung und SMTP-/SMS-Aktion prüfen; private Detailtexte bleiben geschützt.'
        };
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', $explanation);
        $messages = ['Create a preview before applying recipient changes.', 'Target is busy. Create a new preview and try again.', 'Selected address is no longer available.', 'Select at least one phone number or email address.', 'Unsupported recipient configuration.',
            'Enter a stable Notice user ID using letters and digits.', 'Select an existing notification level and action.', 'Only SMTP and SMS recipient actions are supported.',
            'Select the address required by this action.', 'No recipient changes to apply.', 'Preview expired or changed. Create a new preview.',
            'Select an installed recipient target.', 'The target has unapplied changes. Apply or discard them first.', 'A current, unpaused contact is required.',
            'Target update failed. The previous configuration was restored.', 'Target update and restoration failed. Check the target configuration.'];
        return in_array($error->getMessage(), $messages, true) ? $error->getMessage() : 'Recipient transfer unavailable. Check the source and target configuration.';
    }
}
