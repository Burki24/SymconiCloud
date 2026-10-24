<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account.php';

$check(method_exists(iCloudDevice::class, 'ActivateLostMode'), 'Devices need an explicitly confirmed Lost Mode action.');

/** Captures the SDK form updates absent from the official stub implementation. */
class LostModeDeviceFixture extends iCloudDevice
{
    public array $fieldUpdates = [];

    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Device/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        $this->fieldUpdates[$Field][$Parameter] = $Value;
        return true;
    }
}

$lostModules = $metadata->getValue();
$lostModules['{DEA72AD7-7877-4392-B64F-055B63D1E5C4}']['Class'] = LostModeDeviceFixture::class;
$metadata->setValue(null, $lostModules);
$lostSaved = $saved;
$lostCache = json_decode($lostSaved['attributes']['DeviceCache'], true, 512, JSON_THROW_ON_ERROR);
$lostCache['devices'][0]['details']['lostModeCapable'] = true;
$lostCache['devices'][0]['details']['lostModeEnabled'] = false;
$lostSaved['attributes']['DeviceCache'] = json_encode($lostCache, JSON_THROW_ON_ERROR);
$lostAccountID = IPS_CreateInstance($accountModule);
/** @var AccountFixtureModule $lostAccount */
$lostAccount = IPS\InstanceManager::getInstanceInterface($lostAccountID);
$lostAccount->ApplyPersistence($lostSaved);
$lostAccount->ApplyChanges();
$lostTransport = new AccountFixtureTransport();
$lostAccount->transport = $lostTransport;
$lostDeviceID = IPS_CreateInstance('{DEA72AD7-7877-4392-B64F-055B63D1E5C4}');
IPS_ConnectInstance($lostDeviceID, $lostAccountID);
IPS_SetProperty($lostDeviceID, 'DeviceID', 'synthetic-device');
IPS_ApplyChanges($lostDeviceID);
/** @var LostModeDeviceFixture $lostDevice */
$lostDevice = IPS\InstanceManager::getInstanceInterface($lostDeviceID);
$ids = IPS_GetChildrenIDs($lostDeviceID);
$phone = '+49 30 000000';
$message = 'SYNTHETIC-LOST-MESSAGE';
$passcode = '082615';
$invoke = static fn (bool $confirmed = true): bool => $lostDevice->ActivateLostMode($confirmed, $phone, $message, $passcode);
$check(!$invoke(false) && $lostTransport->requests === [], 'An unconfirmed activation must not reach Apple.');
foreach (['LostModePhone', 'LostModeMessage', 'LostModePasscode'] as $field) {
    $check(($lostDevice->fieldUpdates[$field]['value'] ?? null) === '', 'Transient input fields must be cleared by SDK updates after an action.');
}
$lostDevice->Update();
IPS_ApplyChanges($lostDeviceID);
$form = json_decode($lostDevice->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$panel = array_values(array_filter($form['actions'], static fn ($item): bool => ($item['name'] ?? '') === 'LostMode'))[0];
$fields = array_column($panel['items'], null, 'name');
$check($fields['LostModeTarget']['caption'] === 'Target device: ' . $lostCache['devices'][0]['name'], 'The form must identify the stored target device.');
$check($fields['ActivateLostMode']['confirm'] !== '' && $fields['ActivateLostMode']['onClick'] === 'ICID_ActivateLostMode($id, true, $LostModePhone, $LostModeMessage, $LostModePasscode);', 'Activation needs the documented confirmation dialog and transient form inputs.');
$check($fields['LostModePasscode']['type'] === 'PasswordTextBox', 'A new passcode must be masked and never a persistent property.');
foreach (['LostModePhone', 'LostModeMessage', 'LostModePasscode', 'Confirmed'] as $field) {
    $check(!array_key_exists($field, $lostDevice->GetPersistence()['configuration']), 'Contact information, passcodes and confirmation must not be settings.');
}
$check($lostTransport->requests === [], 'Opening, updating and applying a device must never enable Lost Mode.');
$lostTransport->enqueue('POST', '/lostDevice?', 200, ['statusCode' => '200', 'private' => 'SYNTHETIC-APPLE-SECRET']);
$check($invoke(), 'A confirmed activation for a capable cached device must be accepted.');
$check(count($lostTransport->requests) === 1 && $lostTransport->queue === [], 'One confirmation sends exactly one Lost Mode request without refresh or login.');
$request = $lostTransport->requests[0];
$check($request['body'] === ['device' => 'synthetic-device', 'text' => $message, 'userText' => true, 'ownerNbr' => $phone, 'lostModeEnabled' => true, 'trackingEnabled' => true, 'passcode' => $passcode], 'Only the configured target and bounded Lost Mode fields may reach Apple.');
$check(str_starts_with($request['url'], 'https://p01-fmipweb.icloud.com/fmipservice/client/web/lostDevice?'), 'Lost Mode uses the authenticated Find My endpoint.');
$check(GetValue(IPS_GetObjectIDByIdent('LostModeEnabled', $lostDeviceID)) === 0, 'Request acceptance must not pretend the device has activated Lost Mode.');
$lostDevice->Update();
$reported = $lostSaved;
$reportedCache = $lostCache;
$reportedCache['devices'][0]['details']['lostModeEnabled'] = true;
$reported['attributes']['DeviceCache'] = json_encode($reportedCache, JSON_THROW_ON_ERROR);
$lostAccount->ApplyPersistence($reported);
$lostDevice->Update();
$check(GetValue(IPS_GetObjectIDByIdent('LostModeEnabled', $lostDeviceID)) === 1 && count($lostTransport->requests) === 1, 'Only a subsequent account report may change the Lost Mode status, without another activation.');
$lostAccount->ApplyPersistence($lostSaved);
$lostDevice->Update();
$check($lostSaved['attributes']['DeviceCache'] === $lostAccount->GetPersistence()['attributes']['DeviceCache'], 'An action must preserve the account inventory.');
foreach ([$lostDevice->GetPersistence(), $lostAccount->GetPersistence(), json_decode($lostDevice->GetConfigurationForm(), true)] as $snapshot) {
    $json = json_encode($snapshot, JSON_THROW_ON_ERROR);
    foreach ([$phone, $message, $passcode, 'SYNTHETIC-APPLE-SECRET'] as $secret) {
        $check(!str_contains($json, $secret), 'Contact data, passcode and raw responses must never enter persistence or returned forms.');
    }
}
$count = count($lostTransport->requests);
foreach ([['bad phone', $message, ''], [$phone, str_repeat('x', 161), ''], [$phone, "bad\nmessage", ''], [$phone, $message, '123'], [$phone, $message, '1234567'], [$phone, $message, '12x4'], [$phone, "\xFF", '']] as [$number, $text, $code]) {
    $check(!$lostDevice->ActivateLostMode(true, $number, $text, $code) && count($lostTransport->requests) === $count, 'Invalid Lost Mode input must not be sent.');
}
foreach ([['', '', ''], ['', str_repeat('ä', 160), '0123']] as [$number, $text, $code]) {
    $lostTransport->enqueue('POST', '/lostDevice?', 200, ['statusCode' => '200']);
    $check($lostDevice->ActivateLostMode(true, $number, $text, $code) && count($lostTransport->requests) === ++$count, 'Optional fields, Unicode character limits and leading-zero passcodes must be preserved.');
    $check(end($lostTransport->requests)['body']['passcode'] === $code, 'An empty or leading-zero passcode must not be changed or invented.');
}
$envelope = ['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => 'ActivateLostMode', 'DeviceID' => 'synthetic-device', 'Confirmed' => true, 'PhoneNumber' => $phone, 'Message' => $message, 'NewPasscode' => ''];
foreach ([['Confirmed' => false], ['Confirmed' => 1], ['DeviceID' => 'another-account-device'], ['NewPasscode' => []], ['URL' => 'https://example.invalid'], ['Message' => str_repeat('x', 161)]] as $override) {
    $reply = json_decode($lostAccount->ForwardData(json_encode(array_replace($envelope, $override), JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
    $check(!$reply['success'] && count($lostTransport->requests) === $count, 'Direct child requests must enforce confirmation, identity, types and the input allowlist.');
}
foreach ([false, null, true] as $capability) {
    $state = $lostSaved;
    $cache = $lostCache;
    $cache['devices'][0]['details']['lostModeCapable'] = $capability;
    $cache['devices'][0]['details']['lostModeEnabled'] = $capability === true;
    $state['attributes']['DeviceCache'] = json_encode($cache, JSON_THROW_ON_ERROR);
    $lostAccount->ApplyPersistence($state);
    $check(!$invoke() && count($lostTransport->requests) === $count, 'Unsupported, unknown-capability and already-lost devices must not receive another activation.');
}
$lostAccount->ApplyPersistence($lostSaved);
foreach ([[503, []], [200, ['statusCode' => '500']], [200, ['success' => false]]] as [$status, $body]) {
    $lostTransport->enqueue('POST', '/lostDevice?', $status, $body);
    $check(!$invoke() && count($lostTransport->requests) === ++$count, 'A rejected activation must fail without retries.');
    $check(json_decode($lostAccount->GetDevices(), true)['success'], 'Lost Mode service errors must preserve otherwise usable cached data.');
}
$lostTransport->enqueue('POST', '/lostDevice?', 429, [], ['retry-after' => '60']);
$check(!$invoke() && count($lostTransport->requests) === ++$count, 'Lost Mode must respect Apple rate limits.');
$check(!$invoke() && count($lostTransport->requests) === $count, 'The account cooldown must suppress further device actions.');
$check(!$lostDevice->PlaySound() && count($lostTransport->requests) === $count, 'The same cooldown also applies to sound actions.');
$lostAccount->ApplyPersistence($lostSaved);
$lostTransport->enqueue('POST', '/lostDevice?', 401);
$check(!$invoke() && count($lostTransport->requests) === ++$count, 'Expired sessions must not be renewed or automatically retried during activation.');
$check(json_decode($lostAccount->GetPersistence()['attributes']['Session'], true)['state'] === 'expired', 'Authentication failure expires the shared session.');
$check(!$invoke() && count($lostTransport->requests) === $count, 'Expired sessions must not send another activation.');
$lostAccount->ApplyPersistence($lostSaved);
IPS_SetProperty($lostAccountID, 'Enabled', false);
IPS_ApplyChanges($lostAccountID);
$check(!$invoke() && count($lostTransport->requests) === $count, 'Disabled accounts must not activate Lost Mode.');
$lostAccount->ApplyPersistence($lostSaved);
$changedIdentity = $lostSaved;
$changedIdentity['configuration']['Username'] = 'different-fixture@example.invalid';
$lostAccount->ApplyPersistence($changedIdentity);
$check(!$invoke() && count($lostTransport->requests) === $count, 'A credential fingerprint mismatch must not reuse a previous authenticated session.');
$lostAccount->ApplyPersistence($lostSaved);
$otherID = IPS_CreateInstance($accountModule);
IPS_DisconnectInstance($lostDeviceID);
IPS_ConnectInstance($lostDeviceID, $otherID);
IPS_ApplyChanges($lostDeviceID);
$check(!$invoke() && count($lostTransport->requests) === $count, 'Reconnection must never use the old account for an activation.');
$check(IPS_GetChildrenIDs($lostDeviceID) === $ids, 'Lost Mode must not change existing variable IDs.');
$check($lostDevice->GetPersistence()['attributes']['LostModeResult'] !== '', 'A failed action must expose sanitized feedback.');
ob_end_clean();
echo "Confirmed Lost Mode, transient inputs, account isolation, truthful status and no-retry contracts passed.\n";
