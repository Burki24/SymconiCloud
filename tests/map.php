<?php

declare(strict_types=1);

ob_start();
require_once __DIR__ . '/modules.php';

// RegisterHook is a volatile SDK registration; it must exist before output is enabled.
// https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/registerhook/
class HookLifecycleMap extends iCloudWoIst
{
    public array $registeredHooks = [];

    protected function RegisterHook(string $HookPath): bool
    {
        $this->registeredHooks[$HookPath] = true;
        return true;
    }

    protected function UnregisterHook(string $HookPath): bool
    {
        unset($this->registeredHooks[$HookPath]);
        return true;
    }
}
$modules = $metadata->getValue();
$modules['{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}']['Class'] = HookLifecycleMap::class;
$metadata->setValue(null, $modules);
$lifecycleID = IPS_CreateInstance('{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}');
$lifecycle = IPS\InstanceManager::getInstanceInterface($lifecycleID);
$check(isset($lifecycle->registeredHooks['SymconiCloudWoIst' . $lifecycleID]), 'Create must register the hook even with IPSView disabled.');
$configureTransport = new ReflectionMethod($lifecycle, 'ConfigureWoIstIPSViewTransport');
foreach ([true, false, true] as $enabled) {
    IPS_SetProperty($lifecycleID, 'EnableIPSView', $enabled);
    $configureTransport->invoke($lifecycle);
    $check(isset($lifecycle->registeredHooks['SymconiCloudWoIst' . $lifecycleID]), 'Output toggles must retain the registered hook.');
}
$modules['{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}']['Class'] = iCloudWoIst::class;
$metadata->setValue(null, $modules);

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
$check($initial['options']['tileHeaderVisible'] === true && $initial['options']['tileHeaderInset'] === 58, 'Native tiles must reserve the Symcon title by default.');
$check($initial['options']['design']['height'] === 0, 'New map designs must fit the viewport automatically.');
// Official stubs predate the Symcon 9.1 title field; supply it without modifying vendor sources.
$objectsProperty = new ReflectionProperty(IPS\ObjectManager::class, 'objects');
$objects = $objectsProperty->getValue();
$objects[$mapID]['ObjectIsHiddenTitle'] = true;
$objectsProperty->setValue(null, $objects);
$check($state()['options']['tileHeaderVisible'] === false && $state()['options']['tileHeaderInset'] === 0, 'Hidden native titles must release their reserved header space.');
$objects[$mapID]['ObjectIsHiddenTitle'] = false;
$objectsProperty->setValue(null, $objects);
$check($state()['options']['tileHeaderVisible'] === true, 'Title visibility must be read again for SDK state updates.');
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

