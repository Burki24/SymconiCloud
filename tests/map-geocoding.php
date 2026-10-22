<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

/** Real module and cache with only the outgoing provider boundary substituted. */
class GeocodingFixtureMap extends iCloudWoIst
{
    public array $geocodingRequests = [];
    public array $geocodingResponse = ['status' => 200, 'body' => '{"display_name":"Example Street 7, Example Town"}'];
    public ?Closure $duringLookup = null;

    protected function RequestNominatim(string $url): array
    {
        $this->geocodingRequests[] = $url;
        if ($this->duringLookup !== null) {
            ($this->duringLookup)();
        }
        return $this->geocodingResponse;
    }

    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../iCloud WoIst/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}

$mapModule = '{B67E8FEF-2144-4D93-88D0-FF6FEE4FC92F}';
$modules = $metadata->getValue();
$modules[$mapModule]['Class'] = GeocodingFixtureMap::class;
$metadata->setValue(null, $modules);
$geocodingID = IPS_CreateInstance($mapModule);
IPS_ConnectInstance($geocodingID, $firstAccount);
$geocoding = IPS\InstanceManager::getInstanceInterface($geocodingID);
$firstBoundary->result = $envelope;
$firstBoundary->commands = [];
$readGeocoding = static fn (): array => json_decode($geocoding->GetState(), true, 512, JSON_THROW_ON_ERROR);
$timerInterval = new ReflectionMethod($geocoding, 'GetTimerInterval');
IPS_ApplyChanges($geocodingID);
$check($geocoding->ResolveAddresses() === false && $geocoding->geocodingRequests === [], 'Address resolution must be disabled by default.');
$check($readGeocoding()['state']['devices'][0]['address'] === '', 'Disabled resolution exposes no address.');
$check($timerInterval->invoke($geocoding, 'ResolveAddresses') === 0, 'Disabled feature must not schedule requests.');

IPS_SetProperty($geocodingID, 'EnableAddressResolution', true);
IPS_SetProperty($geocodingID, 'NominatimServer', 'https://nominatim.example/osm');
IPS_SetProperty($geocodingID, 'EnableIPSView', true);
IPS_ApplyChanges($geocodingID);
$check($timerInterval->invoke($geocoding, 'ResolveAddresses') === 2000, 'Pending positions schedule background work.');
$geocoding->GetState();
$geocoding->GetVisualizationTile();
$geocoding->GetConfigurationForm();
$check($geocoding->geocodingRequests === [], 'Opening forms, tiles or state endpoints must never call Nominatim.');
$check($geocoding->ResolveAddresses(), 'Background worker resolves one position.');
$check($readGeocoding()['state']['devices'][0]['address'] === 'Example Street 7, Example Town', 'Address reaches the authenticated map state.');
$attributeReader = new ReflectionMethod($geocoding, 'ReadAttributeString');
$ipsView = json_decode($attributeReader->invoke($geocoding, 'IPSViewTransportState'), true, 512, JSON_THROW_ON_ERROR);
$check($ipsView['state']['devices'][0]['address'] === 'Example Street 7, Example Town', 'IPSView receives the same resolved address.');
$check($timerInterval->invoke($geocoding, 'ResolveAddresses') === 0, 'A fully cached inventory stops the worker.');
$check(!$geocoding->ResolveAddresses() && count($geocoding->geocodingRequests) === 1, 'Repeated processing reuses the persistent cache.');
$check(!str_contains($geocoding->geocodingRequests[0], 'synthetic-device'), 'Device IDs never leave Symcon.');

