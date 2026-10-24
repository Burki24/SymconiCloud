<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/PhotoDiskCache.php';
$check = static function (bool $ok, string $message): void
{ if (!$ok) { throw new RuntimeException($message); } };
foreach (['compact' => [50, 100], 'standard' => [250, 500], 'large' => [1000, 2048], 'custom' => [3, 2]] as $profile => [$images, $mib]) {
    $check(\SymconiCloud\PhotoDiskCache::limits($profile, 3, 2) === [$images, $mib * 1048576], 'Explicit resource profiles must match the agreed count and disk limits.');
}
$root = sys_get_temp_dir() . '/symcon-photo-cache-' . bin2hex(random_bytes(8));
$cache = new \SymconiCloud\PhotoDiskCache($root, 2, 12);
try {
    $cache->put('a', '1234');
    $cache->put('b', '5678');
    $check($cache->get('a') === '1234', 'Read must return a cached image.');
    $cache->put('c', 'abcd');
    $check($cache->get('b') === null && $cache->get('a') !== null, 'Count limits must evict the least recently used entry.');
    $cache->put('d', '123456789');
    $check($cache->stats() === ['images' => 1, 'bytes' => 9], 'The byte limit must also evict entries independently of the count limit.');
    $check(!$cache->put('too-large', str_repeat('x', 13)), 'An oversized image must not exceed the disk budget.');
    $check($cache->get('../other') === null, 'Opaque keys must never escape the account cache.');
    $cache->clear();
    $cache->put('first', '1234');
    $cache->put('second', '5678');
    $smaller = new \SymconiCloud\PhotoDiskCache($root, 1, 4);
    $smaller->trim();
    $check($smaller->stats() === ['images' => 1, 'bytes' => 4] && $smaller->get('second') === '5678', 'Reducing a profile must immediately enforce both new limits.');
    $orphan = $root . '/' . hash('sha256', 'interrupted-write') . '.bin';
    file_put_contents($orphan, 'orphan');
    file_put_contents($root . '/photo-interrupted.tmp', 'temporary');
    file_put_contents($root . '/unrelated.txt', 'unrelated');
    $smaller->trim();
    $check(!file_exists($orphan) && !file_exists($root . '/photo-interrupted.tmp') && file_exists($root . '/unrelated.txt'), 'Crash cleanup must remove owned orphan files without touching other files.');
    $cache->clear();
    $check(file_get_contents($root . '/unrelated.txt') === 'unrelated', 'Cache deletion must retain unrelated files.');
    unlink($root . '/unrelated.txt');
} finally {
    $cache->clear();
    if (is_dir($root)) {
        rmdir($root);
    }
}
echo "Photo resource profiles, shared disk budget and LRU eviction passed.\n";