$hook = new ReflectionMethod($map, 'ProcessHookData');
$callHook = static function (string $method = 'POST', ?string $provided = null, ?string $path = null) use ($map, $mapID, $hook): array
{
    $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path ?? '/hook/SymconiCloudWoIst' . $mapID];
    if ($provided !== null) {
        $_SERVER['HTTP_X_WOIST_TOKEN'] = $provided;
    }
    ob_start();
    $hook->invoke($map);
    return [http_response_code(), ob_get_clean()];
};

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
$check(str_contains($html, '/page') && !str_contains($html, 'leaflet'), 'IPSView must create a small loader for the real HTTP map page.');
[$pageStatus, $pageHTML] = $callHook('GET', null, '/hook/SymconiCloudWoIst' . $mapID . '/page');
$check(str_contains($pageHTML, '"tileHeaderVisible":false'), 'Standalone IPSView pages must not reserve a native Symcon title.');
$check($pageStatus === 200 && str_contains($pageHTML, '"mode":"ipsview"') && str_contains($pageHTML, '--symc-background'), 'The HTTP page must embed the complete standalone map and theme.');
$check(IPS_GetVariable($outputID)['VariableType'] === VARIABLETYPE_STRING, 'IPSView output must be a String WebContent variable.');
$check(!str_contains($html, '{{') && !str_contains($html, 'synthetic-secret-session'), 'IPSView must resolve templates and exclude account secrets.');
$check(array_unique($firstBoundary->commands) === ['GetDevices'], 'Enabling and rendering IPSView must only read the account cache.');
$literalData = $envelope;
$literalData['devices'][0]['name'] = '{{MODULE_SCRIPT}} {{UNKNOWN}}';
$firstBoundary->result = $literalData;
$check(str_contains($map->GetVisualizationTile(), '"name":"{{MODULE_SCRIPT}} {{UNKNOWN}}"'), 'Literal template-like device names must remain data in the native tile.');
$check(!str_contains($map->GetIPSViewHTML(), '{{MODULE_SCRIPT}}'), 'The persistent IPSView loader must never embed device names.');
$firstBoundary->result = $envelope;
$map->RequestAction('IPSViewHTMLRegenerateVariables', '');
$check(IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === $outputID, 'HTML regeneration must preserve its output variable.');
$html = GetValueString($outputID);
$notification = $envelope;
$notification['devices'][0]['name'] = 'Synthetic IPSView update';
$firstBoundary->commands = [];
$map->ReceiveData(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $notification, JSON_THROW_ON_ERROR));
$check(GetValueString($outputID) === $html, 'Data notifications must never rewrite the persistent IPSView document.');
$check($firstBoundary->commands === [], 'Publishing received IPSView data must not request the account again.');
IPS_SetProperty($mapID, 'IPSViewUseTileDesign', false);
IPS_SetProperty($mapID, 'IPSViewMarkerSize', 13);
IPS_SetProperty($mapID, 'TileMarkerSize', 7);
IPS_ApplyChanges($mapID);
$check(str_contains($callHook('GET', null, '/hook/SymconiCloudWoIst' . $mapID . '/page')[1], '"markerSize":13'), 'The HTTP page must render its independent IPSView design.');
$check(str_contains($map->GetVisualizationTile(), '"markerSize":7'), 'Native tile must retain its own design.');
$map->CopyTileDesignToIPSView();
$check(IPS_GetProperty($mapID, 'IPSViewMarkerSize') === 7 && IPS_GetProperty($mapID, 'IPSViewUseTileDesign') === false, 'Copy action must snapshot saved Tile settings into independent IPSView design.');
$lastHtml = GetValueString($outputID);
IPS_SetProperty($mapID, 'EnableIPSView', false);
IPS_ApplyChanges($mapID);
$check(IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === $outputID && GetValueString($outputID) === $lastHtml, 'Disabling optional output must retain its variable and content.');
$map->ReceiveData(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $notification, JSON_THROW_ON_ERROR));
$check(GetValueString($outputID) === $lastHtml, 'Disabled IPSView output must stop updating retained content.');
$check($callHook('GET', null, '/hook/SymconiCloudWoIst' . $mapID . '/page')[0] === 404, 'Disabled output must reject the page even while the hook remains registered.');

