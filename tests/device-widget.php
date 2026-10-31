<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/modules.php';

$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$syntheticDevice];
$widgetID = $createDevice($firstAccount);
$widget = IPS\InstanceManager::getInstanceInterface($widgetID);
$read = static function () use ($widget): array
{
    $html = $widget->GetVisualizationTile();
    if (!preg_match('#<script type="application/json" id="device-bootstrap">(.*?)</script>#s', $html, $match)) {
        throw new RuntimeException('Device tile must provide location and sound controls.');
    }
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
};
$children = IPS_GetChildrenIDs($widgetID);
$firstBoundary->commands = [];
$initial = $read();
$check($initial['state']['location']['latitude'] === 51.25 && $initial['state']['location']['address'] === '', 'Without address resolution, show the known coordinates.');
$check($initial['state']['location']['timestamp'] === 1700000000 && $initial['options']['tileHeaderInset'] === 58, 'Use the location timestamp and reserve the native tile heading.');
$check($initial['state']['canPlaySound'] && $firstBoundary->commands === [], 'Rendering is local and never sends a sound or Apple request.');

// Historical snapshots from before accuracy availability existed used zero for missing accuracy.
$snapshotID = $createDevice($firstAccount);
$snapshotDevice = IPS\InstanceManager::getInstanceInterface($snapshotID);
$snapshotPayload = static function () use ($snapshotDevice): array
{
    preg_match('#<script type="application/json" id="device-bootstrap">(.*?)</script>#s', $snapshotDevice->GetVisualizationTile(), $match);
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR)['state']['location'];
};
$setSnapshotValue = static fn (string $ident, mixed $value) => (new ReflectionMethod($snapshotDevice, 'SetValue'))->invoke($snapshotDevice, $ident, $value);
$historical = ['latitude' => 51.25, 'longitude' => 7.5, 'accuracy' => 0, 'timestamp' => 1700000000, 'address' => 'Synthetic historical address'];
foreach ([0, 12.5, null] as $oldAccuracy) {
    $historical['accuracy'] = $oldAccuracy;
    $saved = $snapshotDevice->GetPersistence();
    $saved['attributes']['WidgetLocation'] = json_encode($historical, JSON_THROW_ON_ERROR);
    $snapshotDevice->ApplyPersistence($saved);
    $setSnapshotValue('LocationAvailable', false);
    IPS_DeleteVariable(IPS_GetObjectIDByIdent('AccuracyAvailable', $snapshotID));
    $oldObjects = [];
    foreach (IPS_GetChildrenIDs($snapshotID) as $objectID) {
        $oldObjects[$objectID] = IPS_GetObject($objectID);
    }
    $firstBoundary->commands = [];
    $upgraded = $snapshotPayload(); // No ApplyChanges or account update has happened.
    $expected = $historical;
    $expected['accuracy'] = $oldAccuracy === 0 ? null : $oldAccuracy;
    $check($upgraded === $expected, 'Legacy zero is ambiguous; positive accuracy, coordinates, time and matching address must survive.');
    $afterMigration = $snapshotDevice->GetPersistence()['attributes']['WidgetLocation'];
    $snapshotDevice->ReceiveData('{invalid');
    $check($snapshotPayload() === $expected, 'Unavailable account data must not resurrect an ambiguous historical zero.');
    $check($snapshotDevice->GetPersistence()['attributes']['WidgetLocation'] === $afterMigration, 'Snapshot migration must be idempotent.');
    foreach ($oldObjects as $objectID => $object) {
        $check(IPS_GetObject($objectID) === $object, 'Snapshot upgrades must preserve existing variable metadata.');
    }
    $check($firstBoundary->commands === [], 'Reading/migrating a snapshot must not query the account.');
}
// Genuine new zero survives unavailable data and persistence reload, unlike the old sentinel.
$firstBoundary->result['devices'] = [$syntheticDevice];
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 0.0;
$snapshotDevice->Update();
$check($snapshotPayload()['accuracy'] === 0.0 || $snapshotPayload()['accuracy'] === 0, 'A new confirmed zero must remain known.');
$snapshotDevice->ReceiveData('{invalid');
$savedZero = $snapshotDevice->GetPersistence();
$snapshotDevice->ApplyPersistence($savedZero);
$zeroHistory = $snapshotPayload();
$check($zeroHistory['accuracy'] === 0.0 || $zeroHistory['accuracy'] === 0, 'A genuine zero must survive location loss and a persistence reload.');
$check(array_keys($zeroHistory) === array_keys($historical), 'Internal provenance must not change the public location payload shape.');
// Prior releases can also have an unmarked zero proven by the still-current availability flag.
$snapshotDevice->Update();
$unmarked = $snapshotDevice->GetPersistence();
$unmarked['attributes']['WidgetLocation'] = json_encode(array_replace($historical, ['accuracy' => 0]), JSON_THROW_ON_ERROR);
$snapshotDevice->ApplyPersistence($unmarked);
$check($snapshotPayload()['accuracy'] === 0 || $snapshotPayload()['accuracy'] === 0.0, 'An unmarked zero matching the available current measurement must survive upgrade.');
$snapshotDevice->ReceiveData('{invalid');
$check($snapshotPayload()['accuracy'] === 0 || $snapshotPayload()['accuracy'] === 0.0, 'A verified old zero keeps its provenance when the account is later unavailable.');
$firstBoundary->result['devices'][0]['location']['latitude'] = 0.0;
$firstBoundary->result['devices'][0]['location']['longitude'] = 0.0;
$snapshotDevice->Update();
$currentZero = $snapshotPayload();
$unmarked = $snapshotDevice->GetPersistence();
$unmarked['attributes']['WidgetLocation'] = json_encode($currentZero, JSON_THROW_ON_ERROR);
$snapshotDevice->ApplyPersistence($unmarked);
$check($snapshotPayload()['accuracy'] === 0 || $snapshotPayload()['accuracy'] === 0.0, 'JSON integer zero coordinates must match the floating-point current measurement.');
foreach (['timestamp', 'latitude'] as $differentField) {
    $different = $currentZero;
    $different[$differentField]++;
    $unmarked['attributes']['WidgetLocation'] = json_encode($different, JSON_THROW_ON_ERROR);
    $snapshotDevice->ApplyPersistence($unmarked);
    $check($snapshotPayload()['accuracy'] === null, 'Current zero accuracy must not validate a different historical measurement.');
}
$firstBoundary->result['devices'] = [$syntheticDevice];
$firstBoundary->commands = [];

