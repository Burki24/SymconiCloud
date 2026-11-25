<?php

declare(strict_types=1);

require_once __DIR__ . '/address-precision.php';

$firstBoundary->result = $envelope;
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 20.0;
$cloudMapID = IPS_CreateInstance($mapModule);
$cloudMap = IPS\InstanceManager::getInstanceInterface($cloudMapID);
$cloudMap->geocodingTime = 1792627190;
IPS_ConnectInstance($cloudMapID, $firstAccount);
IPS_SetProperty($cloudMapID, 'EnableAddressResolution', true);
IPS_SetProperty($cloudMapID, 'AddressProvider', 'geoapify');
IPS_SetProperty($cloudMapID, 'GeoapifyAPIKey', 'synthetic-cloud-api-key');
IPS_SetProperty($cloudMapID, 'GeoapifyDailyLimit', 2);
IPS_SetProperty($cloudMapID, 'NominatimServer', 'invalid-and-unused');
IPS_SetProperty($cloudMapID, 'EnableIPSView', true);
IPS_ApplyChanges($cloudMapID);
$cloudMap->geocodingResponse = ['status' => 200, 'body' => json_encode(['results' => [[
    'formatted'   => '7, Example Street, Example Quarter, Example Town',
    'housenumber' => '7', 'street' => 'Example Street', 'suburb' => 'Example Quarter',
    'city'        => 'Example Town', 'country' => 'Example Country'
]]], JSON_THROW_ON_ERROR)];
$cloudState = static fn (): array => json_decode($cloudMap->GetState(), true, 512, JSON_THROW_ON_ERROR);
$form = json_decode($cloudMap->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$panel = array_values(array_filter($form['elements'], static fn ($e): bool => ($e['name'] ?? '') === 'AddressResolution'))[0];
$fields = array_column($panel['items'], null, 'name');
$check($fields['GeoapifyAPIKey']['type'] === 'PasswordTextBox', 'Keys must use a masked official form control.');
$check($fields['AddressProvider']['options'][0]['value'] === 'nominatim', 'Nominatim remains the compatible default.');
$check($fields['GeoapifyDailyLimit']['minimum'] === 0 && $fields['GeoapifyDailyLimit']['maximum'] === 1000000, 'The form and helper must agree on allowed budgets.');
$check(!$fields['GeocodingConfigurationWarning']['visible'], 'Geoapify must not require a Nominatim URL.');
$cloudMap->GetVisualizationTile();
$cloudMap->GetIPSViewHTML();
$check($cloudMap->geocodingRequests === [], 'Forms, pages and state reads must never spend budget.');
$cloudMap->ResolveAddresses();
$check($cloudState()['state']['devices'][0]['address'] === 'Example Street 7, Example Town, Example Country', 'Compact Geoapify addresses reach the map.');
$cloudDevice = $createDevice($firstAccount);
IPS_SetProperty($cloudDevice, 'AddressSource', $cloudMapID);
IPS_ApplyChanges($cloudDevice);
$check($value($cloudDevice, 'Address') === 'Example Street 7, Example Town, Example Country', 'The device variable reads Geoapify from its selected WoIst source.');
$deviceID = $firstBoundary->result['devices'][0]['id'];
$location = $firstBoundary->result['devices'][0]['location'];
$check(str_starts_with($cloudMap->GetDeviceAddress($deviceID, (float) $location['latitude'], (float) $location['longitude']), 'Example Street 7'), 'The existing device address API also serves Geoapify.');
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 368.0;
$firstBoundary->broadcast();
$check($cloudState()['state']['devices'][0]['address'] === '', 'Accuracy downgrades immediately hide precise Geoapify results.');
$cloudMap->ResolveAddresses();
$state = $cloudState()['state'];
$check($state['devices'][0]['address'] === 'Example Quarter, Example Town, Example Country', 'Geoapify follows the same coarse-field policy.');
$check(str_contains($value($cloudDevice, 'Address'), 'Approximate') && !str_contains($value($cloudDevice, 'Address'), 'Example Street'), 'Device variables receive the same coarse result and warning.');
$check($state['geocoding']['provider'] === 'geoapify' && $state['geocoding']['notice'] !== '', 'The exhausted budget is visible without disabling the map.');
$ips = json_decode($attributeReader->invoke($cloudMap, 'IPSViewTransportState'), true);
$check($ips['state']['geocoding'] === $state['geocoding'], 'IPSView must receive provider attribution and quota notices too.');
$firstBoundary->result['devices'][0]['location']['longitude'] = 10.25;
$firstBoundary->broadcast();
$cloudMap->ResolveAddresses();
$check(count($cloudMap->geocodingRequests) === 2, 'No address request may pass the configured daily cap.');
$expectedDelay = ((intdiv($cloudMap->geocodingTime, 86400) + 1) * 86400 - $cloudMap->geocodingTime) * 1000;
$check($timerInterval->invoke($cloudMap, 'ResolveAddresses') === $expectedDelay, 'Pending addresses must resume at the next UTC midnight without polling every two seconds.');
IPS_SetProperty($cloudMapID, 'EnableAddressResolution', false);
IPS_ApplyChanges($cloudMapID);
IPS_SetProperty($cloudMapID, 'EnableAddressResolution', true);
IPS_SetProperty($cloudMapID, 'GeoapifyAPIKey', 'another-synthetic-api-key');
IPS_ApplyChanges($cloudMapID);
$cloudMap->ResolveAddresses();
$check(count($cloudMap->geocodingRequests) === 2, 'Disable/re-enable, ApplyChanges and key replacement preserve the counter.');
$cloudMap->geocodingTime = (intdiv($cloudMap->geocodingTime, 86400) + 1) * 86400;
$cloudMap->ResolveAddresses();
$check(count($cloudMap->geocodingRequests) === 3, 'New UTC days resume the background worker.');
foreach ([$cloudMap->GetState(), $cloudMap->GetVisualizationTile(), $cloudMap->GetIPSViewHTML(), $attributeReader->invoke($cloudMap, 'IPSViewTransportState'), $attributeReader->invoke($cloudMap, 'ReverseGeocodingCache')] as $output) {
    $check(!str_contains($output, 'synthetic-api-key') && !str_contains($output, 'synthetic-cloud-api-key'), 'Credentials must never appear in visualization state, HTML or caches.');
}
IPS_SetProperty($cloudMapID, 'GeoapifyDailyLimit', 0);
IPS_ApplyChanges($cloudMapID);
$check($cloudState()['state']['devices'][0]['address'] !== '', 'Zero budget retains cached addresses.');
$firstBoundary->result['devices'][0]['location']['longitude'] = 10.5;
$firstBoundary->broadcast();
$cloudMap->ResolveAddresses();
$check(count($cloudMap->geocodingRequests) === 3, 'Zero explicitly disables fresh requests.');
$check($timerInterval->invoke($cloudMap, 'ResolveAddresses') === 0, 'Zero budget must leave the background timer dormant.');
foreach (['', "bad\nkey"] as $invalid) {
    IPS_SetProperty($cloudMapID, 'GeoapifyAPIKey', $invalid);
    IPS_ApplyChanges($cloudMapID);
    $check($cloudState()['state']['success'] && $cloudState()['state']['devices'][0]['address'] === '', 'Invalid credentials leave the map usable without addresses.');
}
$check(!in_array('RefreshDevices', $firstBoundary->commands, true), 'Geoapify must not refresh Apple.');
echo "WoIst Geoapify integration, quota, privacy and compatibility contracts passed.\n";
