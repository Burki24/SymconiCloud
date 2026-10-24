<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/photos.php';
ob_end_clean();

// A different concrete filename reproduces Symcon's evaluated WebHook class.
class PhotosHookContextFixture extends iCloudPhotos
{
}
$loaded = $metadata->getValue();
$loaded[$photoModule]['Class'] = PhotosHookContextFixture::class;
$metadata->setValue(null, $loaded);
$hookID = IPS_CreateInstance($photoModule);
$hook = IPS\InstanceManager::getInstanceInterface($hookID);
$shell = (new ReflectionMethod($hook, 'PhotoPage'))->invoke($hook, true, true);
$check(str_contains($shell, 'id="photo-widget"'), 'The HTTP shell must render even when the concrete class has an evaluated or synthetic filename.');

// A real valid PNG with a large trailing payload exercises encoded byte limits.
$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', true) . random_bytes(2200000);
IPS_SetProperty($photoAccountID, 'EnablePhotos', true);
IPS_SetProperty($photoAccountID, 'PhotoCacheMiB', 8);
IPS_ApplyChanges($photoAccountID);
$saved = $photoAccount->GetPersistence();
$saved['attributes']['Session'] = json_encode($session, JSON_THROW_ON_ERROR);
$saved['attributes']['PhotoAccess'] = true;
$saved['attributes']['PhotoRetryAt'] = 0;
$saved['attributes']['PhotoAlbums'] = json_encode(['albums' => [['id' => 'synthetic-album', 'name' => 'Fixture', 'kind' => 'album']], 'updatedAt' => time()], JSON_THROW_ON_ERROR);
$saved['attributes']['PhotoAssets'] = json_encode([hash('sha256', "synthetic-album\0large") => ['id' => 'large', 'revision' => 'large-version', 'url' => 'https://test.icloud-content.com/fixture']], JSON_THROW_ON_ERROR);
$photoAccount->ApplyPersistence($saved);
$cache = new \SymconiCloud\PhotoDiskCache(PhotosFixtureAccount::$root . '/' . $photoAccountID, 2, 8388608);
try {
    $cache->put('large:large-version', $bytes);
    $begin = $forward('GetPhotoImage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'large']);
    $check(strlen(json_encode($begin, JSON_THROW_ON_ERROR)) < 245760 && !isset($begin['image']), 'Image initiation must stay below the Symcon buffer limit and return transfer metadata rather than a complete Base64 image.');
    $transfer = $begin['transfer'];
    $encoded = '';
    for ($number = 0; $number < $transfer['PageCount']; $number++) {
        $page = $forward('GetPhotoImagePage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'large', 'Token' => $transfer['Token'], 'Page' => $number]);
        $check($page['success'] && $page['Page'] === $number && $page['Token'] === $transfer['Token'] && strlen(json_encode($page, JSON_THROW_ON_ERROR)) < 245760, 'Every data-flow and HTTP image page must stay below 240 KiB.');
        $encoded .= implode('', $page['Items']);
    }
    $check(base64_decode($encoded, true) === $bytes, 'Paging must preserve every byte of a multi-megabyte image.');
    $check(!$forward('GetPhotoImagePage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'other', 'Token' => $transfer['Token'], 'Page' => 0])['success'], 'A transfer must not be readable using another photo identity.');
    $check($forward('ClearPhotoImageTransfer', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'large', 'Token' => $transfer['Token']])['success'], 'Completed image transfers must be removed.');
    $check(!$forward('GetPhotoImagePage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'large', 'Token' => $transfer['Token'], 'Page' => 0])['success'], 'Cleared transfer tokens must no longer return image bytes.');
} finally {
    $photoAccount->Logout();
    if (is_dir(PhotosFixtureAccount::$root . '/' . $photoAccountID)) {
        rmdir(PhotosFixtureAccount::$root . '/' . $photoAccountID);
    }
    if (is_dir(PhotosFixtureAccount::$root)) {
        rmdir(PhotosFixtureAccount::$root);
    }
}
echo "Photo WebHook context and large-image transfer boundaries passed.\n";
