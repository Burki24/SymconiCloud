<?php

declare(strict_types=1);

require_once __DIR__ . '/geoapify.php';

$firstBoundary->result = $envelope;
$compactMaps = [];
$compactDevices = [];
foreach (['nominatim', 'geoapify'] as $provider) {
    $instance = IPS_CreateInstance($mapModule);
    IPS_ConnectInstance($instance, $firstAccount);
    IPS_SetProperty($instance, 'EnableAddressResolution', true);
    IPS_SetProperty($instance, 'AddressProvider', $provider);
    IPS_SetProperty($instance, 'NominatimServer', 'https://compact-nominatim.example');
    IPS_SetProperty($instance, 'GeoapifyAPIKey', 'synthetic-compact-api-key');
    IPS_SetProperty($instance, 'EnableIPSView', true);
    IPS_ApplyChanges($instance);
    $map = IPS\InstanceManager::getInstanceInterface($instance);
    $map->geocodingResponse = ['status' => 200, 'body' => json_encode($provider === 'nominatim'
        ? ['display_name' => '7a, Example Street, Example Quarter, Example County, Example Town, 12345, Example Country',
            'address'     => ['house_number' => '7a', 'road' => 'Example Street', 'quarter' => 'Example Quarter',
                'city'                       => 'Example Town', 'postcode' => '12345', 'country' => 'Example Country']]
        : ['results' => [['formatted' => 'An inconsistent provider display string', 'housenumber' => '7a', 'street' => 'Example Street',
            'suburb'                  => 'Example Quarter', 'city' => 'Example Town', 'postcode' => '12345', 'country' => 'Example Country']]], JSON_THROW_ON_ERROR)];
    $compactMaps[$provider] = $map;
    $device = $createDevice($firstAccount);
    IPS_SetProperty($device, 'AddressSource', $instance);
    IPS_ApplyChanges($device);
    $compactDevices[$provider] = $device;
}

foreach ([[20.0, 'Example Street 7a, 12345 Example Town, Example Country'],
    [20.01, 'Example Street, 12345 Example Town, Example Country'],
    [100.0, 'Example Street, 12345 Example Town, Example Country'],
    [100.01, 'Example Quarter, 12345 Example Town, Example Country'],
    [1000.0, 'Example Quarter, 12345 Example Town, Example Country'],
    [1000.01, 'Example Town, Example Country'],
    [null, 'Example Town, Example Country']] as [$accuracy, $expected]) {
    $firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = $accuracy;
    $firstBoundary->broadcast();
    foreach ($compactMaps as $provider => $map) {
        $map->ResolveAddresses();
        $state = json_decode($map->GetState(), true, 512, JSON_THROW_ON_ERROR)['state']['devices'][0];
        $check($state['address'] === $expected, 'Both providers must publish the same compact layout at every accuracy boundary.');
        $check(($state['addressNotice'] === '') === ($accuracy === 20.0), 'Compact formatting must preserve the approximate/unknown notices.');
        $check($value($compactDevices[$provider], 'Address') === ($state['addressNotice'] === '' ? '' : $state['addressNotice'] . ': ') . $expected, 'Device variables use the compact provider result with the same notice.');
        $ips = json_decode($attributeReader->invoke($map, 'IPSViewTransportState'), true);
        $check($ips['state']['devices'][0] === $state, 'IPSView and tile state must share the same compact address.');
    }
}
$usage = json_decode($attributeReader->invoke($compactMaps['geoapify'], 'GeoapifyDailyUsage'), true);
$check($usage['used'] === 4, 'Only the four required detail lookups consume Geoapify budget.');
echo "Compact address provider parity, device variables and precision integration passed.\n";
