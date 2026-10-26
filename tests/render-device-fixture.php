<?php

declare(strict_types=1);

// Local browser fixture: production renderer, synthetic account and inert sound transport.
ob_start();
require __DIR__ . '/modules.php';
ob_end_clean();
$firstBoundary->result = $envelope;
$syntheticDevice['name'] = 'Beispieltelefon';
$syntheticDevice['location']['timestamp'] = time() - 120;
$firstBoundary->result['devices'] = [$syntheticDevice];
$widgetID = $createDevice($firstAccount);
$widget = IPS\InstanceManager::getInstanceInterface($widgetID);
$html = $widget->GetVisualizationTile();
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Device/locale.json'), true)['translations']['de'];
$html = preg_replace_callback('#(<script type="application/json" id="device-bootstrap">)(.*?)(</script>)#s', static function (array $parts) use ($locale): string
{
    $payload = json_decode($parts[2], true, 512, JSON_THROW_ON_ERROR);
    $payload['translations'] = $locale;
    if (isset($_GET['tile'])) {
        $payload['state']['location']['address'] = 'Beispielstraße 7, 12345 Musterstadt, Deutschland';
    }
    return $parts[1] . json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . $parts[3];
}, $html);
$mock = <<<'HTML'
<script>
window.addEventListener('error', event => {
    const show = () => {
        const output = document.createElement('pre'); output.id = 'fixture-error';
        output.textContent = String(event.error && event.error.stack || event.message); document.body.appendChild(output);
    };
    if (document.body) show(); else document.addEventListener('DOMContentLoaded', show, {once:true});
});
window.fixtureCommands = [];
window.requestAction = function (ident, value) {
    window.fixtureCommands.push([ident, value]);
    if (ident === 'PlaySound') setTimeout(() => {
        const next = JSON.parse(document.getElementById('device-bootstrap').textContent);
        next.state.soundRevision = 1; next.state.result = 'Synthetischer Tonauftrag angenommen. Kein Versand.';
        window.handleMessage(next);
    }, 100);
};
window.addEventListener('message', event => {
    if (event.source !== parent || !['address', 'coordinates', 'missing', 'empty', 'update', 'dark', 'light'].includes(event.data)) return;
    if (event.data === 'dark' || event.data === 'light') {
        document.documentElement.style.colorScheme = event.data; return;
    }
    const next = JSON.parse(document.getElementById('device-bootstrap').textContent);
    if (event.data === 'address' || event.data === 'update') {
        next.state.location.address = 'Beispielstraße 7, 12345 Musterstadt, Deutschland';
    }
    if (event.data === 'missing') { next.state.locationAvailable = false; next.state.location.timestamp -= 7200; }
    if (event.data === 'empty') { next.state.location = null; next.state.canPlaySound = false; }
    window.handleMessage(next);
});
</script>
HTML;
$html = str_replace('</head>', $mock . '</head>', $html);
$html = str_replace('<body>', '<body><div style="position:fixed;top:16px;left:12px;z-index:2">iCloud Gerät</div>', $html);
if (isset($_GET['tile'])) {
    echo str_replace('</head>', '<style>html,body {width:420px;height:400px;color-scheme:light}</style></head>', $html);
    return;
}
?><!doctype html>
<html lang="de"><meta charset="utf-8"><title>Device widget browser fixture</title>
<style>body {font:16px sans-serif;margin:20px} iframe {display:block;width:420px;max-width:100%;height:400px;border:1px solid #aaa;margin-top:12px} button{margin:4px;padding:8px}</style>
<p>Synthetische Geräte-Kachel. Keine Verbindung zu Apple oder einem Symcon-System.</p>
<?php foreach (['address' => 'Adresse', 'coordinates' => 'Koordinaten', 'missing' => 'Veraltet', 'empty' => 'Kein Standort', 'update' => 'Datenupdate', 'dark' => 'Dunkel', 'light' => 'Hell'] as $action => $label) { ?>
<button type="button" data-action="<?php echo $action; ?>"><?php echo $label; ?></button>
<?php } ?>
<button type="button" id="small">Kleine Kachel</button><button type="button" id="large">Große Kachel</button>
<iframe title="Gerätekachel" srcdoc="<?php echo htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></iframe>
<script>
const frame = document.querySelector('iframe');
document.querySelectorAll('[data-action]').forEach(button => button.onclick = () => frame.contentWindow.postMessage(button.dataset.action, '*'));
document.getElementById('small').onclick = () => { frame.style.width = '280px'; frame.style.height = '230px'; };
document.getElementById('large').onclick = () => { frame.style.width = '640px'; frame.style.height = '520px'; };
</script></html>
