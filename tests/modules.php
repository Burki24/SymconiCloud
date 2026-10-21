<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/autoload.php';

/** Synthetic account boundary; child modules and kernel data flow remain real. */
class WoIstFixtureAccount extends IPSModuleStrict
{
    /** @var array<string, mixed> */
    public array $result = ['success' => false, 'state' => 'signedOut', 'updatedAt' => 0, 'devices' => [], 'error' => 'Sign in required.'];
    /** @var list<string> */
    public array $commands = [];

    public function ForwardData(string $JSONString): string
    {
        $request = json_decode($JSONString, true, 512, JSON_THROW_ON_ERROR);
        $this->commands[] = $request['Command'];
        if (!in_array($request['Command'], ['GetDevices', 'RefreshDevices'], true)) {
            throw new RuntimeException('Unexpected account command.');
        }
        return json_encode($this->result, JSON_THROW_ON_ERROR);
    }

    public function broadcast(): void
    {
        $this->SendDataToChildren(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $this->result, JSON_THROW_ON_ERROR));
    }
}

$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$value = static fn (int $id, string $ident): mixed => GetValue(IPS_GetObjectIDByIdent($ident, $id));
$accountModule = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
$configuratorModule = '{E9D7CB55-7E98-46BF-BC8D-6D384870D9AC}';
$deviceModule = '{DEA72AD7-7877-4392-B64F-055B63D1E5C4}';

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');

// Substitute only the account transport boundary in the official loader metadata.
// Production children, manifests, instance construction and physical routing are unchanged.
$metadata = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $metadata->getValue();
$check(isset($modules[$accountModule]), 'The real library must include its account module.');
$modules[$accountModule]['Class'] = WoIstFixtureAccount::class;
$metadata->setValue(null, $modules);

$firstAccount = IPS_CreateInstance($accountModule);
$secondAccount = IPS_CreateInstance($accountModule);
$firstBoundary = IPS\InstanceManager::getInstanceInterface($firstAccount);
$secondBoundary = IPS\InstanceManager::getInstanceInterface($secondAccount);
$syntheticDevice = [
    'id'           => 'synthetic-device', 'name' => 'Synthetic phone', 'model' => 'Phone', 'deviceClass' => 'iPhone',
    'deviceStatus' => 200, 'batteryLevel' => 0.42, 'batteryStatus' => 'NotCharging',
    'location'     => ['latitude' => 51.25, 'longitude' => 7.5, 'horizontalAccuracy' => 12.5, 'timestamp' => 1700000000, 'isOld' => false]
];
$envelope = ['success' => true, 'state' => 'authenticated', 'updatedAt' => 1700000100, 'devices' => [$syntheticDevice], 'error' => ''];
$firstBoundary->result = $envelope;
$secondBoundary->result = $envelope;

$createDevice = static function (int $account) use ($deviceModule): int
{
    $id = IPS_CreateInstance($deviceModule);
    IPS_ConnectInstance($id, $account);
    IPS_SetProperty($id, 'DeviceID', 'synthetic-device');
    IPS_ApplyChanges($id);
    return $id;
};
$firstDevice = $createDevice($firstAccount);
$secondDevice = $createDevice($secondAccount);
$configurator = IPS_CreateInstance($configuratorModule);
IPS_ConnectInstance($configurator, $firstAccount);
IPS_ApplyChanges($configurator);
$configuratorInterface = IPS\InstanceManager::getInstanceInterface($configurator);
// Official SDK contract: an object with connect/moduleIDs, not a GUID list.
// Direct IPS_ConnectInstance calls in the stubs do not validate the console wizard contract.
foreach ([$configuratorInterface, IPS\InstanceManager::getInstanceInterface($firstDevice)] as $child) {
    $parents = json_decode($child->GetCompatibleParents(), false, 512, JSON_THROW_ON_ERROR);
    $check($parents instanceof stdClass, 'Parent selection requires a JSON object, not a GUID list.');
    $check(($parents->type ?? '') === 'connect', 'Parent selection must allow sharing an existing account.');
    $check(($parents->moduleIDs ?? []) === [$accountModule], 'Parent selection must reference the account module.');
}
$firstBoundary->commands = [];
$form = json_decode(IPS_GetConfigurationForm($configurator), true, 512, JSON_THROW_ON_ERROR);
$rows = $form['elements'][0]['values'];
$check(count($rows) === 1 && $rows[0]['instanceID'] === $firstDevice, 'Same device ID on another account must not be matched.');
$check($rows[0]['instanceID'] !== $secondDevice, 'Physical account connection is part of device identity.');
$check($rows[0]['create']['moduleID'] === $deviceModule && $rows[0]['create']['configuration'] === ['DeviceID' => 'synthetic-device'], 'Creation recipe must create only a device using the configurator parent.');
$check($firstBoundary->commands === ['GetDevices'], 'Opening the form must issue only a cache read.');
$check(ICWC_Reload($configurator), 'Reload must accept a populated authenticated cache.');
$check($firstBoundary->commands === ['GetDevices', 'GetDevices'], 'Reload must not start an Apple refresh.');
$check(ICWC_RefreshDevices($configurator), 'Explicit refresh must return its account result.');
$check(end($firstBoundary->commands) === 'RefreshDevices', 'Explicit refresh must send its dedicated command.');

