<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account.php';

$soundAccountID = IPS_CreateInstance($accountModule);
/** @var AccountFixtureModule $soundAccount */
$soundAccount = IPS\InstanceManager::getInstanceInterface($soundAccountID);
$soundAccount->ApplyPersistence($saved);
$soundAccount->ApplyChanges();
$soundTransport = new AccountFixtureTransport();
$soundAccount->transport = $soundTransport;
$deviceID = IPS_CreateInstance('{DEA72AD7-7877-4392-B64F-055B63D1E5C4}');
IPS_ConnectInstance($deviceID, $soundAccountID);
IPS_SetProperty($deviceID, 'DeviceID', 'synthetic-device');
IPS_ApplyChanges($deviceID);
/** @var iCloudDevice $device */
$device = IPS\InstanceManager::getInstanceInterface($deviceID);
$check(method_exists($device, 'PlaySound'), 'Device instances must expose an explicit PlaySound action.');
$ids = IPS_GetChildrenIDs($deviceID);
$cache = $soundAccount->GetPersistence()['attributes']['DeviceCache'];
$state = IPS_GetInstance($deviceID)['InstanceStatus'];
$form = json_decode($device->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$buttons = array_column($form['actions'], null, 'name');
$check(isset($buttons['PlaySound'], $buttons['SoundResult']) && $buttons['PlaySound']['onClick'] === "IPS_RequestAction(\$id, 'PlaySound', '');", 'The form button must route the explicit device action through RequestAction.');
$check($soundTransport->requests === [], 'Creation, configuration and form reads must never play a sound.');

$soundTransport->enqueue('POST', '/playSound?', 200, ['statusCode' => '200', 'private' => 'SYNTHETIC-SOUND-SECRET']);
$check(ICID_PlaySound($deviceID), 'An accepted Apple sound request must return true.');
$request = $soundTransport->requests[0];
$check(count($soundTransport->requests) === 1 && $soundTransport->queue === [], 'One explicit action must issue exactly one sound POST without a refresh or password login.');
$check($request['body'] === ['device' => 'synthetic-device', 'subject' => 'Find My iPhone Alert', 'clientContext' => ['fmly' => true]], 'Only the configured device and the fixed sound action may be sent to Apple.');
$check(str_starts_with($request['url'], 'https://p01-fmipweb.icloud.com/fmipservice/client/web/playSound?'), 'Sound requests must stay on the authenticated account endpoint.');
$check($cache === $soundAccount->GetPersistence()['attributes']['DeviceCache'] && $ids === IPS_GetChildrenIDs($deviceID) && $state === IPS_GetInstance($deviceID)['InstanceStatus'], 'Sound commands must preserve cached data, variable IDs and device state.');
$form = $device->GetConfigurationForm();
foreach (['SYNTHETIC-SOUND-SECRET', 'SYNTHETIC-TOKEN-SECRET', 'SYNTHETIC-COOKIE-SECRET'] as $secret) {
    $check(!str_contains($form, $secret), 'Action feedback must not disclose raw Apple responses or credentials.');
}
$check(str_contains($form, 'accepted') && !str_contains($form, 'Sound is playing'), 'Feedback must report request acceptance, not claim audible playback.');
$device->Update();
$check(count($soundTransport->requests) === 1, 'Subsequent cache updates must not repeat sound playback.');

$soundTransport->enqueue('POST', '/playSound?', 503, ['private' => 'SYNTHETIC-SOUND-SECRET']);
$check(!$device->PlaySound() && count($soundTransport->requests) === 2, 'HTTP errors must fail without an automatic retry.');
$check(json_decode($soundAccount->GetDevices(), true)['success'], 'A sound service error must not invalidate otherwise usable location data.');
$soundTransport->enqueue('POST', '/playSound?', 200, ['statusCode' => '500']);
$check(!$device->PlaySound() && count($soundTransport->requests) === 3, 'An Apple command error inside HTTP 200 must not be reported as accepted.');

$count = count($soundTransport->requests);
foreach ([['Command' => 'PlaySound', 'DeviceID' => 'other-device'], ['Command' => 'PlaySound'], ['Command' => 'PlaySound', 'DeviceID' => []], ['Command' => 'PlaySound', 'DeviceID' => 'synthetic-device', 'URL' => 'https://example.invalid']] as $payload) {
    $reply = json_decode($soundAccount->ForwardData(json_encode(['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}'] + $payload, JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
    $check(!$reply['success'] && count($soundTransport->requests) === $count, 'Invalid devices or extra request parameters must never reach Apple.');
}
IPS_SetProperty($deviceID, 'DeviceID', 'other-device');
IPS_ApplyChanges($deviceID);
$check(!$device->PlaySound() && count($soundTransport->requests) === $count, 'Changing the device identity must not ring the previously cached device.');
IPS_SetProperty($deviceID, 'DeviceID', 'synthetic-device');
IPS_ApplyChanges($deviceID);
IPS_SetProperty($soundAccountID, 'Enabled', false);
IPS_ApplyChanges($soundAccountID);
$check(!$device->PlaySound() && count($soundTransport->requests) === $count, 'Disabled accounts must not send sound commands.');
$soundAccount->ApplyPersistence($saved);
$soundAccount->ApplyChanges();

$soundTransport->enqueue('POST', '/playSound?', 429, [], ['retry-after' => '1']);
$check(!$device->PlaySound(), 'Apple rate limits must be reported as a failed sound action.');
$count = count($soundTransport->requests);
$check(!$device->PlaySound() && count($soundTransport->requests) === $count, 'The shared account cooldown must prevent further sound requests.');
$soundAccount->ApplyPersistence($saved);
$soundAccount->ApplyChanges();
$soundTransport->enqueue('POST', '/playSound?', 401);
$check(!$device->PlaySound() && count($soundTransport->requests) === $count + 1, 'An expired sound session must fail without reauthentication or resending the command.');
$check(json_decode($soundAccount->GetPersistence()['attributes']['Session'], true)['state'] === 'expired', 'Sound authentication failures must persist the expired session.');
$check($cache === $soundAccount->GetPersistence()['attributes']['DeviceCache'], 'Authentication failures retain the last cached device inventory.');
$otherAccountID = IPS_CreateInstance($accountModule);
/** @var AccountFixtureModule $otherAccount */
$otherAccount = IPS\InstanceManager::getInstanceInterface($otherAccountID);
$otherPersistence = $saved;
$otherPersistence['attributes']['DeviceCache'] = '{"devices":[],"updatedAt":1700000100}';
$otherAccount->ApplyPersistence($otherPersistence);
$otherAccount->ApplyChanges();
$otherAccount->transport = new AccountFixtureTransport();
IPS_DisconnectInstance($deviceID);
IPS_ConnectInstance($deviceID, $otherAccountID);
IPS_ApplyChanges($deviceID);
$check(!$device->PlaySound() && $otherAccount->transport->requests === [] && count($soundTransport->requests) === $count + 1, 'Reconnecting to another account must never send to the previous account or accept its device cache.');
$rejected = false;
try {
    $device->RequestAction('PlaySound', ['DeviceID' => 'other-device']);
} catch (InvalidArgumentException) {
    $rejected = true;
}
$check($rejected && $otherAccount->transport->requests === [], 'The device action must not accept an alternative target payload.');
ob_end_clean();
echo "Explicit device sound action, account isolation, error handling and no-retry contracts passed.\n";
