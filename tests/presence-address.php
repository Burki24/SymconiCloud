<?php

declare(strict_types=1);

ob_start();
require_once __DIR__ . '/map-geocoding.php';
require_once __DIR__ . '/presence.php';
ob_end_clean();

// This inventory device has no corresponding iCloud Device instance.
$now = time();
$position = ['latitude' => 51.25, 'longitude' => 7.5, 'horizontalAccuracy' => 5, 'timestamp' => $now, 'isOld' => false];
$firstBoundary->result = ['success' => true, 'state' => 'authenticated', 'updatedAt' => $now,
    'devices'                       => [['id' => 'presence-only', 'name' => 'Synthetic member', 'location' => $position]], 'error' => ''];
$secondBoundary->result = $firstBoundary->result;
$mapID = IPS_CreateInstance($mapModule);
IPS_ConnectInstance($mapID, $firstAccount);
IPS_SetProperty($mapID, 'EnableAddressResolution', true);
IPS_SetProperty($mapID, 'NominatimServer', 'https://presence-nominatim.example');
IPS_ApplyChanges($mapID);
$map = IPS\InstanceManager::getInstanceInterface($mapID);
$check($map->ResolveAddresses(), 'Synthetic map lookup must populate the real address cache.');
$providerCalls = count($map->geocodingRequests);

$presenceID = IPS_CreateInstance($presenceModule);
$presence = IPS\InstanceManager::getInstanceInterface($presenceID);
$presence->clock = $now;
IPS_ConnectInstance($presenceID, $firstAccount);
IPS_SetProperty($presenceID, 'Devices', '[{"deviceID":"presence-only","name":"Synthetic member"}]');
IPS_SetProperty($presenceID, 'Zones', '[{"id":"home","name":"Home","latitude":0,"longitude":0,"radius":100,"reference":true}]');
IPS_ApplyChanges($presenceID);
$widgetState = static function () use ($presence): array
{
    return json_decode((new ReflectionMethod($presence, 'ReadAttributeString'))->invoke($presence, 'StatusWidgetState'), true, 512, JSON_THROW_ON_ERROR);
};
$status = static fn (): string => $widgetState()['members'][0]['status'];
$check(!str_contains($status(), 'Example Street'), 'Address display must be opt-in.');
IPS_SetProperty($presenceID, 'AddressSource', $mapID);
IPS_ApplyChanges($presenceID);
$check(str_contains($status(), 'Example Street') && str_contains($status(), 'Outside all zones'), 'Outside cards must show a matching address and retain the zone explanation.');
$bootstrap = new ReflectionMethod($presence, 'StatusWidgetBootstrap');
foreach ([[false, false], [true, false], [true, true]] as [$ipsView, $compact]) {
    $model = $bootstrap->invoke($presence, $ipsView, $compact);
    $check(str_contains($model['state']['members'][0][$compact ? 'zone' : 'status'], 'Example Street'), 'Native, IPSView and WidgetCompact models must retain the optional address.');
}
$zoneID = IPS_GetObjectIDByIdent($presence->GetDeviceVariableIdent('presence-only', 'ZoneID'), $presenceID);
$zone = IPS_GetObjectIDByIdent($presence->GetDeviceVariableIdent('presence-only', 'Zone'), $presenceID);
$check(GetValue($zoneID) === '' && GetValue($zone) === 'Outside all zones', 'Script-facing zone variables must remain unchanged.');
$check(count($map->geocodingRequests) === $providerCalls, 'Presence cache reads must not start provider requests.');
$check(ICWI_GetAccountDeviceAddress($mapID, $secondAccount, 'presence-only', 51.25, 7.5) === '', 'An address lookup must not return a different account member.');

IPS_SetProperty($presenceID, 'AddressSource', $firstAccount);
IPS_ApplyChanges($presenceID);
$check(!str_contains($status(), 'Example Street') && IPS_GetInstance($presenceID)['InstanceStatus'] === IS_ACTIVE, 'An invalid optional source must not disable presence.');
IPS_DisconnectInstance($mapID);
IPS_ConnectInstance($mapID, $secondAccount);
IPS_SetProperty($presenceID, 'AddressSource', $mapID);
IPS_ApplyChanges($presenceID);
$check(!str_contains($status(), 'Example Street'), 'An explicit source on another account must be rejected.');
IPS_DisconnectInstance($mapID);
IPS_ConnectInstance($mapID, $firstAccount);

$firstBoundary->result['devices'][0]['location']['longitude'] = 8.0;
$firstBoundary->result['devices'][0]['location']['timestamp']++;
$presence->clock++;
$presence->Update();
$check(!str_contains($status(), 'Example Street'), 'Moved coordinates must clear an unmatched address.');
$firstBoundary->result['devices'][0]['location'] = null;
$presence->Update();
$check($status() === 'Unknown', 'Missing positions must not retain an outside address.');
$firstBoundary->result['devices'][0]['location'] = $position;
$presence->clock = $now;
$presence->Update();
$check(str_contains($status(), 'Example Street'), 'A reliable matching outside position may recover its address.');
IPS_SetProperty($presenceID, 'Zones', '[{"id":"home","name":"Home","latitude":51.25,"longitude":7.5,"radius":100,"reference":true}]');
IPS_ApplyChanges($presenceID);
$check(str_contains($status(), 'Home') && !str_contains($status(), 'Example Street'), 'Known zones must retain their names without an outside address.');
IPS_SetProperty($presenceID, 'Zones', '[{"id":"home","name":"Home","latitude":0,"longitude":0,"radius":100,"reference":true}]');
IPS_ApplyChanges($presenceID);
IPS_SetProperty($presenceID, 'MaxSpeed', 0);
IPS_ApplyChanges($presenceID);
$firstBoundary->result['devices'][0]['location'] = array_replace($position, ['latitude' => 0.0, 'longitude' => 0.0, 'timestamp' => $now + 1]);
$presence->clock++;
$presence->Update();
$check(str_contains($status(), 'Change awaiting confirmation') && !str_contains($status(), 'Example Street'), 'A pending transition must not retain the previous outside address.');
IPS_SetProperty($mapID, 'EnableAddressResolution', false);
IPS_ApplyChanges($mapID);
$presence->Update();
$check(!str_contains($status(), 'Example Street'), 'Disabled map resolution must fall back to the original zone text.');

echo "Optional presence addresses, account/coordinate isolation, fallback and unchanged zone contracts passed.\n";
