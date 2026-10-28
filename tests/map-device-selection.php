<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/map-accounts.php';
ob_end_clean();

IPS_ConnectInstance($mapID, $firstAccount);
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $secondAccount]]));
IPS_ApplyChanges($mapID);
$check(IPS_GetProperty($mapID, 'ShowAllDevices') === true && count($read()['devices']) === 2, 'Existing maps must continue showing every device by default.');
$key = static fn (int $account): string => 'account:' . $account . ':' . hash('sha256', 'synthetic-device');
$selection = static function (array $keys) use ($mapID): void
{
    IPS_SetProperty($mapID, 'SelectedDevices', json_encode(array_map(static fn (string $key): array => ['DeviceKey' => $key], $keys)));
    IPS_SetProperty($mapID, 'ShowAllDevices', false);
    IPS_ApplyChanges($mapID);
};
$loader = $map->GetIPSViewHTML();
$token = $attribute->invoke($map, 'IPSViewTransportToken');
$selection([$key($secondAccount), $key($secondAccount)]);
$devices = $read()['devices'];
$check(count($devices) === 1 && $devices[0]['accountId'] === (string) $secondAccount, 'Identical device IDs on different accounts must be selectable independently; duplicate selections do not duplicate devices.');
$check($map->GetIPSViewHTML() === $loader && $attribute->invoke($map, 'IPSViewTransportToken') === $token && IPS_GetObjectIDByIdent('IPSViewWoIst', $mapID) === $htmlVariable, 'Device selection must preserve the IPSView loader, token and variable identity.');
$transport = json_decode($attribute->invoke($map, 'IPSViewTransportState'), true, 512, JSON_THROW_ON_ERROR);
$check($transport['state']['devices'] === $devices, 'Native and IPSView state must use the same filtered inventory.');
$unfiltered = (new ReflectionMethod($map, 'bootstrap'))->invoke($map, (new ReflectionMethod($map, 'request'))->invoke($map, 'GetDevices'), false);
$check(count($unfiltered['state']['devices']) === 2, 'Device address consumers must retain the unfiltered cache path.');
$firstBoundary->commands = $secondBoundary->commands = [];
$configuration = IPS_GetConfiguration($mapID);
$form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$panel = array_values(array_filter($form['elements'], static fn ($item): bool => ($item['name'] ?? '') === 'DeviceSelectionPanel'))[0];
$list = array_values(array_filter($panel['items'], static fn ($item): bool => ($item['name'] ?? '') === 'SelectedDevices'))[0];
$options = array_column($list['columns'][0]['edit']['options'], 'caption', 'value');
$check(isset($options[$key($firstAccount)], $options[$key($secondAccount)]) && $options[$key($firstAccount)] !== $options[$key($secondAccount)], 'The editor must offer each cached device with its account identity.');
$check(IPS_GetConfiguration($mapID) === $configuration, 'Opening the device selector must not rewrite saved settings.');
$check(!in_array('RefreshDevices', array_merge($firstBoundary->commands, $secondBoundary->commands), true), 'Form reads must never refresh Apple data.');
$newDevice = array_replace($syntheticDevice, ['id' => 'new-device', 'name' => 'New device']);
$secondBoundary->result['devices'][] = $newDevice;
$check(count($read()['devices']) === 1, 'Newly discovered devices cannot bypass restricted mode.');
IPS_SetProperty($mapID, 'ShowAllDevices', true);
IPS_ApplyChanges($mapID);
$check(count($read()['devices']) === 3, 'All-devices mode must include newly discovered devices.');
$secondBoundary->result = $envelope;
$selection([$key($secondAccount)]);
$secondBoundary->result['success'] = false;
$secondBoundary->result['state'] = 'expired';
$check(count($read()['devices']) === 1 && !$read()['devices'][0]['accountAvailable'], 'Cached selected devices must remain last known during an account failure.');
$secondBoundary->result = $envelope;
$check(count($read()['devices']) === 1 && $read()['devices'][0]['accountAvailable'], 'Account recovery must restore selected device availability.');

$selection([]);
$check($read()['success'] && $read()['devices'] === [], 'An explicitly empty selection must show no devices, not silently fall back to all.');
IPS_SetProperty($mapID, 'ShowAllDevices', true);
IPS_ApplyChanges($mapID);
$check(count($read()['devices']) === 2, 'All-devices mode must release an empty restricted selection.');
$selection([$key($firstAccount)]);
IPS_SetName($firstAccount, 'Renamed account');
$check($read()['devices'][0]['accountId'] === (string) $firstAccount, 'Account renaming cannot change selection identity.');
IPS_DisconnectInstance($mapID);
IPS_ConnectInstance($mapID, $secondAccount);
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $firstAccount]]));
IPS_ApplyChanges($mapID);
$check(count($read()['devices']) === 1 && $read()['devices'][0]['accountId'] === (string) $firstAccount, 'Selection identity must survive moving an account from primary to additional.');
IPS_SetProperty($mapID, 'AdditionalAccounts', '[]');
IPS_ApplyChanges($mapID);
$check($read()['devices'] === [], 'Stored selection never authorizes an account that is no longer assigned.');
$form = json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$panel = array_values(array_filter($form['elements'], static fn ($item): bool => ($item['name'] ?? '') === 'DeviceSelectionPanel'))[0];
$list = array_values(array_filter($panel['items'], static fn ($item): bool => ($item['name'] ?? '') === 'SelectedDevices'))[0];
$check(in_array($key($firstAccount), array_column($list['columns'][0]['edit']['options'], 'value'), true), 'An unavailable saved device must stay represented in the editor without being discarded.');
IPS_SetProperty($mapID, 'AdditionalAccounts', json_encode([['AccountID' => $firstAccount]]));
IPS_ApplyChanges($mapID);
$check(count($read()['devices']) === 1, 'A previously selected source must reappear after it is assigned again.');

foreach (['{bad', '{}', '[{"DeviceKey":12}]', '[{"DeviceKey":"arbitrary"}]', str_repeat(' ', 262145), json_encode(array_fill(0, 1001, ['DeviceKey' => '']))] as $invalid) {
    IPS_SetProperty($mapID, 'SelectedDevices', $invalid);
    IPS_ApplyChanges($mapID);
    $check(!$read()['success'] && $read()['devices'] === [] && IPS_GetInstance($mapID)['InstanceStatus'] === 203, 'Invalid restricted selection must fail closed with a configuration status.');
    json_decode($map->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
}
$selection([$key($firstAccount)]);
$check($read()['success'], 'Repairing selection must recover without resetting the map.');
$debug = json_encode(IPS\DebugServer::getDebugMessages($mapID), JSON_THROW_ON_ERROR);
foreach (['synthetic-device', 'New device', 'Renamed account', $key($firstAccount), $key($secondAccount)] as $private) {
    $check(!str_contains($debug, $private), 'Selection diagnostics must expose counts and mode, not private device identities or names.');
}
echo "WoIst device selection, account identity, empty selection, form recovery and shared display state passed.\n";
