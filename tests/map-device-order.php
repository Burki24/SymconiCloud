<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/map-device-selection.php';
ob_end_clean();

IPS_SetProperty($mapID, 'ShowAllDevices', true);
IPS_ApplyChanges($mapID);
$initialOrderToken = $attribute->invoke($map, 'IPSViewTransportToken');
$orderLoader = $map->GetIPSViewHTML();
$move = static fn (string $from, string $before): bool => $map->ChangeDeviceOrder(json_encode(['device' => $from, 'before' => $before]));
$firstBoundary->commands = $secondBoundary->commands = [];
$check($move($key($firstAccount), $key($secondAccount)), 'An assigned device must be movable before another account device.');
$check(array_column($read()['devices'], 'orderKey') === [$key($firstAccount), $key($secondAccount)], 'Persisted ordering must be shared by both account inventories.');
$map->Update();
$check(array_column($read()['devices'], 'orderKey') === [$key($firstAccount), $key($secondAccount)], 'Cache updates cannot undo a saved order.');
IPS_ApplyChanges($mapID);
$check(array_column($read()['devices'], 'orderKey') === [$key($firstAccount), $key($secondAccount)], 'Applying the same configuration must retain persisted ordering.');
$check($move($key($firstAccount), ''), 'A device can be moved to the end.');
$check($read()['devices'][1]['orderKey'] === $key($firstAccount), 'Move-to-end must persist.');
$selection([$key($firstAccount)]);
$check(!$move($key($secondAccount), $key($firstAccount)), 'The endpoint cannot move a device excluded from this map.');
IPS_SetProperty($mapID, 'ShowAllDevices', true);
IPS_ApplyChanges($mapID);
$newDevice = array_replace($syntheticDevice, ['id' => 'later-device', 'name' => 'Later']);
$secondBoundary->result['devices'] = [$newDevice, $syntheticDevice];
$map->Update();
$check($read()['devices'][2]['name'] === 'Later', 'New devices must be appended regardless of cache inventory order.');
$secondBoundary->result = $envelope;
$map->Update();
$secondBoundary->result['devices'][] = $newDevice;
$map->Update();
$check($read()['devices'][2]['name'] === 'Later', 'Disappearing and returning devices retain their saved rank.');
$laterKey = 'account:' . $secondAccount . ':' . hash('sha256', 'later-device');
$selection([$key($firstAccount), $laterKey]);
$check($move($laterKey, $key($firstAccount)), 'Filtered views may reorder their visible devices.');
IPS_SetProperty($mapID, 'ShowAllDevices', true);
IPS_ApplyChanges($mapID);
$check(count($read()['devices']) === 3 && in_array($key($secondAccount), array_column($read()['devices'], 'orderKey'), true), 'Moving a filtered device must not discard hidden order entries.');
$savedOrder = $attribute->invoke($map, 'WoIstDeviceOrder');
foreach (['{bad', '{}', '[]', str_repeat('x', 1025), json_encode(['device' => 'arbitrary', 'before' => '']), json_encode(['reset' => false]), json_encode(['device' => $key($firstAccount), 'before' => 'unknown'])] as $bad) {
    $check(!$map->ChangeDeviceOrder($bad), 'Invalid or stale order operations must fail without mutation.');
    $check($attribute->invoke($map, 'WoIstDeviceOrder') === $savedOrder, 'A rejected operation must preserve saved order.');
}
$check($map->ChangeDeviceOrder('{"reset":true}'), 'Alphabetical reset must be explicit and supported.');
$check($read()['devices'][0]['name'] === 'Later', 'Reset uses natural alphabetical names across accounts.');
$transport = json_decode($attribute->invoke($map, 'IPSViewTransportState'), true, 512, JSON_THROW_ON_ERROR);
$check(array_column($transport['state']['devices'], 'orderKey') === array_column($read()['devices'], 'orderKey'), 'IPSView and native tiles receive the same order.');
$check($attribute->invoke($map, 'IPSViewTransportToken') === $initialOrderToken && $map->GetIPSViewHTML() === $orderLoader, 'Ordering does not rotate access credentials or reload IPSView.');
$check(!in_array('RefreshDevices', array_merge($firstBoundary->commands, $secondBoundary->commands), true), 'Sorting reads caches only, never Apple.');
$debugOrder = json_encode(IPS\DebugServer::getDebugMessages($mapID), JSON_THROW_ON_ERROR);
$check(!str_contains($debugOrder, $key($firstAccount)) && !str_contains($debugOrder, $laterKey) && !str_contains($debugOrder, 'later-device'), 'Sort diagnostics must not reveal device identities or request bodies.');

class OrderTransportMap extends iCloudWoIst
{
    public string $body = '';
    public int $bodyReads = 0;

    protected function ReadWoIstOrderBody(): string
    {
        $this->bodyReads++;
        return $this->body;
    }
}
$moduleTable = $metadata->getValue();
$moduleTable['{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}']['Class'] = OrderTransportMap::class;
$metadata->setValue(null, $moduleTable);
$httpID = IPS_CreateInstance('{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}');
IPS_ConnectInstance($httpID, $firstAccount);
IPS_SetProperty($httpID, 'AdditionalAccounts', json_encode([['AccountID' => $secondAccount]]));
IPS_SetProperty($httpID, 'EnableIPSView', true);
IPS_ApplyChanges($httpID);
$httpMap = IPS\InstanceManager::getInstanceInterface($httpID);
$httpMap->body = json_encode(['device' => $key($secondAccount), 'before' => $key($firstAccount)]);
$savedServer = $_SERVER;
$hook = new ReflectionMethod($httpMap, 'ProcessHookData');
$runHook = static function (string $method, string $token) use ($hook, $httpMap, $httpID): string
{
    $_SERVER['REQUEST_URI'] = '/hook/SymconiCloudWoIst' . $httpID . '/order';
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_X_WOIST_TOKEN'] = $token;
    ob_start();
    try {
        $hook->invoke($httpMap);
        return ob_get_contents();
    }
    finally {
        ob_end_clean();
    }
};
try {
    $httpToken = $attribute->invoke($httpMap, 'IPSViewTransportToken');
    $runHook('POST', 'invalid');
    $check(http_response_code() === 403 && $httpMap->bodyReads === 0, 'Unauthorized order requests must be rejected before reading or changing order.');
    $runHook('GET', $httpToken);
    $check(http_response_code() === 405 && $httpMap->bodyReads === 0, 'GET cannot change order.');
    $response = json_decode($runHook('POST', $httpToken), true, 512, JSON_THROW_ON_ERROR);
    $check(http_response_code() === 200 && $response['state']['devices'][0]['orderKey'] === $key($secondAccount), 'Authenticated IPSView ordering must return the authoritative shared state.');
    $httpMap->body = '{bad';
    $runHook('POST', $httpToken);
    $check(http_response_code() === 400, 'Invalid authenticated JSON is rejected without a success response.');
} finally {
    $_SERVER = $savedServer;
}
echo "Shared WoIst device ordering, stable identities, reset, validation and no-refresh contracts passed.\n";