// The standalone document receives data through a read-only authenticated cache endpoint.
IPS_SetProperty($mapID, 'EnableIPSView', true);
IPS_ApplyChanges($mapID);
$readAttribute = new ReflectionMethod($map, 'ReadAttributeString');
$token = $readAttribute->invoke($map, 'IPSViewTransportToken');
$check(strlen($token) === 64 && ctype_xdigit($token), 'IPSView must have a cryptographically random per-instance token.');
$check(!str_contains($map->GetState(), $token) && !str_contains($map->GetVisualizationTile(), $token), 'Native SDK state and Tile must never expose the standalone transport token.');
$firstBoundary->commands = [];
$stableHtml = GetValueString($outputID);
$map->ReceiveData(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $notification, JSON_THROW_ON_ERROR));
[$status, $body] = $callHook('POST', $token);
$payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
$check($status === 200 && $payload['state']['devices'][0]['name'] === 'Synthetic IPSView update', 'Authenticated polling must deliver current notifications from the sanitized cache.');
$check(!str_contains($body, $token) && $payload['options']['design']['markerSize'] === 7, 'State responses must preserve IPSView design and omit transport credentials.');
$check(GetValueString($outputID) === $stableHtml && $firstBoundary->commands === [], 'Polling and notifications must neither rewrite HTML nor contact the account.');
$check($callHook('OPTIONS')[0] === 204 && $callHook('OPTIONS')[1] === '', 'CORS preflight must return no device state.');
$check($callHook('POST')[0] === 403 && $callHook('POST', 'invalid')[0] === 403, 'Missing and incorrect transport tokens must be rejected.');
$check($callHook('GET', $token)[0] === 405 && $callHook('POST', $token, '/hook/SymconiCloudWoIst' . $mapID . '/extra')[0] === 404, 'Only POST on the exact instance hook is allowed.');
// A public shell has no private bootstrap and accepts neither credentials in URLs nor extra routes.
$provider = IPS_GetProperty($mapID, 'TileURL');
$attribution = IPS_GetProperty($mapID, 'TileAttribution');
IPS_SetProperty($mapID, 'TileURL', 'https://tiles.example.invalid/{z}/{x}/{y}.png?key=synthetic-provider-secret');
IPS_SetProperty($mapID, 'TileAttribution', 'synthetic-private-attribution');
IPS_ApplyChanges($mapID);
$firstBoundary->commands = [];
[$pageStatus, $pageHTML] = $callHook('GET', null, '/hook/SymconiCloudWoIst' . $mapID . '/page');
$check($pageStatus === 200 && str_contains($pageHTML, '"fragmentToken":true') && str_contains($pageHTML, '"tileUrl":""'), 'Public HTTP shell must wait for fragment authentication before loading provider tiles.');
foreach ([$token, 'Synthetic IPSView update', 'synthetic-provider-secret', 'synthetic-private-attribution', '51.25'] as $privateValue) {
    $check(!str_contains($pageHTML, $privateValue), 'Public HTTP shell must never embed account data, provider credentials or bearer tokens.');
}
$check($firstBoundary->commands === [], 'Rendering the public HTTP page must not read the account cache.');
$pagePath = '/hook/SymconiCloudWoIst' . $mapID . '/page';
$check($callHook('POST', $token, $pagePath)[0] === 405, 'Public HTTP shell allows GET only.');
foreach ([$pagePath . '?token=' . $token, $pagePath . '/' . $token, $pagePath . '/extra', $pagePath . '/', '/hook/SymconiCloudWoIst' . $mapID . '?token=' . $token] as $invalidPath) {
    $check($callHook('GET', null, $invalidPath)[0] === 404, 'Query credentials and non-exact page paths must be rejected.');
}
IPS_SetProperty($mapID, 'TileURL', $provider);
IPS_SetProperty($mapID, 'TileAttribution', $attribution);
IPS_ApplyChanges($mapID);
$stableHtml = GetValueString($outputID);
$map->Update();
$map->RefreshDevices();
$check(GetValueString($outputID) === $stableHtml, 'Cache updates and explicit Apple refresh must preserve the IPSView document.');
IPS_DisconnectInstance($mapID);
IPS_ConnectInstance($mapID, $secondAccount);
$check($callHook('POST', $token)[0] === 404, 'Connection changes must immediately deny access to the prior account cache.');
IPS_ApplyChanges($mapID);
$newToken = $readAttribute->invoke($map, 'IPSViewTransportToken');
$check($newToken !== $token && $callHook('POST', $token)[0] === 403, 'Changing accounts must rotate standalone credentials.');
$check(json_decode($callHook('POST', $newToken)[1], true, 512, JSON_THROW_ON_ERROR)['state']['devices'] === [], 'New account polling must not retain prior account positions.');
foreach (['https://user@example.invalid', 'https://example.invalid/path', 'https://example.invalid?query=1', 'javascript:alert(1)', "https://example.invalid\n"] as $invalidOrigin) {
    IPS_SetProperty($mapID, 'IPSViewServerURL', $invalidOrigin);
    IPS_ApplyChanges($mapID);
    $check($callHook('POST', $newToken)[0] === 404, 'Invalid explicit server origins must disable transport.');
}
IPS_SetProperty($mapID, 'IPSViewServerURL', 'https://symcon.example.invalid:3777');
IPS_ApplyChanges($mapID);
$check(str_contains(str_replace('\\/', '/', $map->GetIPSViewHTML()), 'https://symcon.example.invalid:3777/hook/SymconiCloudWoIst'), 'Configured origins must resolve the IPSView endpoint explicitly.');
$otherMapID = IPS_CreateInstance('{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}');
IPS_ConnectInstance($otherMapID, $secondAccount);
IPS_SetProperty($otherMapID, 'EnableIPSView', true);
IPS_ApplyChanges($otherMapID);
$otherMap = IPS\InstanceManager::getInstanceInterface($otherMapID);
$otherToken = $readAttribute->invoke($otherMap, 'IPSViewTransportToken');
$currentToken = $readAttribute->invoke($map, 'IPSViewTransportToken');
$check($otherToken !== $currentToken && $callHook('POST', $otherToken)[0] === 403, 'Separate map instances must never share transport authorization.');
IPS_SetProperty($mapID, 'EnableIPSView', false);
IPS_ApplyChanges($mapID);
$check($readAttribute->invoke($map, 'IPSViewTransportToken') === '' && $readAttribute->invoke($map, 'IPSViewTransportState') === '' && $callHook('POST', $newToken)[0] === 404, 'Disabling IPSView must revoke tokens and clear its device cache.');
$_SERVER = [];
echo "Map integration checks passed.\n";
ob_end_flush();
