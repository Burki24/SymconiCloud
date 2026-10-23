<?php

declare(strict_types=1);

// Official contract sources (reviewed 2026-10-21):
// https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/konfigurationsformulare/button/
// https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/konfigurationsformulare/configurator/
// https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/updateformfield/
// https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/configuration-forms/list/
// https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/configuration-forms/numberspinner/
require __DIR__ . '/modules.php';

/** Enforces the documented UpdateFormField wire contract missing from the stubs. */
class ContractConfigurator extends iCloudDeviceConfigurator
{
    public array $fieldUpdates = [];

    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        $form = json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Device Configurator/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $fields = array_column(array_merge($form['elements'], $form['actions']), null, 'name');
        if (!isset($fields[$Field]) || in_array($Parameter, ['name', 'type'], true)) {
            throw new RuntimeException('UpdateFormField targets an unknown field or immutable parameter.');
        }
        if ($Parameter === 'values') {
            if (!is_string($Value) || !is_array(json_decode($Value, false, 512, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Complex form updates require a JSON-encoded list.');
            }
        }
        $this->fieldUpdates[$Field][$Parameter] = $Value;
        return true;
    }
}

/** Validates restricted form callbacks and their optional documented MESSAGE return without eval. */
function validateButtonScript(string|array $script, array $scope, array $module): array
{
    $script = is_array($script) ? implode("\n", $script) : $script;
    $tokens = array_values(array_filter(token_get_all('<?php ' . $script, TOKEN_PARSE), static fn ($token): bool => !is_array($token) || !in_array($token[0], [T_OPEN_TAG, T_WHITESPACE], true)));
    $calls = [];
    while ($tokens !== []) {
        $function = array_shift($tokens);
        if (is_array($function) && $function[0] === T_RETURN) {
            $message = array_shift($tokens);
            if ($calls === [] || !is_array($message) || $message[0] !== T_CONSTANT_ENCAPSED_STRING
                || !str_starts_with(substr($message[1], 1, -1), 'MESSAGE:') || array_shift($tokens) !== ';' || $tokens !== []) {
                throw new RuntimeException('A form return must terminate successful calls with a literal MESSAGE response.');
            }
            break;
        }
        if (!is_array($function) || $function[0] !== T_STRING || array_shift($tokens) !== '(') {
            throw new RuntimeException('Unsupported button script; extend the contract validator explicitly.');
        }
        $arguments = [];
        while (($tokens[0] ?? null) !== ')') {
            $token = array_shift($tokens);
            if (!is_array($token)) {
                throw new RuntimeException('Expected a simple callback argument.');
            }
            if ($token[0] === T_VARIABLE) {
                $name = substr($token[1], 1);
                if (!array_key_exists($name, $scope)) {
                    throw new RuntimeException('Callback variable is outside its form scope: ' . $name);
                }
                $arguments[] = $scope[$name];
            } elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $arguments[] = $token[1][0] === '"' ? json_decode($token[1], true, 512, JSON_THROW_ON_ERROR)
                    : str_replace(["\\'", '\\\\'], ["'", '\\'], substr($token[1], 1, -1));
            } elseif ($token[0] === T_STRING && $token[1] === 'json_encode') {
                $open = array_shift($tokens);
                $variable = array_shift($tokens);
                $close = array_shift($tokens);
                if ($open !== '(' || !is_array($variable) || $variable[0] !== T_VARIABLE || $close !== ')'
                    || !array_key_exists(substr($variable[1], 1), $scope)) {
                    throw new RuntimeException('Only JSON encoding a known form field is supported.');
                }
                $arguments[] = json_encode($scope[substr($variable[1], 1)], JSON_THROW_ON_ERROR);
            } elseif ($token[0] === T_LNUMBER) {
                $arguments[] = (int) $token[1];
            } else {
                throw new RuntimeException('Unsupported callback argument.');
            }
            if (($tokens[0] ?? null) === ',') {
                array_shift($tokens);
                if (($tokens[0] ?? null) === ')') {
                    throw new RuntimeException('Unexpected trailing callback comma.');
                }
            } elseif (($tokens[0] ?? null) !== ')') {
                throw new RuntimeException('Unexpected callback expression.');
            }
        }
        array_shift($tokens);
        if (array_shift($tokens) !== ';') {
            throw new RuntimeException('Expected callback statement terminator.');
        }
        $function = $function[1];
        if ($function !== 'IPS_RequestAction' && !str_starts_with($function, $module['prefix'] . '_')) {
            throw new RuntimeException('Callback targets a different module.');
        }
        if (!function_exists($function)) {
            throw new RuntimeException('Unknown callback function: ' . $function);
        }
        $reflection = new ReflectionFunction($function);
        if (count($arguments) < $reflection->getNumberOfRequiredParameters() || count($arguments) > $reflection->getNumberOfParameters()) {
            throw new RuntimeException('Incorrect callback argument count.');
        }
        foreach ($reflection->getParameters() as $index => $parameter) {
            if (!array_key_exists($index, $arguments) || $parameter->getType() === null) {
                continue;
            }
            $type = (string) $parameter->getType();
            if (($type === 'int' && !is_int($arguments[$index])) || ($type === 'string' && !is_string($arguments[$index])) || ($type === 'bool' && !is_bool($arguments[$index]))) {
                throw new RuntimeException('Incorrect callback argument type.');
            }
        }
        $calls[] = [$function, $arguments];
    }
    if ($calls === []) {
        throw new RuntimeException('Button has no callback.');
    }
    return $calls;
}

