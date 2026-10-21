<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

// Keep the genuine map instance and kernel routing; only Apple transport is synthetic.
$firstBoundary->result = $envelope;
$mapID = IPS_CreateInstance('{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}');
IPS_ConnectInstance($mapID, $firstAccount);
IPS_ApplyChanges($mapID);
$map = IPS\InstanceManager::getInstanceInterface($mapID);
$state = static fn (): array => json_decode($map->GetState(), true, 512, JSON_THROW_ON_ERROR);
$check(IPS_GetInstance($mapID)['InstanceVisualizationType'] === INSTANCE_VISUALIZATION_TYPE_HTML, 'WoIst must register an HTML visualization.');
$check(IPS_GetInstance($mapID)['InstanceStatus'] === IS_ACTIVE, 'An authenticated account cache must activate the map.');
$method = new ReflectionMethod($map, 'GetVisualizationTile');
$check($method->getNumberOfParameters() === 0 && (string) $method->getReturnType() === 'string', 'HTML SDK requires GetVisualizationTile(): string.');
$parents = json_decode($map->GetCompatibleParents(), true, 512, JSON_THROW_ON_ERROR);
$check($parents === ['type' => 'connect', 'moduleIDs' => [$accountModule]], 'The map must offer the official shared account parent descriptor.');
$firstBoundary->commands = [];
$initial = $state();
$check($initial['state']['devices'][0]['location']['latitude'] === 51.25, 'Map must preserve normalized account coordinates.');
$check($initial['state']['devices'][0]['batteryLevel'] === 0.42, 'Map must preserve normalized battery fractions.');
$check($map->Update(), 'Cache update must succeed.');
$map->RequestAction('GetState', '');
$tile = $map->GetVisualizationTile();
$check(!str_contains($tile, '{{BOOTSTRAP}}') && str_contains($tile, 'woist-app'), 'HTML tile must embed its bootstrap and local assets.');
$check(array_unique($firstBoundary->commands) === ['GetDevices'], 'Opening, rendering and SDK cache actions must never refresh Apple.');
$messages = $map->getMessages();
$last = end($messages);
$check($last['Message'] === 10541 && is_array(json_decode($last['Data'][0], true, 512, JSON_THROW_ON_ERROR)), 'SDK state action must publish JSON through UpdateVisualizationValue.');
$check($map->RefreshDevices() && end($firstBoundary->commands) === 'RefreshDevices', 'Only explicit refresh must request fresh account data.');

$unsafe = $syntheticDevice;
$unsafe['name'] = '</script><script>alert("synthetic")</script>';
$unsafe['password'] = 'synthetic-secret';
$firstBoundary->result = $envelope + ['token' => 'synthetic-token'];
$firstBoundary->result['devices'] = [$unsafe, $unsafe];
$json = $map->GetState();
$check(!str_contains($json, '</script>') && !str_contains($json, 'synthetic-secret') && !str_contains($json, 'synthetic-token'), 'Bootstrap must escape script boundaries and exclude credentials and unnormalized fields.');
$check(count($state()['state']['devices']) === 1, 'Duplicate account device IDs must not generate duplicate markers.');
$zero = $syntheticDevice;
$zero['location']['latitude'] = 0;
$zero['location']['longitude'] = 0;
$firstBoundary->result['devices'] = [$zero];
$check($state()['state']['devices'][0]['location']['latitude'] === 0.0, 'Zero coordinates are valid positions, not missing data.');
foreach ([null, ['latitude' => 91, 'longitude' => 0, 'timestamp' => time()], ['latitude' => 1, 'longitude' => 181, 'timestamp' => time()], ['latitude' => 1, 'longitude' => 1, 'timestamp' => time() + 3600], ['latitude' => '1', 'longitude' => 1, 'timestamp' => time()]] as $invalid) {
    $firstBoundary->result['devices'][0]['location'] = $invalid;
    $check($state()['state']['devices'][0]['location'] === null, 'Missing, malformed, out-of-range and future locations must not produce fabricated markers.');
}
$firstBoundary->result = $envelope;
$firstBoundary->result['success'] = false;
$firstBoundary->result['state'] = 'expired';
$failed = $state()['state'];
$check(!$failed['success'] && $failed['devices'][0]['location']['latitude'] === 51.25, 'Unavailable accounts may display last-known positions only with failure state.');
$firstBoundary->result = $envelope;
$map->ReceiveData('{broken');
$check(IPS_GetInstance($mapID)['InstanceStatus'] === 202, 'Malformed account notifications must fail closed.');
$map->ReceiveData(json_encode(['DataID' => '{00000000-0000-0000-0000-000000000000}'] + $envelope, JSON_THROW_ON_ERROR));
$check(IPS_GetInstance($mapID)['InstanceStatus'] === 202, 'Notifications from an unexpected data interface must fail closed.');

foreach (['StaleAfter' => 0, 'TileURL' => 'http://example.invalid/{z}/{x}/{y}.png', 'TileAttribution' => ''] as $property => $invalid) {
    $old = IPS_GetProperty($mapID, $property);
    IPS_SetProperty($mapID, $property, $invalid);
    $firstBoundary->commands = [];
    IPS_ApplyChanges($mapID);
    $check(!$map->RefreshDevices() && !$state()['state']['success'], 'Invalid map settings must reject cache and refresh actions.');
    $check($firstBoundary->commands === [], 'Invalid settings must be rejected before contacting the account.');
    IPS_SetProperty($mapID, $property, $old);
    IPS_ApplyChanges($mapID);
}
$secondBoundary->result = $envelope;
$secondBoundary->result['devices'] = [];
IPS_DisconnectInstance($mapID);
IPS_ConnectInstance($mapID, $secondAccount);
IPS_ApplyChanges($mapID);
$check($state()['state']['devices'] === [], 'Changing account connection must not retain another account coordinates.');
$before = $state();
$firstBoundary->broadcast();
$check($state() === $before, 'Notifications from another account must not affect the map.');
try {
    $map->RequestAction('UnrecognizedAction', '');
    throw new RuntimeException('Map actions must be allowlisted.');
} catch (InvalidArgumentException) {
}

echo "Map integration checks passed.\n";
