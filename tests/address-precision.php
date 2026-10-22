<?php

declare(strict_types=1);

require_once __DIR__ . '/device-address.php';

$firstBoundary->result = $envelope;
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 20.0;
$precisionDevice = $createDevice($firstAccount);
$precisionMap = IPS_CreateInstance($mapModule);
IPS_ConnectInstance($precisionMap, $firstAccount);
IPS_SetProperty($precisionMap, 'EnableAddressResolution', true);
IPS_SetProperty($precisionMap, 'NominatimServer', 'https://nominatim.example');
IPS_SetProperty($precisionMap, 'EnableIPSView', true);
IPS_ApplyChanges($precisionMap);
$precision = IPS\InstanceManager::getInstanceInterface($precisionMap);
$precision->geocodingResponse = ['status' => 200, 'body' => json_encode([
    'display_name' => '7, Example Street, Example Quarter, Example Town, Example Country',
    'address'      => ['house_number' => '7', 'road' => 'Example Street', 'quarter' => 'Example Quarter',
        'city'                        => 'Example Town', 'country' => 'Example Country']
], JSON_THROW_ON_ERROR)];
$precisionState = static fn (): array => json_decode($precision->GetState(), true, 512, JSON_THROW_ON_ERROR)['state']['devices'][0];
$precision->ResolveAddresses();
$check($value($precisionDevice, 'Address') === 'Example Street 7, Example Town, Example Country', 'At exactly 20 metres, compact building detail remains available.');
$check($precisionState()['addressNotice'] === '', 'Building-level results must not have a coarse warning.');

foreach ([[20.01, 17, 'street only'], [100.0, 17, 'street only'], [100.01, 14, 'area only'], [368.0, 14, 'area only'], [1000.0, 14, 'area only'], [1000.01, 10, 'city only']] as [$radius, $zoom, $level]) {
    $firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = $radius;
    $firstBoundary->broadcast();
    $before = $precisionState();
    $check(!str_contains($before['address'], '7,'), 'A precision downgrade must immediately hide a previously cached house number.');
    $precision->ResolveAddresses();
    $after = $precisionState();
    $check(str_contains($after['addressNotice'], 'Approximate') && str_contains($after['addressNotice'], $level), 'Coarse results must visibly state their detail restriction.');
    $check(str_starts_with($value($precisionDevice, 'Address'), $after['addressNotice'] . ': '), 'Device address strings must include the same approximation warning as the map.');
    $check(!str_contains($after['address'], '7,'), 'The provider cannot reintroduce a house number at coarse detail.');
    if ($zoom <= 14) {
        $check(!str_contains($after['address'], 'Example Street'), 'An area/city result must never name a specific street.');
    }
    if ($zoom === 10) {
        $check(!str_contains($after['address'], 'Example Quarter'), 'A city result must not retain neighbourhood detail.');
    }
}
$check(count($precision->geocodingRequests) === 4, 'Only a detail-tier change, not every radius change, needs a new request.');
$queries = array_map(static function (string $url): array
{
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    return $query;
}, $precision->geocodingRequests);
$check(array_column($queries, 'zoom') === ['18', '17', '14', '10'], 'The central policy must control actual Nominatim requests.');
$check(array_unique(array_column($queries, 'layer')) === ['address'], 'Reverse queries must use the address layer.');
$ipsPrecision = json_decode($attributeReader->invoke($precision, 'IPSViewTransportState'), true, 512, JSON_THROW_ON_ERROR)['state']['devices'][0];
$check($ipsPrecision === $precisionState(), 'IPSView must receive the same coarse address and notice as the tile.');

foreach ([null, 0, -1, 'invalid'] as $unknown) {
    $firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = $unknown;
    $firstBoundary->broadcast();
    $check(str_contains($precisionState()['addressNotice'], 'accuracy unknown'), 'Missing/invalid accuracy must be explicitly marked unknown.');
    $check(!str_contains($value($precisionDevice, 'Address'), 'Example Street'), 'Unknown accuracy cannot expose a street or house from cache.');
}
$check(count($precision->geocodingRequests) === 4, 'Unknown accuracy can reuse city-level data without another request.');
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 20.0;
$firstBoundary->broadcast();
$check($precisionState()['addressNotice'] === '' && str_starts_with($value($precisionDevice, 'Address'), 'Example Street 7'), 'Improved accuracy must restore cached building detail and remove the warning.');
$check(count($precision->geocodingRequests) === 4, 'Improved accuracy reuses the matching detail cache.');

// A result arriving after an accuracy-only change cannot publish stale detail.
$firstBoundary->result['devices'][0]['location']['longitude'] = 9.5;
$firstBoundary->broadcast();
$precision->duringLookup = static function () use ($firstBoundary, $precision): void
{
    $firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 368.0;
    $precision->duringLookup = null;
};
$precision->ResolveAddresses();
$check($precisionState()['address'] === '' && $value($precisionDevice, 'Address') === '', 'An in-flight building result cannot satisfy the newly coarse position.');
$precision->ResolveAddresses();
$check($precisionState()['address'] === 'Example Quarter, Example Town, Example Country', 'The next worker resolves only the now-allowed area detail.');
IPS_SetProperty($precisionMap, 'EnableAddressResolution', false);
IPS_ApplyChanges($precisionMap);
$check($precisionState()['addressNotice'] === '' && $value($precisionDevice, 'Address') === '', 'Disabling resolution removes addresses and quality notices.');
$check(!in_array('RefreshDevices', $firstBoundary->commands, true), 'Quality updates must never refresh Apple.');
echo "Accuracy-dependent address display contracts passed.\n";
