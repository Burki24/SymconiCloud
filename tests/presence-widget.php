<?php

declare(strict_types=1);

ob_start();
require_once __DIR__ . '/presence-group.php';

$root = dirname(__DIR__);
$subscriptions = json_decode(file_get_contents($root . '/.helper-sync.json'), true, 512, JSON_THROW_ON_ERROR)['helpers'];
$check(!isset($subscriptions['StatusWidgetHelper']), 'The single-library widget must not subscribe to a central helper.');
$widgetTrait = new ReflectionClass(\SymconiCloud\PresenceWidget::class);
$check(realpath($widgetTrait->getFileName()) === realpath($root . '/libs/PresenceWidget.php'), 'Widget implementation must ship inside SymconiCloud.');
$locale = json_decode(file_get_contents($root . '/iCloud Presence/locale.json'), true, 512, JSON_THROW_ON_ERROR)['translations']['de'];
foreach ($widgetTrait->getConstant('PRESENCE_WIDGET_TEXT') as $caption) {
    $check(isset($locale[$caption]), 'Local widget caption must have a local German translation: ' . $caption);
}
foreach (['index.html', 'style.css', 'app.js'] as $asset) {
    $check(is_file($root . '/iCloud Presence/visualization/' . $asset), 'Widget resource must be part of the library package: ' . $asset);
}

$selection = [['deviceID' => 'alpha', 'name' => '<script>widget</script>', 'picture' => 0], ['deviceID' => 'beta', 'name' => 'Second', 'picture' => 99999]];
$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$makeDevice('alpha', 0), $makeDevice('beta', 0.002, 1700000000, 1000)];
$widgetID = IPS_CreateInstance($presenceModule);
$widget = IPS\InstanceManager::getInstanceInterface($widgetID);
IPS_ConnectInstance($widgetID, $firstAccount);
IPS_SetProperty($widgetID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_SetProperty($widgetID, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($widgetID);
$ids = IPS_GetChildrenIDs($widgetID);
$decodeWidget = static function (string $html): array
{
    if (!preg_match('#<script type="application/json" id="widget-bootstrap">(.*?)</script>#s', $html, $match)) {
        throw new RuntimeException('Widget bootstrap missing.');
    }
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
};
$readAttribute = new ReflectionMethod($widget, 'ReadAttributeString');
$native = $decodeWidget($widget->GetVisualizationTile());
$check($native['state']['summary']['tone'] === 'positive' && count($native['state']['members']) === 2, 'Compact widget shows aggregate and selected members.');
$check($native['state']['members'][1]['tone'] === 'unknown' && $native['state']['members'][1]['detail'] === '', 'Unknown GPS must not display fabricated distance or duration.');
$check($native['state']['members'][1]['image'] === '', 'Missing pictures fall back safely.');
$check(!str_contains($widget->GetVisualizationTile(), '<script>widget</script>'), 'Device labels cannot escape bootstrap JSON.');
$check(!str_contains(json_encode($native), 'latitude') && !str_contains(json_encode($native), 'alpha'), 'Widget state contains opaque IDs and no coordinates or account IDs.');
$check($native['options']['tileHeaderInset'] === 58 && $native['options']['transport'] === null, 'Native widget reserves its heading and uses SDK pushes.');
$firstBoundary->commands = [];
$widget->GetVisualizationTile();
$widget->GetIPSViewHTML();
$check($firstBoundary->commands === [], 'Rendering widgets never reads or refreshes Apple.');
$form = json_decode($widget->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
$fields = [];
$walk = static function (array $items) use (&$walk, &$fields): void
{
    foreach ($items as $item) {
        if (isset($item['name'])) {
            $fields[$item['name']] = $item;
        }
        $walk($item['items'] ?? []);
    }
};
$walk($form['elements']);
foreach (['VisualizationThemeTextColor', 'VisualizationThemeBackgroundColor', 'IPSViewStyleViewBackgroundColor', 'TileWidgetPhotos', 'IPSViewWidgetPhotos', 'TileWidgetSpacing', 'IPSViewWidgetSpacing'] as $field) {
    $check(isset($fields[$field]), 'Independent designers must expose ' . $field);
}
$check($form['elements'][1]['columns'][2]['edit']['type'] === 'SelectMedia', 'Pictures use the documented media selector.');
IPS_SetProperty($widgetID, 'EnableIPSView', true);
IPS_SetProperty($widgetID, 'IPSViewWidgetPhotos', false);
IPS_ApplyChanges($widgetID);
$webID = IPS_GetObjectIDByIdent('IPSViewPresence', $widgetID);
$html = GetValue($webID);
$web = $decodeWidget($html);
$check($web['options']['tileHeaderInset'] === 0 && !$web['options']['design']['photos'] && $decodeWidget($widget->GetVisualizationTile())['options']['design']['photos'], 'IPSView has independent design and no native heading reservation.');
$token = $readAttribute->invoke($widget, 'StatusWidgetToken');
$check(strlen($token) === 64, 'IPSView uses a per-instance authorization token.');
$hook = new ReflectionMethod($widget, 'ProcessHookData');
$request = static function (string $method, string $credential, string $suffix = '') use ($hook, $widget, $widgetID): array
{
    $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/hook/SymconStatusWidget' . $widgetID . $suffix, 'HTTP_X_STATUS_WIDGET_TOKEN' => $credential];
    ob_start();
    $hook->invoke($widget);
    return [http_response_code(), ob_get_clean()];
};
$check($request('POST', '')[0] === 403 && $request('GET', $token)[0] === 405 && $request('POST', $token, '?token=x')[0] === 404, 'Cache endpoint rejects unauthenticated, query and non-POST requests.');
$check($request('OPTIONS', '')[0] === 204 && $request('POST', $token)[0] === 200, 'CORS preflight and authenticated cache request work.');
$oldIdentity = $readAttribute->invoke($widget, 'SourceIdentity');
$oldStates = $readAttribute->invoke($widget, 'MemberStates');
$selection[0]['picture'] = 99999;
IPS_SetProperty($widgetID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_SetProperty($widgetID, 'TileWidgetSpacing', 20);
IPS_ApplyChanges($widgetID);
$check($readAttribute->invoke($widget, 'SourceIdentity') === $oldIdentity && $readAttribute->invoke($widget, 'MemberStates') === $oldStates, 'Picture/design changes cannot reset GPS confirmation or observed stays.');
$firstBoundary->commands = [];
$widget->clock += 15;
$widget->Update();
$check(GetValue($webID) === $html && $request('POST', $token)[0] === 200, 'Data updates keep IPSView HTML and token stable.');
$check(array_values(array_diff(IPS_GetChildrenIDs($widgetID), [$webID])) === $ids, 'Existing variable IDs survive widget activation and updates.');
$check($firstBoundary->commands === ['GetDevices'], 'Only the regular evaluation reads the account cache; polling reads no account data.');
IPS_SetProperty($widgetID, 'EnableIPSView', false);
IPS_ApplyChanges($widgetID);
$check($request('POST', $token)[0] === 404 && IPS_VariableExists($webID) && $readAttribute->invoke($widget, 'StatusWidgetToken') === '', 'Disabling output revokes access and retains the variable.');
IPS_SetProperty($widgetID, 'EnableIPSView', true);
IPS_SetProperty($widgetID, 'IPSViewServerURL', 'https://example.invalid/path');
IPS_ApplyChanges($widgetID);
$check($request('POST', $token)[0] === 404, 'Invalid explicit server origins disable transport.');
$_SERVER = [];
ob_end_clean();
echo "Presence widget model, independent designers, media selection, transport and state preservation passed.\n";
