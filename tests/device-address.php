<?php

declare(strict_types=1);

require_once __DIR__ . '/map-geocoding.php';

// Keep real module routing and the central cache; only Nominatim is synthetic.
$firstBoundary->result = $envelope;
$secondBoundary->result = $envelope;
IPS_SetProperty($firstDevice, 'DeviceID', 'synthetic-device');
IPS_ApplyChanges($firstDevice);
$addressID = IPS_GetObjectIDByIdent('Address', $firstDevice);
$check(IPS_GetVariable($addressID)['VariableType'] === 3, 'Address must be a string variable.');
$check((IPS_GetVariable($addressID)['VariableCustomAction'] ?? 0) === 0, 'Address must be read only.');
$check(IPS_GetVariablePresentation($addressID)['PRESENTATION'] === VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'Address must use the central native text presentation.');
$check($value($firstDevice, 'Address') === '', 'Without an enabled map there is no address.');
$deviceForm = json_decode(IPS_GetConfigurationForm($firstDevice), true, 512, JSON_THROW_ON_ERROR);
$sourceField = array_values(array_filter($deviceForm['elements'], static fn ($field): bool => ($field['name'] ?? '') === 'AddressSource'))[0];
// Official SelectInstance contract: integer property plus a module-ID restriction.
$check($sourceField['type'] === 'SelectInstance' && $sourceField['validModules'] === [$mapModule], 'Address source selection must use the documented instance/module filter.');
$check(IPS_GetProperty($firstDevice, 'AddressSource') === 0, 'An unset source must default to automatic selection.');
IPS_ApplyChanges($firstDevice);
$check(IPS_GetObjectIDByIdent('Address', $firstDevice) === $addressID, 'Repeated ApplyChanges must not recreate the address variable.');

$addressMap = IPS_CreateInstance($mapModule);
IPS_ConnectInstance($addressMap, $firstAccount);
IPS_SetProperty($addressMap, 'EnableAddressResolution', true);
IPS_SetProperty($addressMap, 'NominatimServer', 'https://nominatim.example');
IPS_ApplyChanges($addressMap);
$addressMapInterface = IPS\InstanceManager::getInstanceInterface($addressMap);
$check($value($firstDevice, 'Address') === '' && $addressMapInterface->geocodingRequests === [], 'A cache miss must not resolve from a device callback.');
$check($addressMapInterface->ResolveAddresses(), 'The existing map worker must resolve the position.');
$check($value($firstDevice, 'Address') === 'Example Street 7, Example Town', 'A background address result must immediately reach the device variable.');
$check($value($secondDevice, 'Address') === '', 'The same device ID at another account must not receive the address.');
$check(ICID_Update($firstDevice) && count($addressMapInterface->geocodingRequests) === 1, 'Device updates must reuse the cache without any extra HTTP.');
$check(ICWI_GetDeviceAddress($addressMap, 'synthetic-device', 51.25, 8.0) === '', 'An address read must reject mismatched coordinates.');
$check(ICWI_GetDeviceAddress($addressMap, 'missing-device', 51.25, 7.5) === '', 'An address read must reject missing devices.');

$lateDevice = $createDevice($firstAccount);
$check($value($lateDevice, 'Address') === 'Example Street 7, Example Town', 'New device instances must read an already populated map cache.');
$check(IPS_GetObjectIDByIdent('Address', $firstDevice) === $addressID, 'Repeated ApplyChanges must retain the address variable.');

// Multiple maps require an explicit source instead of arbitrary provider choice.
$otherMap = IPS_CreateInstance($mapModule);
IPS_ConnectInstance($otherMap, $firstAccount);
IPS_SetProperty($otherMap, 'EnableAddressResolution', true);
IPS_SetProperty($otherMap, 'NominatimServer', 'https://other-nominatim.example');
IPS_ApplyChanges($otherMap);
$check($value($firstDevice, 'Address') === '', 'Automatic selection must fail closed for ambiguous sources.');
IPS_SetProperty($firstDevice, 'AddressSource', $addressMap);
IPS_ApplyChanges($firstDevice);
$check($value($firstDevice, 'Address') === 'Example Street 7, Example Town', 'An explicit source must disambiguate maps.');
IPS_SetProperty($secondDevice, 'AddressSource', $addressMap);
IPS_ApplyChanges($secondDevice);
$check($value($secondDevice, 'Address') === '', 'An explicit map cannot cross the account boundary.');
IPS_SetProperty($firstDevice, 'AddressSource', $firstAccount);
IPS_ApplyChanges($firstDevice);
$check($value($firstDevice, 'Address') === '' && IPS_GetInstance($firstDevice)['InstanceStatus'] === IS_ACTIVE, 'An invalid optional source must not disable normal device data.');
IPS_SetProperty($firstDevice, 'AddressSource', $addressMap);
IPS_ApplyChanges($firstDevice);
IPS_SetProperty($otherMap, 'EnableAddressResolution', false);
IPS_ApplyChanges($otherMap);
$check($value($lateDevice, 'Address') === 'Example Street 7, Example Town', 'Automatic selection recovers when a source is unambiguous.');