// Two devices at the same position share one lookup; moved devices lose old addresses.
$firstBoundary->result['devices'][] = array_replace($syntheticDevice, ['id' => 'synthetic-second']);
$geocoding->Update();
$check($readGeocoding()['state']['devices'][1]['address'] === 'Example Street 7, Example Town', 'Same coordinates share cached addresses.');
$firstBoundary->result['devices'][0]['location']['longitude'] = 8.0;
$firstBoundary->broadcast();
$check($readGeocoding()['state']['devices'][0]['address'] === '', 'Location changes must not display the previous address.');
$check($timerInterval->invoke($geocoding, 'ResolveAddresses') === 2000, 'A new location schedules a lookup.');
$geocoding->duringLookup = static function () use ($firstBoundary, $geocoding): void
{
    $firstBoundary->result['devices'][0]['location']['longitude'] = 8.5;
    $geocoding->duringLookup = null;
};
$geocoding->ResolveAddresses();
$check($readGeocoding()['state']['devices'][0]['address'] === '', 'A location changed during HTTP must not receive a stale result.');
$geocoding->geocodingResponse = ['status' => 503, 'body' => 'private provider error'];
$geocoding->ResolveAddresses();
$check($readGeocoding()['state']['success'] && IPS_GetInstance($geocodingID)['InstanceStatus'] === IS_ACTIVE, 'Provider outages must not disable the map.');
$check($timerInterval->invoke($geocoding, 'ResolveAddresses') === 0, 'Failed provider must not be hammered for remaining devices.');

IPS_SetProperty($geocodingID, 'NominatimServer', 'https://second-nominatim.example');
IPS_ApplyChanges($geocodingID);
$check($readGeocoding()['state']['devices'][1]['address'] === '', 'Changing provider must not reuse another server\'s address.');
$geocoding->geocodingResponse = ['status' => 200, 'body' => '{"display_name":"Other Example Street"}'];
$geocoding->duringLookup = static function () use ($geocodingID, $geocoding): void
{
    IPS_SetProperty($geocodingID, 'EnableAddressResolution', false);
    IPS_ApplyChanges($geocodingID);
    $geocoding->duringLookup = null;
};
$geocoding->ResolveAddresses();
$check(json_decode($attributeReader->invoke($geocoding, 'ReverseGeocodingCache'), true) === [], 'Disabling during HTTP must not recreate a private cache afterwards.');
$check($readGeocoding()['state']['devices'][0]['address'] === '', 'A response arriving after disable must remain hidden.');
IPS_SetProperty($geocodingID, 'EnableAddressResolution', true);
IPS_ApplyChanges($geocodingID);
$savedAccountState = $firstBoundary->result;
$firstBoundary->result = ['success' => false, 'state' => 'signedOut', 'updatedAt' => 0, 'devices' => [], 'error' => 'Sign in required.'];
$geocoding->Update();
$check(!$geocoding->ResolveAddresses() && $timerInterval->invoke($geocoding, 'ResolveAddresses') === 0, 'Signed-out accounts must stop background lookups.');
$firstBoundary->result = $savedAccountState;

foreach (['', 'https://nominatim.openstreetmap.org', 'https://github.com/Burki24/Nominatim'] as $invalidServer) {
    IPS_SetProperty($geocodingID, 'NominatimServer', $invalidServer);
    IPS_ApplyChanges($geocodingID);
    $check($timerInterval->invoke($geocoding, 'ResolveAddresses') === 0 && !$geocoding->ResolveAddresses(), 'Invalid server must never schedule external requests.');
    $form = json_decode($geocoding->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
    $panel = array_values(array_filter($form['elements'], static fn ($element): bool => ($element['name'] ?? '') === 'AddressResolution'))[0];
    $warning = array_values(array_filter($panel['items'], static fn ($item): bool => ($item['name'] ?? '') === 'GeocodingConfigurationWarning'))[0];
    $check($warning['visible'], 'Invalid optional configuration needs a visible warning.');
}
IPS_SetProperty($geocodingID, 'EnableAddressResolution', false);
IPS_ApplyChanges($geocodingID);
$check(json_decode($attributeReader->invoke($geocoding, 'ReverseGeocodingCache'), true) === [], 'Disabling must remove the private address cache.');
$check(!in_array('RefreshDevices', $firstBoundary->commands, true), 'Address resolution reads account cache only, never refreshes Apple.');
echo "WoIst optional background geocoding contracts passed.\n";
