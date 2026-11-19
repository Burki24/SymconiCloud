<?php

declare(strict_types=1);

require __DIR__ . '/stubs/autoload.php';
require dirname(__DIR__) . '/iCloud WoIst/module.php';
require dirname(__DIR__) . '/iCloud Presence/module.php';
require dirname(__DIR__) . '/iCloud Photos/module.php';

trait FailingVisualizationHook
{
    public bool $allowHook = false;
    public int $hookAttempts = 0;

    protected function RegisterHook(string $HookPath): bool
    {
        $this->hookAttempts++;
        return $this->allowHook;
    }

    protected function LoadConfigurationForm(): array
    {
        $moduleFile = (new ReflectionClass(get_parent_class($this)))->getFileName();
        return json_decode(file_get_contents(dirname($moduleFile) . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}

class HookUnavailableMap extends iCloudWoIst
{
    use FailingVisualizationHook;
}

class HookUnavailablePresence extends iCloudPresence
{
    use FailingVisualizationHook;
}

class HookUnavailablePhotos extends iCloudPhotos
{
    use FailingVisualizationHook;
}

$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$warning = 'The visualization WebHook is unavailable. Save the configuration to retry registration.';
foreach ([HookUnavailableMap::class, HookUnavailablePresence::class, HookUnavailablePhotos::class] as $index => $class) {
    IPS\Kernel::reset();
    $id = IPS\ObjectManager::registerObject(OBJECTTYPE_INSTANCE);
    IPS\InstanceManager::createInstance($id, [
        'ModuleID'   => sprintf('{00000000-0000-4000-8000-%012d}', $index + 1),
        'ModuleName' => $class,
        'ModuleType' => MODULETYPE_DEVICE,
        'Class'      => $class
    ]);
    $module = IPS\InstanceManager::getInstanceInterface($id);
    $attemptsAfterCreate = $module->hookAttempts;
    $check($attemptsAfterCreate > 0, 'The instance must try to register its hook on creation.');
    IPS_SetProperty($id, 'EnableIPSView', true);
    IPS_ApplyChanges($id);
    $check(str_contains($module->GetConfigurationForm(), $warning), $class . ' must explain the missing hook in the form.');
    $check(str_contains($module->GetIPSViewHTML(), $warning), $class . ' must render a diagnostic instead of a broken IPSView loader.');
    $check(str_contains($module->GetVisualizationTile(), $warning) === ($class !== HookUnavailablePresence::class), 'Native presence must remain available independently of the hook.');
    $check($module->hookAttempts === $attemptsAfterCreate + 1, 'Reading the form and output must not retry hook registration.');

    $module->allowHook = true;
    IPS_ApplyChanges($id);
    $check($module->hookAttempts === $attemptsAfterCreate + 2 && !str_contains($module->GetConfigurationForm(), $warning), $class . ' must recover when applying the configuration.');
    $check(!str_contains($module->GetIPSViewHTML(), $warning), 'The IPSView output must recover after registration succeeds.');
    IPS_ApplyChanges($id);
    $check($module->hookAttempts === $attemptsAfterCreate + 2, 'Applying changes must preserve an already registered hook.');
}

echo "Visualization instances tolerate unavailable hooks and recover on configuration changes.\n";
