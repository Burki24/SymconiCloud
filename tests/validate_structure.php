<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$json = static fn (string $path): array => json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$library = $json($root . '/library.json');
$assert($library['name'] === 'SymconiCloud' && $library['compatibility']['version'] === '9.0', 'Library identity/platform');
$assert(str_contains(file_get_contents($root . '/LICENSE'), 'PolyForm Noncommercial License 1.0.0'), 'PolyForm license');
$required = ['AGENTS.md', 'README.md', 'CHANGELOG.md', 'PROJECT_CONTEXT.md', '.gitattributes', '.editorconfig', '.gitmodules', '.helper-sync.json',
    '.style/.php-cs-fixer.php', '.style/json-check.php', 'tests/stubs/autoload.php', '.github/workflows/tests.yml', '.github/workflows/style.yml'];
foreach ($required as $file) {
    $assert(is_file($root . '/' . $file), 'Missing file: ' . $file);
}
$modules = [];
$ids = [$library['id']];
foreach (glob($root . '/*/module.json') as $file) {
    $module = $json($file);
    $modules[$module['type']] = $module;
    $assert(preg_match('/\A[A-Z0-9]{4}\z/D', $module['prefix']) === 1, 'Module prefixes must have exactly four characters');
    $assert(!in_array($module['id'], $ids, true), 'Duplicate module ID');
    $assert(preg_match('/\A\{[A-F0-9]{8}(?:-[A-F0-9]{4}){3}-[A-F0-9]{12}\}\z/D', $module['id']) === 1, 'Invalid module GUID');
    $ids[] = $module['id'];
    $folder = dirname($file);
    foreach (['module.php', 'form.json', 'locale.json', 'README.md'] as $requiredFile) {
        $assert(is_file($folder . '/' . $requiredFile), 'Missing module file: ' . $requiredFile);
    }
    $source = file_get_contents($folder . '/module.php');
    $assert(str_contains($source, 'class ' . str_replace(' ', '', $module['name']) . ' extends IPSModuleStrict'), 'Strict module class name');
    $form = $json($folder . '/form.json');
    $assert(isset($form['elements'], $form['actions']), 'Form structure');
    $translations = $json($folder . '/locale.json')['translations']['de'];
    $walk = static function (array $items) use (&$walk, $assert, $translations): void
    {
        foreach ($items as $item) {
            if (isset($item['caption']) && $item['caption'] !== '') {
                $assert(isset($translations[$item['caption']]), 'Missing German caption: ' . $item['caption']);
            }
            if (isset($item['items'])) {
                $walk($item['items']);
            }
        }
    };
    $walk($form['elements']);
    $walk($form['actions']);
}
$assert(count($modules) === 3 && isset($modules[2], $modules[3], $modules[4]), 'Account splitter, device and configurator required');
$assert($modules[2]['parentRequirements'] === [], 'HTTPS account needs no artificial I/O parent');
foreach ([3, 4] as $type) {
    $assert(str_contains($modules[$type]['name'], 'WoIst'), 'Service module name must include WoIst');
    $assert($modules[$type]['parentRequirements'] === $modules[2]['implemented'], 'Upstream data contract');
    $assert($modules[$type]['implemented'] === $modules[2]['childRequirements'], 'Downstream data contract');
}
$manifest = $json($root . '/libs/helper/manifest.json');
$subscriptions = $json($root . '/.helper-sync.json')['helpers'];
foreach ($subscriptions as $name => $subscription) {
    $meta = $manifest['helpers'][$name] ?? [];
    $assert(($meta['path'] ?? '') === $subscription['target'], 'Helper subscription path');
    $assert(hash_file('sha256', $root . '/' . $subscription['target']) === ($meta['sha256'] ?? ''), 'Changed upstream helper: ' . $name);
}
$workflow = file_get_contents($root . '/.github/workflows/tests.yml');
$assert(str_contains($workflow, 'Burki24/Symcon_ModuleCI/php-tests@v1.0.0') && str_contains($workflow, "php-version: '8.5'") && str_contains($workflow, 'bcmath'), 'Pinned shared CI and dependencies');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('#^(?:\.git/|\.style/|tests/stubs/|libs/helper/)#', $relative)) {
        continue;
    }
    if (in_array($file->getExtension(), ['php', 'json', 'md', 'py', 'yml'], true)) {
        $bytes = file_get_contents($file->getPathname());
        $assert(!str_contains($bytes, "\r"), 'Expected LF: ' . $relative);
        if ($file->getExtension() === 'json') {
            json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        }
    }
}
echo "Library structure, contracts, helper hashes, JSON and LF checks passed.\n";
