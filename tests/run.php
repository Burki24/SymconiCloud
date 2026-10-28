<?php

declare(strict_types=1);

// sdk-contracts.php also runs the shared module integration scenarios.
// contact-transfer.php also runs Contacts, account protection and account integration scenarios.
// compact-address.php also runs Geoapify, precision, device address and map geocoding scenarios.
// lost-mode-deactivation.php also runs the confirmed activation contracts.
// apple-2fa.php and account-2fa.php also run the existing client and account security scenarios.
require __DIR__ . '/release-automation.php';
foreach (['validate_structure.php', 'photos-cache.php', 'photos-boundaries.php', 'apple-2fa.php', 'account-2fa.php', 'debug-output.php', 'debug-data.php', 'contact-transfer.php', 'play-sound.php', 'device-widget.php', 'lost-mode-deactivation.php', 'sdk-contracts.php', 'device-details.php', 'debug-presence.php', 'presence-form.php', 'presence-widget.php', 'presence-widget-compact.php', 'presence-widget-images.php', 'map.php', 'map-device-selection.php', 'woist-color-guide.php', 'compact-address.php'] as $test) {
    $process = proc_open([PHP_BINARY, __DIR__ . '/' . $test], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}
foreach (['photos-ui.js', 'map-ui.js', 'map-transport.js', 'map-loader.js', 'presence-widget-ui.js', 'device-widget-ui.js'] as $test) {
    $process = proc_open(['node', __DIR__ . '/' . $test], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(dirname(__DIR__)) + 1));
    if ($file->getExtension() !== 'php' || preg_match('#^(?:\.git/|\.style/|tests/stubs/|libs/helper/)#', $relative)) {
        continue;
    }
    $process = proc_open([PHP_BINARY, '-l', $file->getPathname()], [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}
echo "All offline checks passed.\n";