$check($value($firstDevice, 'DataAvailable') === true && $value($firstDevice, 'Online') === true, 'Successful cache read must populate availability.');
$check($value($firstDevice, 'BatteryLevel') === 42.0, 'Normalized battery fraction must become percent.');
$check($value($firstDevice, 'Latitude') === 51.25 && $value($firstDevice, 'Longitude') === 7.5, 'Location coordinates must be preserved accurately.');
$check($value($firstDevice, 'LocationTimestamp') === 1700000000, 'Location timestamp must retain Apple time, not request time.');
$variable = IPS_GetVariable(IPS_GetObjectIDByIdent('BatteryLevel', $firstDevice));
$check(($variable['VariableCustomAction'] ?? 0) === 0, 'Device variables must have no write action.');
$check(IPS_GetVariablePresentation(IPS_GetObjectIDByIdent('BatteryLevel', $firstDevice))['PRESENTATION'] === VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'Battery must use a native value presentation.');

$firstBoundary->result['updatedAt'] = 1700000200;
$firstBoundary->result['devices'][0]['location'] = null;
$firstBoundary->result['devices'][0]['batteryLevel'] = null;
$firstBoundary->broadcast();
$check($value($firstDevice, 'DataAvailable') === true, 'Device data may be available without a location.');
$check($value($firstDevice, 'LocationAvailable') === false && $value($firstDevice, 'LocationIsOld') === true, 'Missing location must be marked unavailable and old.');
$check($value($firstDevice, 'Latitude') === 51.25 && $value($firstDevice, 'LocationTimestamp') === 1700000000, 'Missing location must not zero prior coordinates or timestamp.');
$check($value($firstDevice, 'BatteryAvailable') === false && $value($firstDevice, 'BatteryLevel') === 42.0, 'Missing battery must retain its last value with availability false.');
$check($value($secondDevice, 'LocationAvailable') === true, 'Account notifications must not reach another account device.');

$firstBoundary->result = ['success' => false, 'state' => 'expired', 'updatedAt' => 1700000200, 'devices' => [$syntheticDevice], 'error' => 'Session expired.'];
$firstBoundary->broadcast();
$check($value($firstDevice, 'DataAvailable') === false && $value($firstDevice, 'Online') === false, 'Expired account must not present cached values as available.');
$check($value($firstDevice, 'Latitude') === 51.25 && $value($firstDevice, 'LastUpdate') === 1700000200, 'Account errors must retain old location and last successful refresh time.');
$form = json_decode(IPS_GetConfigurationForm($configurator), true, 512, JSON_THROW_ON_ERROR);
$check(!isset($form['elements'][0]['values'][0]['create']), 'Failure cache must not advertise a device creation recipe.');
$check($form['actions'][0]['caption'] === 'Session expired.', 'Account error must be visible in the configurator.');

$deviceInterface = IPS\InstanceManager::getInstanceInterface($firstDevice);
$deviceInterface->ReceiveData('{invalid json');
$check(IPS_GetInstance($firstDevice)['InstanceStatus'] === 202 && $value($firstDevice, 'DataAvailable') === false, 'Malformed flow must fail closed.');
$configuratorInterface->ReceiveData(json_encode(['DataID' => '{00000000-0000-0000-0000-000000000000}', 'success' => true], JSON_THROW_ON_ERROR));
$check(IPS_GetInstance($configurator)['InstanceStatus'] === 202, 'Unexpected DataID must not be accepted.');

$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [];
$check(!ICWD_Update($firstDevice) && IPS_GetInstance($firstDevice)['InstanceStatus'] === 203, 'Missing device must become unavailable.');
IPS_SetProperty($firstDevice, 'DeviceID', 'different-synthetic-device');
IPS_ApplyChanges($firstDevice);
$check($value($firstDevice, 'Latitude') === 0.0 && $value($firstDevice, 'LocationTimestamp') === 0, 'Changing identity must not preserve another device location.');

echo "Module integration checks passed.\n";
