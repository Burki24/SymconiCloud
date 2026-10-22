<?php

declare(strict_types=1);

// sdk-contracts.php also runs the shared module integration scenarios.
foreach (['validate_structure.php', 'apple-client.php', 'account.php', 'sdk-contracts.php', 'map.php', 'map-geocoding.php'] as $test) {
    $process = proc_open([PHP_BINARY, __DIR__ . '/' . $test], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}
foreach (['map-ui.js', 'map-transport.js', 'map-loader.js'] as $test) {
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
