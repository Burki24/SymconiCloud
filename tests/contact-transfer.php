<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/contacts.php';
ob_end_clean();

/** Native stub storage, with no delivery methods available on either fixture. */
class RecipientTargetFixture extends IPSModuleStrict
{
    public int $failures = 0;
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString('users', '[]');
        $this->RegisterPropertyString('NotificationLevels', '[]');
        $this->RegisterPropertyString('Unrelated', 'PRIVATE-TARGET-SETTING');
    }
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        if ($this->failures > 0) {
            $this->failures--;
            throw new RuntimeException('PRIVATE-TARGET-ERROR');
        }
    }
}
class RecipientSourceFixture extends iCloudContacts
{
    public int $now = 0;
    protected function RecipientTransferTime(): int
    {
        return $this->now ?: time();
    }
    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Contacts/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
$noticeModule = '{4CF21C1E-B0F8-5535-8B5D-01ADDDB5DFD7}';
$notificationModule = '{57677DB8-4C2A-4673-A267-B083A0755CDE}';
$contactModule = '{D9D8872B-3323-41AC-BB8F-D2756D5694E1}';
$meta = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $meta->getValue();
foreach ([$noticeModule, $notificationModule] as $module) {
    $modules[$module] = array_replace($modules[$contactModule], ['ModuleID' => $module, 'ModuleName' => 'RecipientTargetFixture', 'Class' => RecipientTargetFixture::class, 'ParentRequirements' => [], 'Implemented' => []]);
}
$modules[$contactModule]['Class'] = RecipientSourceFixture::class;
$meta->setValue(null, $modules);
$a = $directoryAccount();
$enqueueContacts($a, [$row]);
$check($a->RefreshContacts(), 'Transfer source must have a current cache.');
$bridgeID = IPS_CreateInstance($contactModule);
IPS_ConnectInstance($bridgeID, $idOf($a));
IPS_ApplyChanges($bridgeID);
$bridge = IPS\InstanceManager::getInstanceInterface($bridgeID);
$phone = $row['phones'][0]['field'];
$email = $row['emailAddresses'][0]['field'];
$noticeID = IPS_CreateInstance($noticeModule);
$notificationID = IPS_CreateInstance($notificationModule);
$existingUser = ['id' => 'KEEP', 'name' => 'Existing person', 'mail_addr' => 'old@example.invalid', 'sms_telno' => '0100000000', 'webfront_instID' => 12345, 'inactive' => false, 'immobile' => true, 'script_params' => '{"retained":true}', 'FutureField' => 'preserve'];
IPS_SetProperty($noticeID, 'users', json_encode([$existingUser], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($noticeID);
$before = IPS_GetConfiguration($noticeID);
$preview = static fn ($target, $user = 'NEW', $selectedPhone = null, $selectedEmail = null, $level = 1, $action = 1) => $bridge->PreviewRecipientTransfer($target, 'synthetic-contact-1', $selectedPhone ?? $phone, $selectedEmail ?? $email, $user, $level, $action);
$check($preview($noticeID), 'Notice insertion must have a preview.');
$check(IPS_GetConfiguration($noticeID) === $before && !IPS_HasChanges($noticeID), 'Preview must not even stage target changes.');
$visible = json_decode($bridge->GetRecipientTransferPreview(), true);
$check($visible['ready'] && count($visible['changes']) === 4 && !str_contains($bridge->GetRecipientTransferPreview(), 'PRIVATE-TARGET'), 'Preview must contain only changed recipient fields.');
$check(!$bridge->ApplyRecipientTransfer(false) && IPS_GetConfiguration($noticeID) === $before, 'Cancellation must leave the target byte-identical.');
$check($bridge->ApplyRecipientTransfer(true), 'A confirmed preview must apply.');
$users = json_decode(IPS_GetProperty($noticeID, 'users'), true);
$check($users[0] === $existingUser && $users[1]['id'] === 'NEW' && $users[1]['inactive'] === true && $users[1]['sms_telno'] === $phone && $users[1]['mail_addr'] === $email, 'New users must be merged inactive without touching existing entries.');
$check(IPS_GetProperty($noticeID, 'Unrelated') === 'PRIVATE-TARGET-SETTING' && !$bridge->ApplyRecipientTransfer(true), 'Other settings survive and previews are single-use.');
$check($preview($noticeID, 'keep', '', $email) && $bridge->ApplyRecipientTransfer(true), 'Existing Notice IDs must match case-insensitively.');
$users = json_decode(IPS_GetProperty($noticeID, 'users'), true);
$expected = array_replace($existingUser, ['name' => 'Alex Example', 'mail_addr' => $email]);
$check($users[0] === $expected, 'Existing IDs, unselected fields, flags, assignments and unknown fields must survive.');
$objectUsers = json_decode(IPS_GetProperty($noticeID, 'users'));
$objectUsers[0]->FutureObject = (object) [];
IPS_SetProperty($noticeID, 'users', json_encode($objectUsers, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($noticeID);
$check($preview($noticeID, 'OBJECT') && $bridge->ApplyRecipientTransfer(true)
    && str_contains(IPS_GetProperty($noticeID, 'users'), '"FutureObject":{}'), 'Unknown empty JSON objects must retain their type.');
$action = ['actionType' => 3, 'recipientObjectID' => 23456, 'recipientAddress' => 'old@example.invalid', 'title' => 'Keep title', 'message' => 'Keep message', 'messageVariable' => 34567, 'Extra' => ['keep' => true]];
$levels = [['level' => 'First', 'active' => false, 'duration' => 60, 'actions' => [$action, array_replace($action, ['actionType' => 4, 'recipientAddress' => '0100000000'])]], ['level' => 'Second', 'active' => true, 'duration' => 120, 'actions' => [$action]]];
IPS_SetProperty($notificationID, 'NotificationLevels', json_encode($levels, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($notificationID);
$before = IPS_GetConfiguration($notificationID);
$check($preview($notificationID) && IPS_GetConfiguration($notificationID) === $before && $bridge->ApplyRecipientTransfer(true), 'SMTP transfer needs a non-mutating preview.');
$expected = $levels;
$expected[0]['actions'][0]['recipientAddress'] = $email;
$check(json_decode(IPS_GetProperty($notificationID, 'NotificationLevels'), true) === $expected, 'Only the selected action address may change.');
$check($preview($notificationID, '', $phone, '', 1, 2) && $bridge->ApplyRecipientTransfer(true), 'SMS must use the selected phone.');
$expected[0]['actions'][1]['recipientAddress'] = $phone;
$check(json_decode(IPS_GetProperty($notificationID, 'NotificationLevels'), true) === $expected, 'SMS must preserve unrelated actions.');
$objectLevels = json_decode(IPS_GetProperty($notificationID, 'NotificationLevels'));
$objectLevels[0]->actions[0]->FutureObject = (object) [];
IPS_SetProperty($notificationID, 'NotificationLevels', json_encode($objectLevels, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($notificationID);
$check($preview($notificationID, '', '', $email, 2, 1) && $bridge->ApplyRecipientTransfer(true)
    && str_contains(IPS_GetProperty($notificationID, 'NotificationLevels'), '"FutureObject":{}'), 'Notification settings must preserve unknown JSON object types.');
$check(!$preview($notificationID, '', '', $email, 1, 2) && !$preview($notificationID, '', $phone, $email, 99, 1), 'Missing addresses and action positions must fail.');
$check(!$preview($noticeID, 'INVALID-ID') && !$preview($noticeID, 'NEW', 'NOT-IN-CONTACT') && !$preview(0), 'Invalid identity, destination and target must fail.');
$check($preview($noticeID, 'SAFE'), 'Conflict seed failed.');
IPS_SetProperty($noticeID, 'Unrelated', 'A deliberate concurrent edit');
$check(!$bridge->ApplyRecipientTransfer(true) && IPS_HasChanges($noticeID), 'Unapplied target edits must not be applied or overwritten.');
IPS_ResetChanges($noticeID);
$check($preview($noticeID, 'SAFE'), 'Applied conflict seed failed.');
IPS_SetProperty($noticeID, 'Unrelated', 'Applied concurrent edit');
IPS_ApplyChanges($noticeID);
$check(!$bridge->ApplyRecipientTransfer(true) && IPS_GetProperty($noticeID, 'Unrelated') === 'Applied concurrent edit', 'Applied target edits invalidate previews.');
$check($preview($noticeID, 'SAFE'), 'Source conflict seed failed.');
$cacheBefore = $attrs($a)['ContactsCache'];
$changed = $row;
$changed['firstName'] = 'Changed after preview';
$setAttribute($a, 'ContactsCache', json_encode(['contacts' => \SymconiCloud\ContactsDirectory::normalize([$changed]), 'updatedAt' => $a->clock], JSON_THROW_ON_ERROR));
$check(!$bridge->ApplyRecipientTransfer(true), 'Changed source contacts invalidate previews.');
$setAttribute($a, 'ContactsCache', $cacheBefore);
$bridge->now = time();
$check($preview($noticeID, 'SAFE'), 'Expiry seed failed.');
$bridge->now += 301;
$check(!$bridge->ApplyRecipientTransfer(true), 'Expired previews must fail.');
$bridge->now = 0;
$check($preview($noticeID, 'SAFE'), 'Pause seed failed.');
IPS_SetProperty($idOf($a), 'PauseRequests', true);
IPS_ApplyChanges($idOf($a));
$check(!$bridge->ApplyRecipientTransfer(true) && !$preview($noticeID, 'SAFE'), 'Paused or stale source data must not transfer.');
IPS_SetProperty($idOf($a), 'PauseRequests', false);
IPS_ApplyChanges($idOf($a));
$check($preview($noticeID, 'SAFE'), 'Input seed failed.');
$bridge->InvalidateRecipientTransfer();
$check(!$bridge->ApplyRecipientTransfer(true), 'Input changes invalidate the pending plan.');
$before = IPS_GetConfiguration($noticeID);
$check($preview($noticeID, 'ROLLBACK'), 'Rollback seed failed.');
$target = IPS\InstanceManager::getInstanceInterface($noticeID);
$target->failures = 1;
$check(!$bridge->ApplyRecipientTransfer(true) && IPS_GetConfiguration($noticeID) === $before && !IPS_HasChanges($noticeID), 'An ApplyChanges failure must restore the previous property.');
$check(!str_contains($bridge->GetRecipientTransferPreview(), 'PRIVATE-TARGET-ERROR'), 'Target exceptions must stay private.');
$form = $bridge->GetConfigurationForm();
$check(str_contains($form, 'Apply exactly the previewed recipient changes?') && !str_contains($form, 'PRIVATE-TARGET-SETTING'), 'Confirmation must be explicit and target settings private.');
$check(count($a->transport->requests) === 2, 'No bridge path may contact Apple.');
echo "Both recipient adapters, preview/confirmation, preservation, conflicts, pause, expiry and rollback passed without delivery methods.\n";
