<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/libs/PhotoDiskCache.php';

use SymconiCloud\PhotoDiskCache;

$check = static function (bool $ok, string $message): void
{ if (!$ok) { throw new RuntimeException($message); } };
foreach (['compact' => [50, 100], 'standard' => [250, 500], 'large' => [1000, 2048], 'custom' => [3, 2]] as $profile => [$images, $mib]) {
    $check(PhotoDiskCache::limits($profile, 3, 2) === [$images, $mib * 1048576], 'Resource profiles must preserve their count and disk budgets.');
}
$root = sys_get_temp_dir() . '/symcon-photo-cache-' . bin2hex(random_bytes(8));
$webRoot = $root . '-user';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', true);
$cache = new PhotoDiskCache($root, 2, strlen($png) * 2);
$key = random_bytes(32);
$webCache = new PhotoDiskCache($webRoot, 2, strlen($png) * 2, '', $key);
$photo = static fn (string $id): array => ['id' => $id, 'name' => $id, 'date' => 1, 'revision' => 'revision', 'url' => $id];
$requests = [];
$download = static function (string $id) use ($png, &$requests): string
{
    $requests[] = $id;
    return $png;
};
try {
    mkdir($root, 0700);
    $legacy = hash('sha256', 'a:revision');
    file_put_contents($root . '/' . $legacy . '.bin', $png);
    file_put_contents($root . '/index.json', json_encode([$legacy => strlen($png)], JSON_THROW_ON_ERROR));
    $abandoned = $root . '/' . hash('sha256', 'abandoned') . '.bin';
    file_put_contents($abandoned, $png);
    $cache->trim();
    $first = $cache->synchronize('first', [$photo('a'), $photo('b'), $photo('c')], $download);
    $check($first['limited'] && $first['count'] === 2 && $requests === ['b'], 'The first sync must reuse valid legacy previews and avoid downloads beyond the count limit.');
    $check(!file_exists($abandoned), 'New synchronization must clean up unreferenced previews from an abandoned write.');
    $check($cache->stats() === ['images' => 2, 'bytes' => strlen($png) * 2], 'The account budget applies to stored images.');
    $otherOwner = new PhotoDiskCache($root, 2, strlen($png) * 2, 'other-account');
    $check($otherOwner->page('first', 0)['items'] === [], 'A different account cannot read an inherited snapshot.');
    $cache->synchronize('second', [$photo('b')], $download);
    $check($requests === ['b'], 'Multiple albums must reuse identical photo revisions.');
    $smaller = new PhotoDiskCache($root, 2, strlen($png));
    $smaller->trim();
    $check($smaller->page('first', 0)['total'] === 1 && $smaller->page('second', 0)['total'] === 1 && $smaller->stats()['images'] === 1, 'A reduced byte budget preserves a shared preview and trims only excess entries.');
    $check($smaller->page('first', 0)['limited'], 'Trimmed snapshots must advertise their limit.');
    $cache->synchronize('second', [], $download);
    $check($cache->page('second', 0)['total'] === 0, 'A complete empty cloud album removes old local membership.');
    file_put_contents($root . '/unrelated.txt', 'unrelated');
    mkdir($webRoot, 0700);
    file_put_contents($webRoot . '/photo-user.jpg', 'user-provided photo');
    file_put_contents($webRoot . '/albums.json', 'user-provided index');
    $webCache->importStoredPhotos($cache);
    $check($webCache->page('first', 0)['total'] === 1 && $cache->stats()['images'] === 0, 'Existing synchronized photos must move to the encrypted store without downloading again.');
    $picture = $webCache->page('first', 0)['items'][0];
    $image = $webCache->image('first', $picture['id'], $picture['version']);
    $check(base64_decode($image['data'], true) === $png, 'Transferred previews must be playable through the authorized path.');
    $check(file_get_contents($webRoot . '/scphoto-' . $picture['version'] . '.bin') !== $png, 'The new location must store encrypted bytes.');
    $check(!str_contains(file_get_contents($webRoot . '/scphoto-albums.json'), '"items"'), 'The album index must also be encrypted.');
    $webCache->clear();
    $check(file_get_contents($webRoot . '/photo-user.jpg') === 'user-provided photo' && file_get_contents($webRoot . '/albums.json') === 'user-provided index', 'Cache cleanup must not touch user files in the new directory.');
    unlink($webRoot . '/photo-user.jpg');
    unlink($webRoot . '/albums.json');
    $check($cache->stats() === ['images' => 0, 'bytes' => 0] && file_get_contents($root . '/unrelated.txt') === 'unrelated', 'Clear removes managed snapshots while preserving unrelated files.');
    unlink($root . '/unrelated.txt');

    file_put_contents($root . '/' . $legacy . '.bin', $png);
    file_put_contents($root . '/index.json', json_encode([$legacy => strlen($png)], JSON_THROW_ON_ERROR));
    $webCache->importStoredPhotos($cache);
    $previousRequests = $requests;
    $check($webCache->synchronize('first', [$photo('a')], $download)['count'] === 1 && $requests === $previousRequests, 'Old previews without album metadata must remain reusable after the move.');
} finally {
    $webCache->clear();
    $cache->clear();
    if (is_dir($root)) {
        rmdir($root);
    }
    if (is_dir($webRoot)) {
        rmdir($webRoot);
    }
}
echo "Photo resource profiles, snapshot budgets, legacy reuse and cleanup passed.\n";
