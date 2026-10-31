<?php

declare(strict_types=1);

require_once __DIR__ . '/presence.php';

// The official Migrate contract explicitly schedules ApplyChanges after loading
// persistence on startup/module update. Model that kernel path, not a UI click:
// https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/migrate/
$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$syntheticDevice];
$deviceID = $createDevice($firstAccount);
$presenceID = IPS_CreateInstance($presenceModule);
IPS_ConnectInstance($presenceID, $firstAccount);
IPS_SetProperty($presenceID, 'Devices', json_encode([['deviceID' => 'synthetic-device', 'name' => 'Synthetic']], JSON_THROW_ON_ERROR));
IPS_SetProperty($presenceID, 'Zones', json_encode([['id' => 'home', 'name' => 'Synthetic home', 'latitude' => 51.25, 'longitude' => 7.5, 'radius' => 100, 'reference' => true]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($presenceID);
$interfaces = new ReflectionProperty(IPS\InstanceManager::class, 'interfaces');
$instances = new ReflectionProperty(IPS\InstanceManager::class, 'instances');
$defaultFields = ['IconActive' => false, 'IconValue' => '', 'ColorActive' => false, 'ColorValue' => -1, 'ContentColorActive' => false, 'ContentColorValue' => -1];
$tested = 0;
foreach ([$deviceID, $presenceID] as $instanceID) {
    $instance = IPS\InstanceManager::getInstanceInterface($instanceID);
    $saved = [];
    foreach (IPS_GetChildrenIDs($instanceID) as $variableID) {
        if (IPS_GetVariable($variableID)['VariableType'] !== VARIABLETYPE_BOOLEAN) {
            continue;
        }
        $object = IPS_GetObject($variableID);
        $legacy = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'OPTIONS' => json_encode([
            ['Value' => false, 'Caption' => 'Old no'], ['Value' => true, 'Caption' => 'Old yes']
        ], JSON_THROW_ON_ERROR)];
        (new ReflectionMethod($instance, 'RegisterVariableBoolean'))->invoke($instance, $object['ObjectIdent'], 'Must not rename', $legacy, 999);
        $custom = [];
        if ($tested % 3 !== 0) {
            $custom = $legacy;
            $custom['ICON'] = 'Star';
            $rows = [['Value' => false, 'Caption' => 'User no'], ['Value' => true, 'Caption' => 'User yes']];
            if ($tested % 3 === 1) {
                foreach ($rows as &$row) {
                    $row += $defaultFields;
                    $row['ColorActive'] = true;
                    $row['ColorValue'] = 0;
                }
                unset($row);
            }
            $custom['OPTIONS'] = json_encode($rows, JSON_THROW_ON_ERROR);
        }
        IPS_SetVariableCustomPresentation($variableID, $custom);
        IPS_SetName($variableID, 'User label ' . $tested);
        IPS_SetIcon($variableID, 'Star');
        $saved[$variableID] = ['object' => IPS_GetObject($variableID), 'value' => GetValue($variableID), 'custom' => $custom];
        $tested++;
    }
    $configuration = IPS_GetConfiguration($instanceID);
    $children = IPS_GetChildrenIDs($instanceID);
    for ($cycle = 0; $cycle < 2; $cycle++) {
        $persistence = $instance->GetPersistence();
        $firstBoundary->commands = [];
        $reloaded = new ($instance::class)($instanceID);
        $table = $interfaces->getValue();
        $table[$instanceID] = $reloaded;
        $interfaces->setValue(null, $table);
        $kernelInstances = $instances->getValue();
        $kernelInstances[$instanceID]['InstanceStatus'] = IS_CREATING;
        $instances->setValue(null, $kernelInstances);
        $reloaded->Create();
        $migrated = $reloaded->Migrate(json_encode($persistence, JSON_THROW_ON_ERROR));
        $reloaded->ApplyPersistence($migrated === '' ? $persistence : json_decode($migrated, true, 512, JSON_THROW_ON_ERROR));
        $reloaded->ApplyChanges(); // Kernel step documented for updates; no UI/configuration changes.
        $instance = $reloaded;
        $check(IPS_GetConfiguration($instanceID) === $configuration && !IPS_HasChanges($instanceID), 'An update must not require or invent pending configuration changes.');
        $check(IPS_GetChildrenIDs($instanceID) === $children, 'Reload must preserve all variable IDs.');
        $check($firstBoundary->commands === ['GetDevices'], 'Reload may read only the existing local cache; no Apple refresh or device action.');
        foreach ($saved as $variableID => $expected) {
            $variable = IPS_GetVariable($variableID);
            $check($variable['VariableType'] === VARIABLETYPE_BOOLEAN && GetValue($variableID) === $expected['value'], 'Reload must retain Boolean type and existing value for unchanged cache data.');
            $check(IPS_GetObject($variableID) === $expected['object'] && $variable['VariableCustomPresentation'] === $expected['custom'], 'Reload must preserve names, icons, positions and complete/incomplete custom presentations.');
            $options = json_decode($variable['VariablePresentation']['OPTIONS'], true, 512, JSON_THROW_ON_ERROR);
            $check(array_column($options, 'Value') === [false, true] && array_column($options, 'Caption') === ['No', 'Yes'], 'Kernel ApplyChanges must replace the old native definition automatically.');
            foreach ($options as $row) {
                foreach ($defaultFields as $field => $value) {
                    $check(array_key_exists($field, $row) && $row[$field] === $value, 'Reload must provide every neutral 9.0 field: ' . $field);
                }
            }
        }
    }
}
$check($tested === 11, 'Exercise six device Booleans plus summary and four dynamic presence Booleans.');
echo "Automatic module-update lifecycle refreshes eleven Boolean definitions without UI changes, value loss, custom overrides or Apple requests.\n";