// Position changes clear the old address before the asynchronous lookup completes.
$firstBoundary->result['devices'][0]['location']['longitude'] = 8.0;
$firstBoundary->broadcast();
$check($value($firstDevice, 'Longitude') === 8.0 && $value($firstDevice, 'Address') === '', 'New coordinates must never retain the old address.');
$addressMapInterface->geocodingResponse = ['status' => 200, 'body' => '{"display_name":"New Example Street 8"}'];
$addressMapInterface->ResolveAddresses();
$check($value($firstDevice, 'Address') === 'New Example Street 8', 'A resolved moved position must update the variable.');

$firstBoundary->result['devices'][0]['location']['longitude'] = 8.5;
$firstBoundary->broadcast();
$addressMapInterface->duringLookup = static function () use ($firstBoundary, $addressMapInterface): void
{
    $firstBoundary->result['devices'][0]['location']['longitude'] = 9.0;
    $addressMapInterface->duringLookup = null;
};
$addressMapInterface->ResolveAddresses();
$check($value($firstDevice, 'Longitude') === 9.0 && $value($firstDevice, 'Address') === '', 'A position changed during HTTP must not receive the completed old address.');
$addressMapInterface->geocodingResponse = ['status' => 503, 'body' => 'provider unavailable'];
$addressMapInterface->ResolveAddresses();
$check($value($firstDevice, 'DataAvailable') && $value($firstDevice, 'Address') === '', 'A provider outage leaves normal device data active without a false address.');

$firstBoundary->result['devices'][0]['location'] = null;
$firstBoundary->broadcast();
$check(!$value($firstDevice, 'LocationAvailable') && $value($firstDevice, 'Address') === '', 'A missing location clears the address while retaining legacy coordinates.');
$check($value($firstDevice, 'Longitude') === 9.0, 'Address support must preserve existing last-known-coordinate behavior.');
$firstBoundary->result = $envelope;
$firstBoundary->broadcast();
$check($value($firstDevice, 'Address') === 'Example Street 7, Example Town', 'A known position can reuse its cached address.');
$firstBoundary->result['success'] = false;
$firstBoundary->result['state'] = 'expired';
$firstBoundary->broadcast();
$check($value($firstDevice, 'Address') === '', 'An expired account must hide the address.');
$firstBoundary->result = $envelope;
$firstBoundary->broadcast();

IPS_SetProperty($addressMap, 'NominatimServer', 'https://changed-nominatim.example');
IPS_ApplyChanges($addressMap);
$check($value($firstDevice, 'Address') === '', 'Changing Nominatim servers must clear the device address from the previous provider.');
$addressMapInterface->geocodingResponse = ['status' => 200, 'body' => '{"display_name":"Changed Provider Street"}'];
$addressMapInterface->ResolveAddresses();
$check($value($firstDevice, 'Address') === 'Changed Provider Street', 'The selected new provider can refill the address.');
IPS_SetProperty($addressMap, 'AddressLanguage', 'en');
IPS_ApplyChanges($addressMap);
$check($value($firstDevice, 'Address') === '', 'Changing address language must not retain a different-language cache result.');
$addressMapInterface->ResolveAddresses();
// A missing sibling device must not prevent map updates or other devices.
IPS_SetProperty($lateDevice, 'DeviceID', 'missing-device');
IPS_ApplyChanges($lateDevice);
$addressMapInterface->Update();
$check($value($lateDevice, 'Address') === '' && $value($firstDevice, 'Address') !== '', 'A missing sibling device cannot prevent valid device addresses.');
IPS_SetProperty($lateDevice, 'DeviceID', 'synthetic-device');
IPS_ApplyChanges($lateDevice);

IPS_SetProperty($addressMap, 'EnableAddressResolution', false);
IPS_ApplyChanges($addressMap);
$check($value($firstDevice, 'Address') === '' && $value($lateDevice, 'Address') === '', 'Disabling resolution must clear linked device addresses.');
IPS_SetProperty($addressMap, 'EnableAddressResolution', true);
IPS_ApplyChanges($addressMap);
$addressMapInterface->ResolveAddresses();
$check($value($firstDevice, 'Address') !== '', 'Re-enabled resolution must publish new results.');
IPS_SetProperty($firstDevice, 'DeviceID', 'different-synthetic-device');
IPS_ApplyChanges($firstDevice);
$check($value($firstDevice, 'Address') === '', 'Changing device identity must clear the address.');
IPS_SetProperty($firstDevice, 'DeviceID', 'synthetic-device');
IPS_DisconnectInstance($firstDevice);
IPS_ConnectInstance($firstDevice, $secondAccount);
IPS_ApplyChanges($firstDevice);
$check($value($firstDevice, 'Address') === '', 'Changing accounts must not reuse the old source.');
IPS_DeleteInstance($addressMap);
ICID_Update($lateDevice);
$check($value($lateDevice, 'Address') === '', 'Deleted sources must be handled without errors on the next cache update.');
$check(!in_array('RefreshDevices', $firstBoundary->commands, true), 'Address propagation must never request Apple data.');

echo "Device address integration contracts passed.\n";
