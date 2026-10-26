<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$check = static function (bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$workflowPath = $root . '/.github/workflows/update-library-metadata.yml';
$check(is_file($workflowPath), 'Automatic library metadata workflow is missing.');
$workflow = file_get_contents($workflowPath);
foreach (['Burki24/Symcon_ModuleCI/library-metadata@dev', 'HELPER_SYNC_APP_CLIENT_ID', 'HELPER_SYNC_APP_PRIVATE_KEY', 'permission-contents: write', 'fetch-depth: 0', 'CHORE: Update library metadata', 'cancel-in-progress: false', 'git add library.json', 'Source-Commit:', 'test-command: php tests/run.php', 'bcmath, dom, gd, exif', 'node-version:'] as $needle) {
    $check(str_contains($workflow, $needle), 'Metadata workflow contract missing: ' . $needle);
}
$check(!str_contains($workflow, '--force') && !str_contains($workflow, 'pull_request_target'), 'Metadata automation must never force-push or run on untrusted PR code.');
$check(!is_file($root . '/.github/scripts/update_library_metadata.py'), 'Version logic belongs in the central CI action.');
foreach (glob($root . '/*/module.json') as $path) {
    $module = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $folder = basename(dirname($path));
    $expected = 'https://github.com/Burki24/SymconiCloud/blob/dev/' . rawurlencode($folder) . '/README.md';
    $check($module['url'] === $expected && is_file(dirname($path) . '/README.md'), 'Module documentation must target its existing Beta guide: ' . $folder);
}
echo "Central versioning workflow, safe publishing and current module documentation contracts passed.\n";
