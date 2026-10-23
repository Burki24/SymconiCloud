<?php

declare(strict_types=1);

require_once __DIR__ . '/apple-client.php';
require_once __DIR__ . '/modules.php';

$flags = [
    'LowPowerMode'     => 'lowPowerMode', 'LocationEnabled' => 'locationEnabled',
    'LocationCapable'  => 'locationCapable', 'IsLocating' => 'isLocating',
    'LostModeEnabled'  => 'lostModeEnabled', 'LostModeCapable' => 'lostModeCapable',
    'ActivationLocked' => 'activationLocked', 'FamilyShared' => 'fmlyShare'
];
$raw = [
    'id'           => 'synthetic-device', 'name' => 'Synthetic phone', 'rawDeviceModel' => 'SyntheticPhone1,2',
    'deviceStatus' => '200', 'privateUnexpectedField' => 'must not escape',
    'location'     => ['latitude' => 51.25, 'longitude' => 7.5, 'timeStamp' => 1700000000000,
        'isInaccurate'            => false, 'locationFinished' => true]
];
foreach (array_values($flags) as $offset => $key) {
    $raw[$key] = $offset % 2 === 0;
}
$normalize = static function (array $device): array
{
    $transport = new ScriptedAppleTransport([['POST', '/refreshClient?', 200, ['content' => [$device]]]]);
    $client = new SymconiCloud\AppleClient($transport, [
        'state'  => 'authenticated', 'dsid' => 'synthetic',
        'findme' => 'https://p01-fmipweb.icloud.com', 'headers' => []
    ]);
    return $client->devices()[0];
};
$normalized = $normalize($raw);
check(($normalized['modelIdentifier'] ?? null) === 'SyntheticPhone1,2', 'Technical model must reach normalized inventory.');
foreach ($flags as $ident => $key) {
    check(($normalized['details'][$key] ?? null) === $raw[$key], 'Normalize explicit true and false: ' . $key);
}
check($normalized['location']['isInaccurate'] === false && $normalized['location']['finished'] === true, 'Location details must preserve false/true.');
check(!isset($normalized['privateUnexpectedField']), 'Do not expose unapproved raw fields.');
$absent = $normalize(['id' => 'synthetic-device']);
foreach ($flags as $key) {
    $check(array_key_exists($key, $absent['details']) && $absent['details'][$key] === null, 'Absent raw field must remain explicitly unknown.');
}

$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$normalized];
$detailDevice = $createDevice($firstAccount);
foreach ($flags as $ident => $key) {
    $check($value($detailDevice, $ident) === (int) $raw[$key], 'Read-only device flag: ' . $ident);
}
$check($value($detailDevice, 'LocationIsInaccurate') === 0 && $value($detailDevice, 'LocationFinished') === 1, 'Location flag variables.');
$check($value($detailDevice, 'ModelIdentifier') === 'SyntheticPhone1,2', 'Technical model variable.');
$id = IPS_GetObjectIDByIdent('LowPowerMode', $detailDevice);
$presentation = IPS_GetVariablePresentation($id);
$check($presentation['PRESENTATION'] === VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'Statuses must be read-only value presentations.');
$check(!isset($presentation['OPTIONS']) && ($presentation['INTERVALS_ACTIVE'] ?? false), 'Integer variables require native intervals, not Boolean/String OPTIONS.');
$intervals = json_decode($presentation['INTERVALS'], true, 512, JSON_THROW_ON_ERROR);
$check(array_column($intervals, 'IntervalMinValue') === [-1, 0, 1] && array_column($intervals, 'IntervalMaxValue') === [-1, 0, 1], 'Unknown must be distinct from false and true.');
$check(array_column($intervals, 'ConstantValue') === ['Not available', 'No', 'Yes'], 'All three states need visible text captions.');
$check(array_column($intervals, 'ConstantActive') === [true, true, true], 'Constant captions must be enabled.');
$check(IPS_GetVariable($id)['VariableType'] === VARIABLETYPE_INTEGER && !HasAction($id), 'Optional state must be integer without action.');
$ids = IPS_GetChildrenIDs($detailDevice);
IPS_ApplyChanges($detailDevice);
$check(IPS_GetChildrenIDs($detailDevice) === $ids && IPS_GetObjectIDByIdent('LowPowerMode', $detailDevice) === $id, 'ApplyChanges must retain variable identities.');