/** Project uses Configurator variant 1: moduleID plus a configuration object. */
function validateCreationRecipe(mixed $recipe, array $knownModules): void
{
    if (!$recipe instanceof stdClass || !is_string($recipe->moduleID ?? null) || !isset($knownModules[$recipe->moduleID])
        || !($recipe->configuration ?? null) instanceof stdClass) {
        throw new RuntimeException('Creation needs a known moduleID and a configuration object.');
    }
    $target = IPS_CreateInstance($recipe->moduleID);
    $defaults = json_decode(IPS_GetConfiguration($target), true, 512, JSON_THROW_ON_ERROR);
    foreach (get_object_vars($recipe->configuration) as $name => $value) {
        if (!array_key_exists($name, $defaults) || get_debug_type($defaults[$name]) !== get_debug_type($value)) {
            throw new RuntimeException('Creation property is unknown or has the wrong type: ' . $name);
        }
    }
}

$expectFailure = static function (callable $operation): void
{
    try {
        $operation();
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('A deliberately invalid SDK contract was accepted.');
};
$root = dirname(__DIR__);
$metadata = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$loadedModules = $metadata->getValue();
$loadedModules[$accountModule]['Class'] = iCloudAccount::class;
$metadata->setValue(null, $loadedModules);
$knownModules = [];
foreach (glob($root . '/*/module.json') as $file) {
    $knownModules[json_decode(file_get_contents($file), true)['id']] = dirname($file);
}
foreach ($knownModules as $moduleID => $folder) {
    $module = json_decode(file_get_contents($folder . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    $instanceID = IPS_CreateInstance($moduleID);
    if ($module['prefix'] === 'ICWI') {
        IPS_SetProperty($instanceID, 'EnableIPSView', true);
        IPS_ApplyChanges($instanceID);
    }
    $form = json_decode(IPS_GetConfigurationForm($instanceID), true, 512, JSON_THROW_ON_ERROR);
    $defaults = json_decode(IPS_GetConfiguration($instanceID), true, 512, JSON_THROW_ON_ERROR);
    foreach (['elements', 'actions'] as $section) {
        $scope = ['id' => $instanceID];
        $buttons = [];
        $collect = static function (array $items) use (&$collect, &$scope, &$buttons, $defaults): void
        {
            foreach ($items as $item) {
                if (isset($item['name'])) {
                    $scope[$item['name']] = $defaults[$item['name']] ?? (($item['type'] ?? '') === 'PasswordTextBox' ? '123456' : '');
                    if (($item['type'] ?? '') === 'List') {
                        $scope[$item['name']] = json_decode($scope[$item['name']], true, 512, JSON_THROW_ON_ERROR);
                    }
                }
                foreach (['onClick', 'onChange', 'onAdd', 'onEdit', 'onDelete'] as $event) {
                    if (isset($item[$event])) {
                        $buttons[] = ['script' => $item[$event], 'item' => $item];
                    }
                }
                if (isset($item['items'])) {
                    $collect($item['items']);
                }
            }
        };
        $collect($form[$section] ?? []);
        foreach ($buttons as $callback) {
            $button = $callback['item'];
            $check(is_string($button['caption'] ?? null), 'Button caption must be a string.');
            $check(!isset($button['confirm']) || is_string($button['confirm']), 'Button confirmation must be a string.');
            foreach (validateButtonScript($callback['script'], $scope, $module) as [$function, $arguments]) {
                // The account is disabled; children have no parent. These real
                // callbacks exercise action routing without any Apple transport.
                $function(...$arguments);
            }
        }
    }
}

$firstBoundary->result = $envelope;
$firstBoundary->commands = [];
IPS_ApplyChanges($configurator);
$check(IPS_GetInstance($configurator)['InstanceStatus'] === IS_ACTIVE, 'Configured account cache must remain active after ApplyChanges.');
$check($firstBoundary->commands === ['GetDevices'], 'ApplyChanges must read cache without refreshing Apple.');
$form = json_decode(IPS_GetConfigurationForm($configurator), false, 512, JSON_THROW_ON_ERROR);
foreach ([$form->elements[0], $form->elements[1]] as $table) {
    $columns = [];
    foreach ($table->columns as $column) {
        $check(is_string($column->name) && !in_array($column->name, ['id', 'parent', 'create', 'expanded', 'instanceID'], true), 'Reserved Configurator column name.');
        $check(!isset($columns[$column->name]), 'Duplicate Configurator column.');
        $check(is_string($column->width) && ($column->width === 'auto' || preg_match('/\A[0-9]+px\z/', $column->width) === 1), 'Invalid column width.');
        $columns[$column->name] = $column->width;
    }
    $check(count(array_filter($columns, static fn ($width): bool => $width === 'auto')) <= 1, 'Only one auto column is supported.');
    $check(isset($columns[$table->sort->column]) && in_array($table->sort->direction, ['ascending', 'descending'], true), 'Invalid Configurator sorting.');
    $recipe = $table->values[0]->create;
    validateCreationRecipe($recipe, $knownModules);
}
$recipe = $form->elements[0]->values[0]->create;
$created = IPS_CreateInstance($recipe->moduleID);
IPS_ConnectInstance($created, $firstAccount);
IPS_SetConfiguration($created, json_encode($recipe->configuration, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($created);
$check(IPS_GetProperty($created, 'DeviceID') === 'synthetic-device', 'Creation must apply the intended identity.');
$check($value($created, 'BatteryLevel') === 42.0, 'Created device must consume the intended account cache.');

$deviceMeta = json_decode(file_get_contents($knownModules[$deviceModule] . '/module.json'), true);
$check(count(validateButtonScript("IPS_RequestAction(\$id, 'Update', ''); return 'MESSAGE:Updated';", ['id' => $firstDevice], $deviceMeta)) === 1, 'Helper MESSAGE callbacks must retain their actual action.');
$expectFailure(fn () => validateButtonScript("return 'MESSAGE:Skipped';", ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript("ICID_Update(\$id); return 'NOT_A_MESSAGE';", ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript("ICID_Update(\$id); return 'MESSAGE:Updated'; ICID_Update(\$id);", ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript('ICID_Missing($id);', ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript('ICID_Update($id, 1);', ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript('ICID_Update($Code);', ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript("ICID_Update('invalid-instance');", ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript('ICID_Update(json_encode($Missing));', ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateButtonScript('ICID_Update(file_get_contents($id));', ['id' => $firstDevice], $deviceMeta));
$expectFailure(fn () => validateCreationRecipe((object) ['moduleID' => $deviceModule, 'configuration' => '{"DeviceID":"synthetic-device"}'], $knownModules));
$expectFailure(fn () => validateCreationRecipe((object) ['moduleID' => $deviceModule, 'configuration' => (object) ['Unknown' => 'x']], $knownModules));
$expectFailure(fn () => validateCreationRecipe((object) ['moduleID' => $deviceModule, 'configuration' => (object) ['DeviceID' => 123]], $knownModules));
$expectFailure(fn () => validateCreationRecipe((object) ['moduleID' => '{00000000-0000-0000-0000-000000000000}', 'configuration' => (object) []], $knownModules));

// Replace only the otherwise empty form-update implementation in the official
// stubs; all configurator behavior and account routing stay production code.
$loadedModules = $metadata->getValue();
$loadedModules[$configuratorModule]['Class'] = ContractConfigurator::class;
$metadata->setValue(null, $loadedModules);
$probeID = IPS_CreateInstance($configuratorModule);
IPS_ConnectInstance($probeID, $firstAccount);
IPS_ApplyChanges($probeID);
$probe = IPS\InstanceManager::getInstanceInterface($probeID);
$check(count(json_decode($probe->fieldUpdates['Devices']['values'], true, 512, JSON_THROW_ON_ERROR)) >= 1, 'Dynamic list update must carry device rows as JSON.');
$check(count(json_decode($probe->fieldUpdates['PresenceInstances']['values'], true, 512, JSON_THROW_ON_ERROR)) >= 1, 'Presence list updates must also carry JSON rows.');
$check(is_string($probe->fieldUpdates['Result']['caption']), 'Dynamic result caption must remain a string.');

$firstBoundary->result['success'] = false;
IPS_ApplyChanges($configurator);
$check(IPS_GetInstance($configurator)['InstanceStatus'] === 202, 'Unavailable account cache must produce an error after ApplyChanges.');
$failed = json_decode(IPS_GetConfigurationForm($configurator), true, 512, JSON_THROW_ON_ERROR);
$check(!isset($failed['elements'][0]['values'][0]['create']), 'Failed account cache must not offer device creation.');
$check(!isset($failed['elements'][1]['values'][0]['create']), 'Failed account cache must not offer presence creation.');
$disconnected = IPS_CreateInstance($configuratorModule);
$check(IPS_GetInstance($disconnected)['InstanceStatus'] === 201, 'Missing account must retain its dedicated status.');
echo "Documented form-action and creation contracts passed.\n";
