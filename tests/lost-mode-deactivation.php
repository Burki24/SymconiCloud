<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/lost-mode.php';

$check(method_exists(iCloudDevice::class, 'DeactivateLostMode'), 'Devices need a separately confirmed Lost Mode deactivation action.');
$lostAccount->ApplyPersistence($lostSaved);
IPS_ApplyChanges($lostAccountID);
IPS_DisconnectInstance($lostDeviceID);
IPS_ConnectInstance($lostDeviceID, $lostAccountID);
IPS_ApplyChanges($lostDeviceID);
$stopTransport = new AccountFixtureTransport();
$lostAccount->transport = $stopTransport;
$stop = static fn (bool $confirmed = true): bool => $lostDevice->DeactivateLostMode($confirmed);
$check(!$stop(false) && $stopTransport->requests === [], 'An unconfirmed deactivation must not reach Apple.');
$form = json_decode($lostDevice->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$panel = array_values(array_filter($form['actions'], static fn ($item): bool => ($item['name'] ?? '') === 'LostMode'))[0];
$fields = array_column($panel['items'], null, 'name');
$check($fields['DeactivateLostMode']['confirm'] !== '' && $fields['DeactivateLostMode']['onClick'] === 'ICID_DeactivateLostMode($id, true);', 'Deactivation requires its own confirmation and must not read activation inputs.');
$ids = IPS_GetChildrenIDs($lostDeviceID);
foreach ([true, false, null] as $reportedState) {
    $state = $lostSaved;
    $cache = $lostCache;
    $cache['devices'][0]['details']['lostModeEnabled'] = $reportedState;
    $state['attributes']['DeviceCache'] = json_encode($cache, JSON_THROW_ON_ERROR);
    $lostAccount->ApplyPersistence($state);
    $lostDevice->Update();
    $before = count($stopTransport->requests);
    $stopTransport->enqueue('POST', '/lostDevice?', 200, ['statusCode' => '200', 'private' => 'SYNTHETIC-STOP-SECRET']);
    $check($stop() && count($stopTransport->requests) === $before + 1, 'A confirmed stop may cancel an active or still unconfirmed Lost Mode request.');
    $request = end($stopTransport->requests);
    $check($request['body'] === ['device' => 'synthetic-device', 'lostModeEnabled' => false, 'emailUpdates' => false, 'trackingEnabled' => false, 'userText' => false], 'A stop request must contain only the bound device and fixed disable flags, never a passcode or activation fields.');
    $check(str_starts_with($request['url'], 'https://p01-fmipweb.icloud.com/fmipservice/client/web/lostDevice?'), 'Deactivation uses only the authenticated account endpoint.');
    $check(GetValue(IPS_GetObjectIDByIdent('LostModeEnabled', $lostDeviceID)) === ($reportedState === null ? -1 : (int) $reportedState), 'Acceptance must not change the reported Lost Mode state optimistically.');
    $check($lostAccount->GetPersistence()['attributes']['DeviceCache'] === $state['attributes']['DeviceCache'], 'A deactivation must not rewrite inventory.');
}
$lostAccount->ApplyPersistence($lostSaved);
$count = count($stopTransport->requests);
$lostDevice->Update();
IPS_ApplyChanges($lostDeviceID);
$lostDevice->GetConfigurationForm();
$check(count($stopTransport->requests) === $count && IPS_GetChildrenIDs($lostDeviceID) === $ids, 'Form reads, cache updates and ApplyChanges must not send a command or change variable IDs.');
$envelope = ['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => 'DeactivateLostMode', 'DeviceID' => 'synthetic-device', 'Confirmed' => true];
foreach ([['Confirmed' => false], ['Confirmed' => 1], ['DeviceID' => 'other-device'], ['PhoneNumber' => '000000'], ['NewPasscode' => '123456'], ['lostModeEnabled' => true], ['URL' => 'https://example.invalid']] as $override) {
    $reply = json_decode($lostAccount->ForwardData(json_encode(array_replace($envelope, $override), JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
    $check(!$reply['success'] && count($stopTransport->requests) === $count, 'Deactivation must reject missing confirmation, foreign targets and every extra action field.');
}
foreach ([false, null] as $capability) {
    $state = $lostSaved;
    $cache = $lostCache;
    $cache['devices'][0]['details']['lostModeCapable'] = $capability;
    $state['attributes']['DeviceCache'] = json_encode($cache, JSON_THROW_ON_ERROR);
    $lostAccount->ApplyPersistence($state);
    $check(!$stop() && count($stopTransport->requests) === $count, 'Unknown or unsupported capability must not receive a Lost Mode command.');
}
$lostAccount->ApplyPersistence($lostSaved);
foreach ([[503, []], [200, ['statusCode' => '500']], [200, ['success' => false]], [200, ['error' => 'SYNTHETIC-STOP-SECRET']]] as [$status, $body]) {
    $stopTransport->enqueue('POST', '/lostDevice?', $status, $body);
    $check(!$stop() && count($stopTransport->requests) === ++$count, 'Deactivation failures must never retry or fall back to activation.');
    $check(json_decode($lostAccount->GetDevices(), true)['success'], 'A stop error must preserve an otherwise available inventory.');
    $check(!str_contains($lostDevice->GetConfigurationForm(), 'SYNTHETIC-STOP-SECRET'), 'Stop feedback must not disclose raw Apple data.');
}
$stopTransport->enqueue('POST', '/lostDevice?', 429, [], ['retry-after' => '60']);
$check(!$stop() && count($stopTransport->requests) === ++$count, 'Deactivation respects Apple cooldown.');
$check(!$stop() && !$lostDevice->PlaySound() && count($stopTransport->requests) === $count, 'Cooldown is shared by stop and sound commands.');
$lostAccount->ApplyPersistence($lostSaved);
$stopTransport->enqueue('POST', '/lostDevice?', 401);
$check(!$stop() && count($stopTransport->requests) === ++$count, 'An expired session must not be renewed or retried by a stop command.');
$check(json_decode($lostAccount->GetPersistence()['attributes']['Session'], true)['state'] === 'expired', 'Deactivation auth failure expires the shared session.');
$check(!$stop() && count($stopTransport->requests) === $count, 'Expired sessions must not reach Apple.');
$lostAccount->ApplyPersistence($lostSaved);
IPS_SetProperty($lostAccountID, 'Enabled', false);
IPS_ApplyChanges($lostAccountID);
$check(!$stop() && count($stopTransport->requests) === $count, 'Disabled accounts must not send a stop command.');
$lostAccount->ApplyPersistence($lostSaved);
IPS_DisconnectInstance($lostDeviceID);
IPS_ConnectInstance($lostDeviceID, $otherID);
IPS_ApplyChanges($lostDeviceID);
$check(!$stop() && count($stopTransport->requests) === $count, 'Reconnecting must not reuse the former account.');
foreach ($stopTransport->requests as $request) {
    $check($request['body']['lostModeEnabled'] === false && $request['body']['trackingEnabled'] === false, 'No deactivation test may send an activation payload.');
}
ob_end_clean();
echo "Confirmed Lost Mode deactivation, fixed disable payload, cache preservation and no-retry contracts passed.\n";