// Missing, null, strings and numeric sentinels must never become a misleading No/Yes.
foreach ([null, 'false', 'true', 0, 1, 2, [], new stdClass()] as $invalid) {
    $bad = $raw;
    foreach ($flags as $key) {
        $bad[$key] = $invalid;
    }
    $bad['location']['isInaccurate'] = $invalid;
    $bad['location']['locationFinished'] = $invalid;
    $bad['rawDeviceModel'] = is_string($invalid) ? '' : $invalid;
    $missing = $normalize($bad);
    foreach ($flags as $key) {
        $check($missing['details'][$key] === null, 'Invalid boolean must normalize to null.');
    }
    $check($missing['modelIdentifier'] === null, 'Invalid model identifier must normalize to null.');
    $firstBoundary->result['devices'] = [$missing];
    $firstBoundary->broadcast();
    foreach (array_merge(array_keys($flags), ['LocationIsInaccurate', 'LocationFinished']) as $ident) {
        $check($value($detailDevice, $ident) === -1, 'Unavailable state must replace last value: ' . $ident);
    }
    $check($value($detailDevice, 'ModelIdentifier') === 'Not available', 'Missing model identifier must be explicit.');
}
foreach (['', "bad\nmodel", str_repeat('a', 129), "invalid\xFF"] as $invalidModel) {
    $check($normalize(array_replace($raw, ['rawDeviceModel' => $invalidModel]))['modelIdentifier'] === null, 'Reject invalid model text.');
}

// Legacy caches, lost positions, expired accounts, removed devices and identity changes.
$legacy = $syntheticDevice;
$firstBoundary->result['devices'] = [$legacy];
$firstBoundary->broadcast();
$check($value($detailDevice, 'LowPowerMode') === -1 && $value($detailDevice, 'LocationFinished') === -1, 'Legacy cache must be compatible and unknown.');
$firstBoundary->result['devices'] = [array_replace($normalized, ['location' => null])];
$firstBoundary->broadcast();
$check($value($detailDevice, 'LowPowerMode') === 1 && $value($detailDevice, 'LocationFinished') === -1, 'Missing position must not suppress unrelated flags.');
$invalidPosition = $normalized;
$invalidPosition['location']['latitude'] = 100;
$firstBoundary->result['devices'] = [$invalidPosition];
$firstBoundary->broadcast();
$check($value($detailDevice, 'LocationFinished') === -1 && $value($detailDevice, 'LocationIsInaccurate') === -1, 'Invalid cached coordinates must not advertise location flags.');
$firstBoundary->result['devices'] = [array_replace($normalized, ['details' => ['lowPowerMode' => 'false']])];
$firstBoundary->broadcast();
$check($value($detailDevice, 'LowPowerMode') === -1, 'Child must reject malformed cached boolean values independently.');
$firstBoundary->result['devices'] = [$normalized];
$firstBoundary->broadcast();
$check($value($detailDevice, 'LowPowerMode') === 1, 'A later known value must recover from unknown.');
foreach (['expired', 'removed', 'malformed', 'identity'] as $failure) {
    $firstBoundary->result = $envelope;
    $firstBoundary->result['devices'] = [$normalized];
    $firstBoundary->broadcast();
    if ($failure === 'expired') {
        $firstBoundary->result['state'] = 'expired';
        $firstBoundary->broadcast();
    } elseif ($failure === 'removed') {
        $firstBoundary->result['devices'] = [];
        $firstBoundary->broadcast();
    } elseif ($failure === 'malformed') {
        IPS\InstanceManager::getInstanceInterface($detailDevice)->ReceiveData('{bad');
    } else {
        IPS_SetProperty($detailDevice, 'DeviceID', 'another-synthetic-device');
        IPS_ApplyChanges($detailDevice);
    }
    foreach (array_merge(array_keys($flags), ['LocationIsInaccurate', 'LocationFinished']) as $ident) {
        $check($value($detailDevice, $ident) === -1, 'Unavailable lifecycle must clear optional status: ' . $failure);
    }
    $check($value($detailDevice, 'ModelIdentifier') === 'Not available', 'Unavailable lifecycle must clear model.');
}
$freshDevice = IPS_CreateInstance($deviceModule);
IPS_ApplyChanges($freshDevice);
foreach (array_merge(array_keys($flags), ['LocationIsInaccurate', 'LocationFinished']) as $ident) {
    $check($value($freshDevice, $ident) === -1, 'A new unconfigured instance must start unknown.');
}
$check($value($freshDevice, 'ModelIdentifier') === 'Not available', 'New instance must not show a blank or retained model.');
echo "Optional device details, tri-state availability and lifecycle contracts passed.\n";
