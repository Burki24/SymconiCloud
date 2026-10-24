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
foreach (['ipsview-page.html', 'ipsview-page.js'] as $asset) {
    $check(is_file($root . '/libs/visualization/' . $asset), 'Shared library frame resource must ship with the module: ' . $asset);
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
$deviceForm = array_column($form['elements'][1]['form'], null, 'name');
$check($deviceForm['pictureUpload']['type'] === 'SelectFile' && $deviceForm['pictureUpload']['extensions'] === '.png,.jpg,.jpeg,.gif,.webp', 'The device dialog must accept a local console image file.');
$check($deviceForm['deviceID']['options'] === $form['elements'][1]['columns'][0]['edit']['options'], 'Device selection retains cached choices in the custom row dialog.');
$check($form['elements'][1]['columns'][4]['visible'] === false && $form['elements'][1]['columns'][4]['save'] === true, 'Uploaded bytes must persist without appearing in the device table.');
$check(isset($deviceForm['PictureUploadError']) && !$deviceForm['PictureUploadError']['visible'], 'A picture draft needs a dedicated inline error field.');
foreach (['pictureSource', 'pictureUpload'] as $field) {
    $check($deviceForm[$field]['onChange'] === 'ICPR_ValidatePictureUpload($id, $pictureSource, $pictureUpload);', 'Both changing the source and the file must validate the current draft.');
}
$draftConfiguration = IPS_GetConfiguration($widgetID);
$draftIDs = IPS_GetChildrenIDs($widgetID);
$firstBoundary->commands = [];
ICPR_ValidatePictureUpload($widgetID, 'upload', base64_encode(str_repeat('x', 203100)));
$check($widget->fieldUpdates['PictureUploadError']['visible'] && str_contains($widget->fieldUpdates['PictureUploadError']['caption'], '128 KiB'), 'The reported oversized file must show a useful correction immediately.');
ICPR_ValidatePictureUpload($widgetID, 'upload', 'not an image');
$check($widget->fieldUpdates['PictureUploadError']['visible'] && str_contains($widget->fieldUpdates['PictureUploadError']['caption'], 'PNG'), 'Malformed files must explain accepted image formats.');
foreach ([['media', 'invalid'], ['upload', ''], ['upload', 'R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=']] as [$source, $content]) {
    ICPR_ValidatePictureUpload($widgetID, $source, $content);
    $check(!$widget->fieldUpdates['PictureUploadError']['visible'] && $widget->fieldUpdates['PictureUploadError']['caption'] === '', 'Selecting media, removing the file or choosing a valid file clears obsolete errors.');
}
$check(IPS_GetConfiguration($widgetID) === $draftConfiguration && IPS_GetChildrenIDs($widgetID) === $draftIDs && $firstBoundary->commands === [], 'Picture validation must not save configuration, alter variables or contact the account.');
IPS_SetProperty($widgetID, 'EnableIPSView', true);
IPS_SetProperty($widgetID, 'IPSViewWidgetPhotos', false);
IPS_ApplyChanges($widgetID);
$webID = IPS_GetObjectIDByIdent('IPSViewPresence', $widgetID);
$html = GetValue($webID);
$check(str_contains($html, 'id="ipsview-page"') && !str_contains($html, 'id="members"'), 'IPSView embeds a real HTTP page, not an opaque data document with cross-origin polling.');
$hook = new ReflectionMethod($widget, 'ProcessHookData');
$request = static function (string $method, string $credential, string $suffix = '') use ($hook, $widget, $widgetID): array
{
    $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/hook/SymconStatusWidget' . $widgetID . $suffix, 'HTTP_X_STATUS_WIDGET_TOKEN' => $credential];
    ob_start();
    $hook->invoke($widget);
    return [http_response_code(), ob_get_clean()];
};
[$pageStatus, $page] = $request('GET', '', '/page');
$check($pageStatus === 200, 'Public HTTP shell is available while output is enabled.');
$shell = $decodeWidget($page);
$check($shell['state']['members'] === [] && !str_contains($page, '<script>widget</script>'), 'Public HTTP shell exposes no device data.');
$web = json_decode($request('POST', $readAttribute->invoke($widget, 'StatusWidgetToken'))[1], true, 512, JSON_THROW_ON_ERROR);
$check($web['options']['tileHeaderInset'] === 0 && !$web['options']['design']['photos'] && $decodeWidget($widget->GetVisualizationTile())['options']['design']['photos'], 'IPSView has independent design and no native heading reservation.');
$token = $readAttribute->invoke($widget, 'StatusWidgetToken');
$check(strlen($token) === 64, 'IPSView uses a per-instance authorization token.');
$check(str_contains($html, 'SymconVisualizationInteraction') && str_contains($html, 'SymconHTTPOrigin'), 'Outer data frame uses the central origin and wheel-forwarding helpers.');
$check(!str_contains($page, $token) && $shell['options']['transport']['fragmentToken'] === true, 'Token travels only in the frame fragment, never in a public page response.');
$check($request('POST', $token, '/page')[0] === 405 && $request('GET', '', '/page?token=x')[0] === 404, 'Page route accepts only the exact GET path.');
$check($request('POST', '')[0] === 403 && $request('GET', $token)[0] === 405 && $request('POST', $token, '?token=x')[0] === 404, 'Cache endpoint rejects unauthenticated, query and non-POST requests.');
$check($request('OPTIONS', '')[0] === 204 && $request('POST', $token)[0] === 200, 'Hook accepts OPTIONS and authenticated POST; the browser uses the same-origin HTTP page.');
$oldIdentity = $readAttribute->invoke($widget, 'SourceIdentity');
$oldStates = $readAttribute->invoke($widget, 'MemberStates');
$selection[0]['picture'] = 99999;
$selection[0]['pictureSource'] = 'upload';
$selection[0]['pictureUpload'] = 'R0lGODlhAQABAIAAAAUEBAAAACwAAAAAAQABAAACAkQBADs=';
IPS_SetProperty($widgetID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_SetProperty($widgetID, 'TileWidgetSpacing', 20);
IPS_ApplyChanges($widgetID);
$check($readAttribute->invoke($widget, 'SourceIdentity') === $oldIdentity && $readAttribute->invoke($widget, 'MemberStates') === $oldStates, 'Picture/design changes cannot reset GPS confirmation or observed stays.');
$expectedImage = 'data:image/gif;base64,' . $selection[0]['pictureUpload'];
$check($decodeWidget($widget->GetVisualizationTile())['state']['members'][0]['image'] === $expectedImage, 'A local upload renders in the native widget independently of any media ID.');
$check(json_decode($request('POST', $token)[1], true)['state']['members'][0]['image'] === $expectedImage, 'Authenticated IPSView data carries the same validated image.');
$check(!str_contains($request('GET', '', '/page')[1], $selection[0]['pictureUpload']), 'The public HTTP shell must not disclose uploaded pictures.');
$selection[0]['pictureUpload'] = base64_encode('<svg onload="alert(1)"></svg>');
IPS_SetProperty($widgetID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($widgetID);
$check($decodeWidget($widget->GetVisualizationTile())['state']['members'][0]['image'] === '', 'Invalid uploaded content falls back to initials.');
$selection[0]['pictureUpload'] = substr($expectedImage, strlen('data:image/gif;base64,'));
$selection[0]['pictureSource'] = 'media';
IPS_SetProperty($widgetID, 'Devices', json_encode($selection, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($widgetID);
$check($decodeWidget($widget->GetVisualizationTile())['state']['members'][0]['image'] === '', 'Switching to media stops using a previously uploaded picture.');
$firstBoundary->commands = [];
$widget->clock += 15;
$widget->Update();
$check(GetValue($webID) === $html && $request('POST', $token)[0] === 200, 'Data updates keep IPSView HTML and token stable.');
$check(array_values(array_diff(IPS_GetChildrenIDs($widgetID), [$webID])) === $ids, 'Existing variable IDs survive widget activation and updates.');
$check($firstBoundary->commands === ['GetDevices'], 'Only the regular evaluation reads the account cache; polling reads no account data.');
IPS_SetProperty($widgetID, 'EnableIPSView', false);
IPS_ApplyChanges($widgetID);
$check($request('POST', $token)[0] === 404 && IPS_VariableExists($webID) && $readAttribute->invoke($widget, 'StatusWidgetToken') === '', 'Disabling output revokes access and retains the variable.');
$check($request('GET', '', '/page')[0] === 404, 'Disabling output also removes the public HTTP page.');
IPS_SetProperty($widgetID, 'EnableIPSView', true);
IPS_SetProperty($widgetID, 'IPSViewServerURL', 'https://example.invalid/path');
IPS_ApplyChanges($widgetID);
$check($request('POST', $token)[0] === 404, 'Invalid explicit server origins disable transport.');
$_SERVER = [];
ob_end_clean();
echo "Presence widget model, independent designers, media selection, transport and state preservation passed.\n";
