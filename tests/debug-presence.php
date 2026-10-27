<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/presence-group.php';
ob_end_clean();

$messages = (new ReflectionProperty(IPS\DebugServer::class, 'messages'))->getValue();
$trace = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
foreach (['Standortmessung ist zu alt', 'Standortmessung ist zu ungenau', 'wartet auf eine neue bestätigende Messung', 'Aufenthaltsdauer', 'Sekunden'] as $explanation) {
    $check(str_contains($trace, $explanation), 'Presence debug is missing a human-readable cause/unit: ' . $explanation);
}
foreach (['Synthetic home', 'Synthetic work', 'Synthetic alpha', 'Synthetic beta', '"Wert":0.002', '"Wert":0.01'] as $private) {
    $check(!str_contains($trace, $private), 'Presence debug exposed a private zone/device name or coordinate.');
}
echo "Presence GPS explanations, stabilization, value units and location privacy passed.\n";
