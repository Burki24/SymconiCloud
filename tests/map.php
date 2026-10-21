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

// Optional IPSView output uses the same account cache and managed WebContent lifecycle.
IPS_DisconnectInstance($mapID);
IPS_ConnectInstance($mapID, $firstAccount);
$firstBoundary->result = $envelope;
$firstBoundary->commands = [];
$check(@IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === false, 'IPSView output must remain opt-in.');
IPS_SetProperty($mapID, 'EnableIPSView', true);
IPS_ApplyChanges($mapID);
$outputID = IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID);
$html = GetValueString($outputID);
$check(str_contains($html, '"mode":"ipsview"') && str_contains($html, 'woist-app'), 'IPSView must create a standalone rendered page.');
$check(str_contains($html, '--symc-background'), 'IPSView must embed a complete standalone visual theme.');
$check(IPS_GetVariable($outputID)['VariableType'] === VARIABLETYPE_STRING, 'IPSView output must be a String WebContent variable.');
$check(!str_contains($html, '{{') && !str_contains($html, 'synthetic-secret-session'), 'IPSView must resolve templates and exclude account secrets.');
$check(array_unique($firstBoundary->commands) === ['GetDevices'], 'Enabling and rendering IPSView must only read the account cache.');
$literalData = $envelope;
$literalData['devices'][0]['name'] = '{{MODULE_SCRIPT}} {{UNKNOWN}}';
$firstBoundary->result = $literalData;
foreach ([$map->GetVisualizationTile(), $map->GetIPSViewHTML()] as $literalPage) {
    $check(str_contains($literalPage, '"name":"{{MODULE_SCRIPT}} {{UNKNOWN}}"'), 'Literal template-like device names must remain data in both rendering modes.');
}
$firstBoundary->result = $envelope;
$map->RequestAction('IPSViewHTMLRegenerateVariables', '');
$check(IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === $outputID, 'HTML regeneration must preserve its output variable.');
$notification = $envelope;
$notification['devices'][0]['name'] = 'Synthetic IPSView update';
$firstBoundary->commands = [];
$map->ReceiveData(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $notification, JSON_THROW_ON_ERROR));
$check(str_contains(GetValueString($outputID), 'Synthetic IPSView update'), 'Account notifications must update IPSView output.');
$check($firstBoundary->commands === [], 'Publishing received IPSView data must not request the account again.');
IPS_SetProperty($mapID, 'IPSViewUseTileDesign', false);
IPS_SetProperty($mapID, 'IPSViewMarkerSize', 13);
IPS_SetProperty($mapID, 'TileMarkerSize', 7);
IPS_ApplyChanges($mapID);
$check(str_contains($map->GetIPSViewHTML(), '"markerSize":13'), 'IPSView must render its independent design.');
$check(str_contains($map->GetVisualizationTile(), '"markerSize":7'), 'Native tile must retain its own design.');
$map->CopyTileDesignToIPSView();
$check(IPS_GetProperty($mapID, 'IPSViewMarkerSize') === 7 && IPS_GetProperty($mapID, 'IPSViewUseTileDesign') === false, 'Copy action must snapshot saved Tile settings into independent IPSView design.');
$lastHtml = GetValueString($outputID);
IPS_SetProperty($mapID, 'EnableIPSView', false);
IPS_ApplyChanges($mapID);
$check(IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === $outputID && GetValueString($outputID) === $lastHtml, 'Disabling optional output must retain its variable and content.');
$map->ReceiveData(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $notification, JSON_THROW_ON_ERROR));
$check(GetValueString($outputID) === $lastHtml, 'Disabled IPSView output must stop updating retained content.');

echo "Map integration checks passed.\n";
