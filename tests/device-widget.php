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
