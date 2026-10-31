<?php

declare(strict_types=1);

require_once __DIR__ . '/presence.php';

$zones = [
    ['id' => 'home', 'name' => 'Synthetic home', 'latitude' => 0, 'longitude' => 0, 'radius' => 100, 'reference' => true],
    ['id' => 'work', 'name' => 'Synthetic work', 'latitude' => 0, 'longitude' => 0.01, 'radius' => 150, 'reference' => false]
];
$makeDevice = static fn (string $key, float $longitude, int $timestamp = 1700000000, float $accuracy = 5): array => [
    'id'       => $key, 'name' => 'Synthetic ' . $key,
    'location' => ['latitude' => 0, 'longitude' => $longitude, 'horizontalAccuracy' => $accuracy, 'timestamp' => $timestamp, 'isOld' => false]
];
$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$makeDevice('alpha', 0), $makeDevice('beta', 0.002)];
$firstBoundary->commands = [];
$groupID = IPS_CreateInstance($presenceModule);
$group = IPS\InstanceManager::getInstanceInterface($groupID);
IPS_ConnectInstance($groupID, $firstAccount);
$selection = [['deviceID' => 'alpha', 'name' => 'First'], ['deviceID' => 'beta', 'name' => 'Second']];
IPS_SetProperty($groupID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_SetProperty($groupID, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($groupID);
$member = static fn (string $device, string $field): mixed => $value($groupID, $group->GetDeviceVariableIdent($device, $field));
$check($firstBoundary->commands === ['GetDevices'], 'A group update must read its account cache exactly once.');
$check($value($groupID, 'Presence') === 1 && $member('alpha', 'Presence') === 1 && $member('beta', 'Presence') === 0, 'One present device must establish overall presence.');
$check($value($groupID, 'SelectedCount') === 2 && $value($groupID, 'PresentCount') === 1 && $value($groupID, 'AbsentCount') === 1 && $value($groupID, 'UnknownCount') === 0, 'Aggregate counters must reflect independent members.');
$check($member('alpha', 'StayDuration') === 100 && $member('beta', 'StayDuration') === 0, 'Members must retain independent stay durations.');
$alphaID = IPS_GetObjectIDByIdent($group->GetDeviceVariableIdent('alpha', 'Presence'), $groupID);
$betaID = IPS_GetObjectIDByIdent($group->GetDeviceVariableIdent('beta', 'Presence'), $groupID);

$firstBoundary->result['devices'][1]['location']['horizontalAccuracy'] = 1000;
$firstBoundary->broadcast();
$check($member('beta', 'Presence') === -1 && $member('alpha', 'Presence') === 1 && $value($groupID, 'Presence') === 1, 'Present must win over an unknown member, without state leakage.');
$check($value($groupID, 'UnknownCount') === 1 && !$member('beta', 'DistanceAvailable'), 'Unknown members must have explicit counts and unavailable distances.');
$group->clock = 1700000110;
$firstBoundary->result['devices'][0]['location'] = $makeDevice('alpha', 0.002, $group->clock)['location'];
$firstBoundary->broadcast();
$check($member('alpha', 'TransitionPending') && $value($groupID, 'Presence') === 1, 'Unconfirmed exit must not remove overall presence.');
$check($value($groupID, 'PendingCount') === 1, 'The group must count independently pending members.');
$group->clock += 31;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $group->clock;
$firstBoundary->broadcast();
$check($member('alpha', 'Presence') === 0 && $value($groupID, 'Presence') === -1, 'Absent plus unknown must yield unknown overall presence.');
$check($value($groupID, 'PendingCount') === 0 && $member('alpha', 'StateSince') === $group->clock, 'A confirmed exit clears the pending count and records its state start.');
$firstBoundary->result['devices'][1]['location'] = $makeDevice('beta', 0.002, $group->clock)['location'];
$firstBoundary->broadcast();
$check($value($groupID, 'Presence') === 0 && $value($groupID, 'AbsentCount') === 2, 'Overall absence requires every member to be reliably absent.');

$group->clock += 60;
$firstBoundary->result['devices'][1]['location'] = $makeDevice('beta', 0, $group->clock)['location'];
$firstBoundary->broadcast();
$check($member('beta', 'TransitionPending') && !$member('alpha', 'TransitionPending'), 'A member candidate must not affect another member.');
$check($value($groupID, 'PendingCount') === 1, 'Only the independently changing member is counted as pending.');
$group->clock += 31;
$firstBoundary->result['devices'][1]['location']['timestamp'] = $group->clock;
$firstBoundary->broadcast();
$check($member('beta', 'Presence') === 1 && $member('beta', 'StayDuration') === 0 && $member('alpha', 'StayDuration') === 0, 'A confirmed entry starts only that member stay.');
$group->clock += 20;
ICPR_Update($groupID);
$check($member('beta', 'StayDuration') === 20 && $value($groupID, 'Presence') === 1, 'Local cache evaluation advances the active member stay.');

// Reorder, add and remove devices without resetting unchanged members.
IPS_SetProperty($groupID, 'Devices', json_encode(array_reverse($selection), JSON_THROW_ON_ERROR));
IPS_ApplyChanges($groupID);
$check($member('beta', 'StayDuration') === 20 && IPS_GetObjectIDByIdent($group->GetDeviceVariableIdent('alpha', 'Presence'), $groupID) === $alphaID, 'Reordering preserves member IDs and observed stays.');
$selection[] = ['deviceID' => 'gamma', 'name' => 'Missing'];
IPS_SetProperty($groupID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($groupID);
$check($member('gamma', 'Presence') === -1 && !$member('gamma', 'DataAvailable') && $value($groupID, 'Presence') === 1 && $member('beta', 'StayDuration') === 20, 'Adding a missing member cannot reset existing stays or hide known presence.');
$form = json_decode(IPS_GetConfigurationForm($groupID), true, 512, JSON_THROW_ON_ERROR);
$options = $form['elements'][1]['columns'][0]['edit']['options'];
$check(in_array('gamma', array_column($options, 'value'), true), 'Missing selected devices must remain editable in the form.');
IPS_SetProperty($groupID, 'Devices', json_encode([$selection[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($groupID);
$check(IPS_GetObject($betaID)['ObjectIsHidden'] && GetValue($betaID) === -1, 'Deselected member variables must be unknown and hidden, retaining their IDs.');
IPS_SetProperty($groupID, 'Devices', json_encode([$selection[1], $selection[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($groupID);
$check(!IPS_GetObject($betaID)['ObjectIsHidden'] && $member('beta', 'StayDuration') === 20, 'Reselecting a device reuses its variables and starts from its observed fix.');
$check(IPS_GetObjectIDByIdent($group->GetDeviceVariableIdent('beta', 'Presence'), $groupID) === $betaID, 'Reselection preserves archived variable identity.');

$firstBoundary->result['success'] = false;
$firstBoundary->broadcast();
$check($value($groupID, 'Presence') === -1 && $value($groupID, 'UnknownCount') === 2 && !$value($groupID, 'DataAvailable'), 'Account failure invalidates every member and overall presence.');
$check($member('beta', 'StayDuration') === 0 && !$member('alpha', 'DistanceAvailable'), 'Account errors leave no usable old duration or distance.');
$firstBoundary->result['success'] = true;
$firstBoundary->broadcast();
$check($value($groupID, 'Presence') === -1, 'Repeated cached fixes cannot bridge an account outage.');
$group->clock += 60;
foreach ($firstBoundary->result['devices'] as &$device) {
    $device['location']['timestamp'] = $group->clock;
}
unset($device);
$firstBoundary->broadcast();
$check($value($groupID, 'Presence') === 1 && $member('beta', 'StayDuration') === 0, 'Fresh fixes resume independent stays after an outage.');
$group->clock += 901;
ICPR_Update($groupID);
$check($value($groupID, 'Presence') === -1 && $value($groupID, 'UnknownCount') === 2, 'Stale members must not imply overall absence.');

IPS_SetProperty($groupID, 'Devices', '[]');
IPS_ApplyChanges($groupID);
$check($value($groupID, 'Presence') === -1 && $value($groupID, 'SelectedCount') === 0 && $group->timerInterval() === 0, 'An empty selection is unknown and stops evaluation.');
IPS_SetProperty($groupID, 'Devices', json_encode([$selection[0], $selection[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($groupID);
$check(IPS_GetInstance($groupID)['InstanceStatus'] === 201 && $value($groupID, 'Presence') === -1, 'Duplicate device IDs must fail safely.');
IPS_SetProperty($groupID, 'Devices', '{invalid');
IPS_ApplyChanges($groupID);
$check(IPS_GetInstance($groupID)['InstanceStatus'] === 201, 'Malformed selections must fail safely.');

// The official SDK migration must preserve explicit selections and unrelated persistence.
$old = ['configuration' => ['DeviceID' => 'legacy-device', 'Zones' => json_encode($zones), 'MaxAccuracy' => 50], 'attributes' => ['EvaluationState' => '{"version":1}']];
$migrated = json_decode($group->Migrate(json_encode($old, JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
$check(json_decode($migrated['configuration']['Devices'], true) === [['deviceID' => 'legacy-device', 'name' => '']], 'Single-device selection must migrate into one group row.');
$check($migrated['configuration']['Zones'] === $old['configuration']['Zones'] && $migrated['attributes'] === $old['attributes'], 'Migration must preserve zones, settings and saved state.');
$check($group->Migrate(json_encode($migrated, JSON_THROW_ON_ERROR)) === '', 'Migration is idempotent.');
$old['configuration']['Devices'] = '[]';
$check($group->Migrate(json_encode($old, JSON_THROW_ON_ERROR)) === '', 'An explicit empty selection must never be repopulated from legacy DeviceID.');

$legacyID = IPS_CreateInstance($presenceModule);
$legacy = IPS\InstanceManager::getInstanceInterface($legacyID);
IPS_ConnectInstance($legacyID, $firstAccount);
IPS_SetProperty($legacyID, 'DeviceID', 'legacy-device');
IPS_SetProperty($legacyID, 'Devices', json_encode([['deviceID' => 'legacy-device', 'name' => 'Legacy']], JSON_THROW_ON_ERROR));
IPS_SetProperty($legacyID, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
$oldPresenceID = IPS_GetObjectIDByIdent('Presence', $legacyID);
$state = ['version' => 1, 'zone' => 'home', 'since' => 1699999900, 'candidate' => null, 'candidateSince' => 0,
    'lastTimestamp' => 1700000000, 'point' => ['latitude' => 0.0, 'longitude' => 0.0, 'accuracy' => 5.0], 'evaluatedAt' => 1700000100, 'reason' => 'valid'];
$normalized = array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name'], 'latitude' => (float) $zone['latitude'],
    'longitude'                                                 => (float) $zone['longitude'], 'radius' => (float) $zone['radius']], $zones);
$legacyIdentity = hash('sha256', json_encode([$firstAccount, 'legacy-device', $normalized, 'home',
    ['maxAge' => 900, 'maxAccuracy' => 100, 'hysteresis' => 25, 'confirmSeconds' => 30, 'maxSpeed' => 300]], JSON_THROW_ON_ERROR));
$legacy->seedLegacy($state, $legacyIdentity);
$firstBoundary->result['devices'] = [$makeDevice('legacy-device', 0)];
IPS_ApplyChanges($legacyID);
$newMemberID = IPS_GetObjectIDByIdent($legacy->GetDeviceVariableIdent('legacy-device', 'Presence'), $legacyID);
$check($newMemberID === $oldPresenceID && $newMemberID !== IPS_GetObjectIDByIdent('Presence', $legacyID), 'Legacy variable IDs must follow the original device while the group gets its own summary.');
$check($value($legacyID, $legacy->GetDeviceVariableIdent('legacy-device', 'StayDuration')) === 200, 'A known unchanged legacy source must preserve its observed stay.');
IPS_ApplyChanges($legacyID);
$check(IPS_GetObjectIDByIdent($legacy->GetDeviceVariableIdent('legacy-device', 'Presence'), $legacyID) === $oldPresenceID, 'Repeated application must not repeat the variable migration.');
$check(array_diff($firstBoundary->commands, ['GetDevices']) === [], 'Grouped presence must never request a fresh Apple location.');

// Visibility belongs to the user; retiring a member may hide its variables only once.
$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$makeDevice('alpha', 0), $makeDevice('beta', 0.002)];
$visibilityID = IPS_CreateInstance($presenceModule);
$visibility = IPS\InstanceManager::getInstanceInterface($visibilityID);
IPS_ConnectInstance($visibilityID, $firstAccount);
$visibleMembers = [['deviceID' => 'alpha', 'name' => 'First'], ['deviceID' => 'beta', 'name' => 'Second']];
IPS_SetProperty($visibilityID, 'Devices', json_encode($visibleMembers, JSON_THROW_ON_ERROR));
IPS_SetProperty($visibilityID, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($visibilityID);
$variable = static fn (string $device, string $field): int => IPS_GetObjectIDByIdent($visibility->GetDeviceVariableIdent($device, $field), $visibilityID);
$alphaPresence = $variable('alpha', 'Presence');
$alphaDistance = $variable('alpha', 'Distance');
$betaPresence = $variable('beta', 'Presence');
$betaZone = $variable('beta', 'Zone');
$betaDistance = $variable('beta', 'Distance');
$visibilityIDs = IPS_GetChildrenIDs($visibilityID);
IPS_SetHidden($alphaPresence, true);
IPS_SetHidden($alphaDistance, true);
IPS_SetHidden($betaZone, true);
foreach (['cache', 'push', 'apply', 'apply'] as $refresh) {
    match ($refresh) {
        'cache' => ICPR_Update($visibilityID),
        'push'  => $firstBoundary->broadcast(),
        default => IPS_ApplyChanges($visibilityID)
    };
    $check(IPS_GetObject($alphaPresence)['ObjectIsHidden'] && IPS_GetObject($alphaDistance)['ObjectIsHidden'] && IPS_GetObject($betaZone)['ObjectIsHidden'], 'Cache, push and ApplyChanges must preserve manually hidden member variables.');
    $check(!IPS_GetObject($betaPresence)['ObjectIsHidden'], 'Updates must also preserve visible selected member variables.');
}
IPS_SetProperty($visibilityID, 'Devices', json_encode([$visibleMembers[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($visibilityID);
$check(IPS_GetObject($betaPresence)['ObjectIsHidden'] && IPS_GetObject($betaZone)['ObjectIsHidden'], 'Deselection hides retired variables without losing their previous visibility.');
IPS_SetHidden($betaDistance, false);
ICPR_Update($visibilityID);
$firstBoundary->broadcast();
IPS_ApplyChanges($visibilityID);
$check(!IPS_GetObject($betaDistance)['ObjectIsHidden'], 'Repeated retirement must preserve a user visibility override.');
IPS_SetProperty($visibilityID, 'Devices', json_encode($visibleMembers, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($visibilityID);
$check(!IPS_GetObject($betaPresence)['ObjectIsHidden'] && IPS_GetObject($betaZone)['ObjectIsHidden'] && !IPS_GetObject($betaDistance)['ObjectIsHidden'], 'Reselection restores only module-hidden visibility and preserves previously hidden or manually shown variables.');
$check(IPS_GetChildrenIDs($visibilityID) === $visibilityIDs && $variable('beta', 'Presence') === $betaPresence, 'Visibility changes and reselection must retain all variable identities.');
IPS_SetHidden($betaPresence, true);
IPS_SetProperty($visibilityID, 'Devices', json_encode([$visibleMembers[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($visibilityID);
IPS_SetHidden($betaDistance, false);
ICPR_Update($visibilityID);
IPS_SetHidden($betaDistance, true);
ICPR_Update($visibilityID);
IPS_SetProperty($visibilityID, 'Devices', json_encode($visibleMembers, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($visibilityID);
$check(IPS_GetObject($betaPresence)['ObjectIsHidden'] && IPS_GetObject($alphaPresence)['ObjectIsHidden'], 'A later removal cycle must retain new user visibility settings.');
$check(IPS_GetObject($betaDistance)['ObjectIsHidden'], 'An observed manual override relinquishes module visibility ownership, including later user changes.');

// Both aggregate and dynamic member variables keep user-owned names and icons.
$metadataIDs = IPS_GetChildrenIDs($visibilityID);
foreach ($metadataIDs as $objectID) {
    IPS_SetName($objectID, 'User presence ' . $objectID);
    IPS_SetIcon($objectID, 'Star');
}
IPS_SetProperty($visibilityID, 'Devices', json_encode(array_reverse($visibleMembers), JSON_THROW_ON_ERROR));
IPS_ApplyChanges($visibilityID);
ICPR_Update($visibilityID);
$firstBoundary->broadcast();
foreach ($metadataIDs as $objectID) {
    $check(IPS_GetName($objectID) === 'User presence ' . $objectID && IPS_GetObject($objectID)['ObjectIcon'] === 'Star', 'Aggregate and member labels/icons must survive reorder, ApplyChanges and cache pushes.');
}

echo "Multi-device presence, shared zones, aggregation, identity and migration contracts passed.\n";
