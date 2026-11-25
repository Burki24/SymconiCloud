<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/modules.php';
ob_end_clean();

// Additional-source facade: the genuine account boundary has no Apple transport.
function ICAC_GetDevices(int $InstanceID): string
{
    return IPS\InstanceManager::getInstanceInterface($InstanceID)->ForwardData(json_encode(['Command' => 'GetDevices']));
}
function ICAC_RefreshDevices(int $InstanceID): bool
{
    return json_decode(IPS\InstanceManager::getInstanceInterface($InstanceID)->ForwardData(json_encode(['Command' => 'RefreshDevices'])), true)['success'];
}

$firstBoundary->result = $secondBoundary->result = $envelope;
$mapID = IPS_CreateInstance('{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}');
IPS_ConnectInstance($mapID, $firstAccount);
$map = IPS\InstanceManager::getInstanceInterface($mapID);
$read = static fn (): array => json_decode($map->GetState(), true, 512, JSON_THROW_ON_ERROR)['state'];
IPS_SetProperty($mapID, 'StaleAfter', 777);
IPS_SetProperty($mapID, 'EnableIPSView', true);
IPS_ApplyChanges($mapID);
$htmlVariable = IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID);
$attribute = new ReflectionMethod($map, 'ReadAttributeString');
$initialToken = $attribute->invoke($map, 'IPSViewTransportToken');
$check(count($read()['devices']) === 1 && $read()['devices'][0]['id'] === 'synthetic-device', 'Existing primary device IDs must remain unchanged.');
$before = IPS_GetConfiguration($mapID);
$firstBoundary->commands = $secondBoundary->commands = [];
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $secondAccount], ['AccountID' => $firstAccount], ['AccountID' => $secondAccount]]));
IPS_ApplyChanges($mapID);
$state = $read();
$originalConfiguration = json_decode($before, true, 512, JSON_THROW_ON_ERROR);
$currentConfiguration = json_decode(IPS_GetConfiguration($mapID), true, 512, JSON_THROW_ON_ERROR);
unset($originalConfiguration['AdditionalAccounts'], $currentConfiguration['AdditionalAccounts']);
$check($currentConfiguration === $originalConfiguration, 'Adding accounts must preserve every previously saved map and designer setting.');
$check(count($state['accounts']) === 2 && count($state['devices']) === 2, 'Duplicate account entries must be read once and identical raw device IDs must coexist.');
$expandedToken = $attribute->invoke($map, 'IPSViewTransportToken');
$check($expandedToken !== $initialToken && IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === $htmlVariable, 'Account scope changes rotate access credentials without replacing the IPSView variable.');
$map->Update();
$check($attribute->invoke($map, 'IPSViewTransportToken') === $expandedToken, 'Cache-only updates must not rotate the IPSView token or reload its page.');
$check($state['devices'][0]['id'] === 'synthetic-device' && $state['devices'][1]['id'] !== 'synthetic-device', 'Adding a second account must preserve the original selection identity.');
$check($state['devices'][1]['accountId'] === (string) $secondAccount && $state['staleAfter'] === 777, 'Account identity and previous settings must be preserved.');
$check(!in_array('RefreshDevices', $firstBoundary->commands, true) && !in_array('RefreshDevices', $secondBoundary->commands, true), 'ApplyChanges and state reads must never request Apple refresh or authentication.');
$secondID = $state['devices'][1]['id'];
$secondBoundary->result['success'] = false;
$secondBoundary->result['state'] = 'expired';
$state = $read();
$check($state['success'] && $state['devices'][0]['accountAvailable'] && !$state['devices'][1]['accountAvailable'], 'A failed extra account must not invalidate the healthy primary account.');
$check($state['devices'][1]['id'] === $secondID, 'Failure and recovery must keep the selected device identity stable.');
$secondBoundary->result = $envelope;
$firstBoundary->result['success'] = false;
$firstBoundary->result['state'] = 'expired';
$check($read()['success'] && $read()['devices'][1]['accountAvailable'], 'A failed primary account must not disable a healthy additional source.');
$firstBoundary->result = $envelope;
$firstBoundary->commands = $secondBoundary->commands = [];
$check($map->RefreshDevices(), 'Explicit refresh must succeed for healthy accounts.');
$check($firstBoundary->commands === ['RefreshDevices'] && $secondBoundary->commands === ['RefreshDevices', 'GetDevices'], 'Each selected account receives one explicit protected refresh.');
$firstBoundary->commands = [];
$firstBoundary->broadcast();
$check($firstBoundary->commands === [], 'A parent push uses its snapshot without a recursive cache request.');
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $secondAccount], ['AccountID' => $firstDevice]]));
IPS_ApplyChanges($mapID);
$check($read()['success'] && count($read()['accounts']) === 3 && count($read()['devices']) === 2, 'A wrong-module source must be isolated, never called as an account.');
IPS_SetProperty($mapID, 'AdditionalAccounts', '{bad');
IPS_ApplyChanges($mapID);
$check(!$read()['success'] && IPS_GetInstance($mapID)['InstanceStatus'] === 203, 'Malformed account configuration must fail closed without an exception.');
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode(array_fill(0, 21, ['AccountID' => $secondAccount])));
IPS_ApplyChanges($mapID);
$check(!$read()['success'], 'Additional source lists must have a bounded size even when entries repeat.');
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $secondAccount]]));
IPS_ApplyChanges($mapID);
$check($read()['success'] && IPS_GetProperty($mapID, 'StaleAfter') === 777, 'Repairing the source list must recover without resetting existing properties.');
IPS_SetProperty($mapID, 'AdditionalAccounts', '[]');
IPS_ApplyChanges($mapID);
$check(count($read()['devices']) === 1 && $read()['devices'][0]['id'] === 'synthetic-device', 'Removing extra sources must leave the original account and device identities intact.');
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $secondAccount]]));
IPS_ApplyChanges($mapID);
IPS_DisconnectInstance($mapID);
$check($read()['success'] && count($read()['devices']) === 1, 'An assigned additional account remains available when the primary connection is removed.');
echo "Multi-account map isolation, identity, cache-only access and backward compatibility passed.\n";