(new ReflectionMethod($widget, 'SetValue'))->invoke($widget, 'Address', 'Example Street 7, Example City');
$firstBoundary->result['devices'][0]['location'] = null;
// The widget snapshot is captured before availability changes; address still belongs to the old coordinates.
$widget->ReceiveData(json_encode(['DataID' => '{FCE7780F-9520-4E52-92F3-60034FFAED6D}'] + $firstBoundary->result, JSON_THROW_ON_ERROR));
$missing = $read();
$check(!$missing['state']['locationAvailable'] && $missing['state']['location']['latitude'] === 51.25, 'Missing GPS retains a clearly historical position.');
$check($missing['state']['location']['address'] === 'Example Street 7, Example City', 'The last address must remain tied to its own coordinates.');

$firstBoundary->result['devices'] = [$syntheticDevice];
$firstBoundary->result['devices'][0]['location']['latitude'] = 0.0;
$firstBoundary->result['devices'][0]['location']['longitude'] = 0.0;
$firstBoundary->result['devices'][0]['name'] = '</script><script>unsafe</script>';
$widget->Update();
$zero = $read();
$check($zero['state']['location']['latitude'] === 0.0 && $zero['state']['location']['address'] === '', 'Valid zero coordinates are not missing; a new position cannot inherit an old address.');
$check(!str_contains($widget->GetVisualizationTile(), '</script><script>unsafe'), 'Private device text is encoded as data, never HTML.');
$widget->ReceiveData('{invalid');
$check(!$read()['state']['canPlaySound'] && $read()['state']['location'] !== null, 'Unavailable account retains history but disables the sound control.');
$persistence = $widget->GetPersistence();
$persistence['attributes']['WidgetLocation'] = 'null';
$widget->ApplyPersistence($persistence);
$widget->ReceiveData('{invalid');
$check($read()['state']['location']['timestamp'] === 1700000000, 'After updating an older module, retained timestamped coordinates seed historical display even when current GPS is unavailable.');
$widget->RequestAction('GetState', '');
$check($children === IPS_GetChildrenIDs($widgetID), 'The widget never changes existing variable IDs.');
$before = count($firstBoundary->commands);
IPS_SetProperty($widgetID, 'DeviceID', 'other-device');
IPS_ApplyChanges($widgetID);
$check($read()['state']['location'] === null && $read()['state']['name'] === '', 'Changing the device clears the previous device location and name.');
$check(count($firstBoundary->commands) === $before + 1, 'Changing configuration only reads the local account cache.');
IPS_SetProperty($widgetID, 'DeviceID', 'synthetic-device');
IPS_ApplyChanges($widgetID);
$check($read()['state']['location'] !== null, 'The original account can supply a new matching location.');
IPS_DisconnectInstance($widgetID);
$secondBoundary->result['devices'] = [];
IPS_ConnectInstance($widgetID, $secondAccount);
IPS_ApplyChanges($widgetID);
$check($read()['state']['location'] === null, 'Account changes also clear historical data even for an equal device ID.');
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Device/locale.json'), true)['translations']['de'];
foreach ((new ReflectionClass(\SymconiCloud\DeviceWidget::class))->getConstant('DEVICE_WIDGET_TEXT') as $key) {
    $check(isset($locale[$key]), 'All visible device widget labels need German translations: ' . $key);
}
ob_end_clean();
echo "Device tile location, history, identity and no-side-effect checks passed.\n";
