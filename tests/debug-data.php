<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/contact-transfer.php';
ob_end_clean();

$debug = (new ReflectionProperty(IPS\DebugServer::class, 'messages'))->getValue();
foreach ($debug as $entries) {
    foreach ($entries as $entry) {
        $check(preg_match('/^\w+ \| (START|ENDE|AKTION|SENDEN|EMPFANG|FEHLER) \| [^:]+:\d+$/D', $entry['Message']) === 1, 'Recipient decisions must name the module function, not its closure frame.');
    }
}
$output = json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
foreach (['PRIVATE-', 'private-user@example.invalid', 'alex@example.invalid', '+49 170 1234567', 'Synthetic phone', 'private-album'] as $private) {
    $check(!str_contains($output, $private), 'Debug must exclude contact, recipient and session contents: ' . $private);
}
$sources = glob(dirname(__DIR__) . '/*/module.php');
foreach (glob(dirname(__DIR__) . '/libs/*.php') as $file) {
    if (preg_match('/^trait \w+/m', file_get_contents($file))) {
        $sources[] = $file;
    }
}
$covered = 0;
foreach ($sources as $file) {
    $source = file_get_contents($file);
    preg_match_all('/public function (\w+)\([^{}]*?\)\s*:\s*\w+\s*\{([^\n]*\n[^\n]*)/s', $source, $methods, PREG_SET_ORDER);
    foreach ($methods as $method) {
        if (in_array($method[1], ['GetCompatibleParents', 'GetDeviceVariableIdent'], true)) {
            continue;
        }
        $check(str_contains($method[2], 'CloudDebugCall'), 'Public entry is missing its balanced tracing wrapper: ' . $method[1]);
        $covered++;
    }
}
$check($covered >= 100, 'The coverage inventory must include module-owned public trait entries, not just a few examples.');
echo "Contact/recipient privacy and public entry debug coverage passed.\n";
