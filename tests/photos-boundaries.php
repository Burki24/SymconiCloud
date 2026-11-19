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
$photoAccount->ApplyPersistence($saved);
$cache = (new ReflectionMethod($photoAccount, 'PhotoCache'))->invoke($photoAccount);
try {
    $cache->synchronize('synthetic-album', [['id' => 'large', 'name' => 'Large', 'date' => 0, 'revision' => 'large-version', 'url' => 'fixture']], static fn (): string => $bytes);
    $before = count($transport->requests);
    $album = ['AlbumID' => 'synthetic-album'];
    $begin = $forward('GetPhotoImage', $album + ['PhotoID' => 'large']);
    $check($begin['success'] && $begin['bytes'] === strlen($bytes) && !isset($begin['data']), 'Image metadata contains only local dimensions needed for transfer.');
    $complete = '';
    for ($number = 0; $number < $begin['pages']; $number++) {
        $page = $forward('GetPhotoImagePage', $album + ['PhotoID' => 'large', 'Version' => $begin['version'], 'Page' => $number]);
        $check($page['success'] && $page['page'] === $number && $page['version'] === $begin['version'] && strlen(json_encode($page, JSON_THROW_ON_ERROR)) < 245760, 'Each file slice must fit inside the Symcon buffer limit.');
        $complete .= base64_decode($page['data'], true);
    }
    $check($complete === $bytes && count($transport->requests) === $before, 'All bytes of a multi-megabyte image must come from the local file.');
    $check(!$forward('GetPhotoImagePage', $album + ['PhotoID' => 'other', 'Version' => $begin['version'], 'Page' => 0])['success'], 'A version must not authorize another photo identity.');
    $check(!$forward('GetPhotoImagePage', ['AlbumID' => 'smart:all', 'PhotoID' => 'large', 'Version' => $begin['version'], 'Page' => 0])['success'], 'An image in one album must not satisfy a request for another.');
    $check(!$forward('GetPhotoImagePage', $album + ['PhotoID' => 'large', 'Version' => $begin['version'], 'Page' => $begin['pages']])['success'], 'Requests beyond the file end must be rejected.');
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
