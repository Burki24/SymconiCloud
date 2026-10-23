<?php

declare(strict_types=1);

require_once __DIR__ . '/presence.php';

// Native spinner limits block accepting a value outside minimum/maximum.
// List editor changes use the documented columns.x.edit JSON-object update.
$probeID = IPS_CreateInstance($presenceModule);
$probe = IPS\InstanceManager::getInstanceInterface($probeID);
IPS_ConnectInstance($probeID, $firstAccount);
IPS_SetProperty($probeID, 'Devices', json_encode([['deviceID' => 'synthetic-device', 'name' => '']], JSON_THROW_ON_ERROR));
$formZones = [
    ['id' => 'home', 'name' => 'Synthetic home', 'latitude' => 0, 'longitude' => 0, 'radius' => 10, 'reference' => true],
    ['id' => 'work', 'name' => 'Synthetic work', 'latitude' => 0, 'longitude' => 1, 'radius' => 100, 'reference' => false]
];
$encoded = json_encode($formZones, JSON_THROW_ON_ERROR);
IPS_SetProperty($probeID, 'Zones', $encoded);
IPS_SetProperty($probeID, 'Hysteresis', 10);
IPS_ApplyChanges($probeID);
$check(IPS_GetInstance($probeID)['InstanceStatus'] === 201 && $probe->timerInterval() === 0, 'Equal radius and buffer must remain rejected by runtime validation.');
$check(str_contains($value($probeID, 'Reason'), 'Synthetic home') && str_contains($value($probeID, 'Reason'), '(10 m)'), 'Invalid persisted settings must name the zone and conflicting measurements.');
$form = json_decode($probe->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$check($form['elements'][3]['columns'][4]['edit']['minimum'] === 11, 'A 10 m buffer requires a radius of at least 11 m in the form.');
$check($form['elements'][5]['items'][2]['maximum'] === 9, 'A 10 m zone requires a buffer of at most 9 m in the form.');
$check($form['elements'][7]['visible'] && str_contains($form['elements'][7]['caption'], 'Synthetic home'), 'Reopening invalid settings must show a specific correction message.');
$before = IPS_GetConfiguration($probeID);
$ids = IPS_GetChildrenIDs($probeID);
$commands = $firstBoundary->commands;
$formZones[0]['radius'] = 80;
$formZones[1]['radius'] = 30;
$draft = json_encode($formZones, JSON_THROW_ON_ERROR);
ICPR_ValidateZoneSettings($probeID, $draft, 25);
$editor = json_decode($probe->fieldUpdates['Zones']['columns.4.edit'], true, 512, JSON_THROW_ON_ERROR);
$check($editor === ['type' => 'NumberSpinner', 'minimum' => 26, 'maximum' => 1000000], 'Unsaved buffer changes must update the radius editor with valid JSON.');
$check($probe->fieldUpdates['Hysteresis']['maximum'] === 29, 'The smallest zone limits the buffer, including non-reference zones.');
$check($probe->fieldUpdates['ZoneSettingsError']['visible'] === false, 'Correcting the draft must clear its warning.');
ICPR_ValidateZoneSettings($probeID, $draft, 30);
$check($probe->fieldUpdates['ZoneSettingsError']['visible'] && str_contains($probe->fieldUpdates['ZoneSettingsError']['caption'], 'Synthetic work'), 'An equal buffer must identify the conflicting secondary zone.');
ICPR_ValidateZoneSettings($probeID, $draft, 31);
$check($probe->fieldUpdates['ZoneSettingsError']['visible'] && str_contains($probe->fieldUpdates['ZoneSettingsError']['caption'], '(31 m)'), 'A buffer greater than a zone radius must also be rejected.');
$formZones[1]['radius'] = 1;
ICPR_ValidateZoneSettings($probeID, json_encode($formZones, JSON_THROW_ON_ERROR), 0);
$check($probe->fieldUpdates['Hysteresis']['maximum'] === 0 && !$probe->fieldUpdates['ZoneSettingsError']['visible'], 'A 1 m zone permits the zero-buffer boundary without false rejection.');
ICPR_ValidateZoneSettings($probeID, '[]', 5);
$check($probe->fieldUpdates['Hysteresis']['maximum'] === 10000, 'Deleting the final zone must release obsolete spinner limits.');
ICPR_ValidateZoneSettings($probeID, '{invalid', 5);
$check(IPS_GetConfiguration($probeID) === $before && IPS_GetChildrenIDs($probeID) === $ids && $firstBoundary->commands === $commands, 'Draft validation must not persist settings, alter IDs, evaluate state or request account data.');
IPS_SetProperty($probeID, 'Hysteresis', 9);
IPS_ApplyChanges($probeID);
$check(IPS_GetInstance($probeID)['InstanceStatus'] === IS_ACTIVE && $probe->timerInterval() === 15000, 'Reducing the buffer below every radius must restore evaluation.');
$check(IPS_GetChildrenIDs($probeID) === $ids, 'Correcting invalid settings must retain all variable IDs.');

// A custom zone must not constrain a global buffer it does not use.
$custom = $formZones;
$custom[0] += ['customGPS' => true, 'hysteresis' => 2, 'maxAccuracy' => 20, 'confirmSeconds' => 0];
$custom[1]['radius'] = 100;
ICPR_ValidateZoneSettings($probeID, json_encode($custom, JSON_THROW_ON_ERROR), 25);
$check($probe->fieldUpdates['Hysteresis']['maximum'] === 99, 'Only inheriting zones constrain the global buffer.');
$custom[1]['customGPS'] = true;
$custom[1]['hysteresis'] = 2;
ICPR_ValidateZoneSettings($probeID, json_encode($custom, JSON_THROW_ON_ERROR), 25);
$check($probe->fieldUpdates['Hysteresis']['maximum'] === 10000, 'All custom zones must release the global buffer limit.');
$row = json_decode(ICPR_GetZoneEditForm($probeID, 25, 50, 60, true, 10, 2), true, 512, JSON_THROW_ON_ERROR);
$check($row[4]['minimum'] === 3 && $row[7]['visible'] === true, 'A custom row radius uses its own buffer.');
$check($row[7]['items'][1]['value'] === 50 && $row[7]['items'][2]['value'] === 60, 'New custom values must start from the unsaved global defaults.');
$row = json_decode(ICPR_GetZoneEditForm($probeID, 25, 50, 60, false, 10, 2), true, 512, JSON_THROW_ON_ERROR);
$check($row[4]['minimum'] === 26 && $row[7]['visible'] === false && $row[8]['visible'], 'An inherited row must use the global buffer even when dormant custom values differ.');
$configuration = IPS_GetConfiguration($probeID);
$commands = $firstBoundary->commands;
ICPR_ValidateZoneDraft($probeID, 10, true, 2, 25);
$check($probe->fieldUpdates['radius']['minimum'] === 3 && !$probe->fieldUpdates['ZoneDraftError']['visible'], 'A valid custom draft must clear the warning.');
ICPR_ValidateZoneDraft($probeID, 10, false, 2, 25);
$check($probe->fieldUpdates['radius']['minimum'] === 26 && $probe->fieldUpdates['ZoneDraftError']['visible'], 'Switching inheritance back on must immediately revalidate the radius.');
$check(IPS_GetConfiguration($probeID) === $configuration && $firstBoundary->commands === $commands, 'Row construction and edits must remain free of persistence and account reads.');

$custom[0]['radius'] = 10;
$custom[1]['radius'] = 100;
IPS_SetProperty($probeID, 'Zones', json_encode($custom, JSON_THROW_ON_ERROR));
IPS_SetProperty($probeID, 'Hysteresis', 25);
IPS_ApplyChanges($probeID);
$check(IPS_GetInstance($probeID)['InstanceStatus'] === IS_ACTIVE, 'A valid per-zone override must supersede an incompatible larger global buffer.');
$custom[0]['customGPS'] = false;
IPS_SetProperty($probeID, 'Zones', json_encode($custom, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($probeID);
$check(IPS_GetInstance($probeID)['InstanceStatus'] === 201 && str_contains($value($probeID, 'Reason'), 'Synthetic home'), 'Returning to globals must reject the now-incompatible radius without changing settings.');
$check(IPS_GetChildrenIDs($probeID) === $ids, 'Zone override validation must preserve every existing variable ID.');

echo "Presence form radius/buffer constraints and read-only draft validation passed.\n";
