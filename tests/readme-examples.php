<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/autoload.php';

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');
$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$count = 0;
foreach (glob(dirname(__DIR__) . '/iCloud */module.json') as $manifestPath) {
    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $id = IPS_CreateInstance($manifest['id']);
    IPS_ApplyChanges($id);
    $readme = file_get_contents(dirname($manifestPath) . '/README.md');
    $configuration = json_decode(IPS_GetConfiguration($id), true, 512, JSON_THROW_ON_ERROR);
    // Configuration examples must refer to actual properties with their real PHP types.
    $propertyTable = false;
    foreach (explode("\n", $readme) as $line) {
        if (str_starts_with($line, '| Formularfeld | Property |')) {
            $propertyTable = true;
        } elseif (!str_starts_with($line, '|')) {
            $propertyTable = false;
        }
        if ($propertyTable && preg_match('/^\| [^|]+ \| `([A-Za-z]+)` \| (bool|int|float|string) \|/', $line, $match)) {
            $check(array_key_exists($match[1], $configuration), 'Unknown documented property: ' . $match[1]);
            $check(get_debug_type($configuration[$match[1]]) === $match[2], 'Wrong documented property type: ' . $match[1]);
        }
    }
    preg_match_all('/```php\R(.*?)```/s', $readme, $examples);
    $check($examples[1] !== [], 'Each module needs a runnable synthetic script example.');
    foreach ($examples[1] as $example) {
        // Documentation examples intentionally use only local reads and configuration.
        $check(!preg_match('/\b(?:ICAC_Login|ICAC_VerifyCode|ICAC_Refresh|ICID_PlaySound|ICID_Activate|ICID_Deactivate|ICCT_Refresh|ICPH_Refresh|ICWI_Refresh|ICDC_Refresh)/', $example), 'Examples must not trigger external work.');
        $code = str_replace('$instanceID = 12345;', '$instanceID = ' . $id . ';', $example);
        unset($result);
        ob_start();
        try {
            eval($code);
        } finally {
            ob_end_clean();
        }
        $check(isset($result), 'Example must expose its documented result.');
        match ($manifest['prefix']) {
            'ICAC'  => $check(is_array($result) && $result['success'] === false && IPS_GetProperty($id, 'PauseRequests'), 'Account example must pause requests and read an unavailable cache.'),
            'ICCT'  => $check(is_array($result) && $result['success'] === false && $offset === 0, 'Directory example must stop on unavailable data.'),
            'ICWI'  => $check(is_array($result) && isset($result['state']), 'Map example must return the local bootstrap structure.'),
            'ICPH'  => $check(is_string($result), 'Photo embedding example must return a string, not a delivery confirmation.'),
            'ICID'  => $check($result === false && $available === false, 'Device example must not mistake unavailable accuracy for zero.'),
            'ICPR'  => $check($result === false && $presence === -1, 'Presence example must retain an unknown state without source data.'),
            'ICDC'  => $check($result === false, 'Configurator example must report missing source data.'),
            default => throw new RuntimeException('Unreviewed module example.')
        };
        $count++;
    }
}
$check($count === 7, 'All seven module examples must be exercised.');
echo "Seven README examples, property types and unavailable-data contracts passed offline.\n";
