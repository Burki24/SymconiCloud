<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account.php';
ob_end_clean();

use ICloudServices\AppleException;
use ICloudServices\ApplePhotoDownload;
use ICloudServices\PhotosLibrary;

class PhotosFixtureAccount extends AccountFixtureModule
{
    public static string $root;
    public int $downloads = 0;
    public array $downloadedIDs = [];
    public string $failID = '';

    protected function PhotoCacheDirectory(): string
    {
        return self::$root . '/' . $this->InstanceID;
    }

    protected function FormerUserPhotoCacheDirectory(): string
    {
        return self::$root . '/user-' . $this->InstanceID;
    }

    protected function FormerPrivatePhotoCacheDirectory(): string
    {
        return self::$root . '/previous-' . $this->InstanceID;
    }

    protected function DownloadPhotoPreview(string $url): string
    {
        ApplePhotoDownload::assertURL($url);
        $id = basename((string) parse_url($url, PHP_URL_PATH));
        if ($id === $this->failID) {
            throw new AppleException('network');
        }
        $this->downloadedIDs[] = $id;
        $this->downloads++;
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', true);
    }
}
PhotosFixtureAccount::$root = sys_get_temp_dir() . '/symcon-photos-' . bin2hex(random_bytes(8));
$loaded = $metadata->getValue();
$loaded[$accountModule]['Class'] = PhotosFixtureAccount::class;
$metadata->setValue(null, $loaded);
$photoAccountID = IPS_CreateInstance($accountModule);
/** @var PhotosFixtureAccount $photoAccount */
$photoAccount = IPS\InstanceManager::getInstanceInterface($photoAccountID);
foreach (['Enabled' => true, 'EnablePhotos' => true, 'Username' => 'photos@example.invalid', 'Password' => 'SYNTHETIC', 'PhotoCacheProfile' => 'custom', 'PhotoCacheImages' => 2, 'PhotoCacheMiB' => 1] as $property => $value) {
    IPS_SetProperty($photoAccountID, $property, $value);
}
IPS_ApplyChanges($photoAccountID);
$persistence = $photoAccount->GetPersistence();
$session = json_decode($sessionJSON, true, 512, JSON_THROW_ON_ERROR);
$session['photos'] = 'https://p01-ckdatabasews.icloud.com';
$persistence['attributes']['Session'] = json_encode($session, JSON_THROW_ON_ERROR);
$persistence['attributes']['PhotoAssets'] = '{"legacy":{"url":"https://test.icloud-content.com/legacy?signature=SYNTHETIC"}}';
$photoAccount->ApplyPersistence($persistence);
$photoAccount->transport = new AccountFixtureTransport();
$transport = $photoAccount->transport;
$expectedSuffix = '/iCloudServices/photos/' . $photoAccountID;
$realPath = (new ReflectionMethod(iCloudAccount::class, 'PhotoCacheDirectory'))->invoke($photoAccount);
$check(str_ends_with(str_replace('\\', '/', $realPath), $expectedSuffix), 'Production previews must use the private Symcon data directory.');
$privateDirectory = PhotosFixtureAccount::$root . '/' . $photoAccountID;
$formerUserDirectory = PhotosFixtureAccount::$root . '/user-' . $photoAccountID;
$legacyDirectory = PhotosFixtureAccount::$root . '/previous-' . $photoAccountID;
mkdir($legacyDirectory, 0700, true);
$legacyPreview = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', true);
$legacyKey = hash('sha256', 'legacy-photo:revision');
file_put_contents($legacyDirectory . '/' . $legacyKey . '.bin', $legacyPreview);
file_put_contents($legacyDirectory . '/index.json', json_encode([$legacyKey => strlen($legacyPreview)], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($photoAccountID);
$check($photoAccount->GetPersistence()['attributes']['PhotoAssets'] === '{}' && $transport->requests === [], 'An upgrade must clear legacy signed URLs without contacting Apple.');
$movedPreview = PhotosFixtureAccount::$root . '/' . $photoAccountID . '/scphoto-' . $legacyKey . '.bin';
$check(is_file($movedPreview) && file_get_contents($movedPreview) !== $legacyPreview && !is_file($legacyDirectory . '/index.json'), 'Applying the update must move and encrypt existing previews.');
rmdir($legacyDirectory);
$photoModule = '{7F1383D4-5B82-4F95-A52B-664E56FA5C3A}';
$forward = static fn (string $command, array $args = []): array => json_decode($photoAccount->ForwardData(json_encode(['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => $command] + $args, JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
$records = [['recordType' => 'CPLAlbum', 'recordName' => 'synthetic-album', 'fields' => ['albumNameEnc' => ['value' => base64_encode('Family <script>')]]]];
$catalog = static function (int $status = 200) use ($transport, $records): void
{
    $transport->enqueue('POST', '/records/query?', $status, ['records' => [['fields' => ['state' => ['value' => 'FINISHED']]]]]);
    if ($status === 200) {
        $transport->enqueue('POST', '/records/query?', 200, ['records' => $records]);
    }
};
$master = static fn (string $id, bool $hidden = false, string $revision = 'revision-1'): array => [
    ['recordType' => 'CPLMaster', 'recordName' => $id, 'fields' => ['filenameEnc' => ['value' => base64_encode('Family.jpg')], 'resJPEGLargeRes' => ['value' => ['size' => 68, 'downloadURL' => 'https://test.icloud-content.com/preview/' . $id . '?signature=SYNTHETIC-PRIVATE']], 'resJPEGLargeFingerprint' => ['value' => $revision]]],
    ['recordType' => 'CPLAsset', 'recordName' => 'asset-' . $id, 'fields' => ['masterRef' => ['value' => ['recordName' => $id]], 'assetDate' => ['value' => 1700000000000], 'isHidden' => ['value' => $hidden]]]
];
$page = static function (array $items, int $total = 2) use ($transport): void
{
    $transport->enqueue('POST', '/internal/records/query/batch?', 200, ['batch' => [['records' => [['fields' => ['itemCount' => ['value' => $total]]]]]]]);
    $transport->enqueue('POST', '/records/query?', 200, ['records' => $items]);
};
try {
    $catalog();
    $check($photoAccount->RefreshPhotoAlbums(), 'Existing authenticated account must load a read-only Photos catalog.');
    $albums = $forward('GetPhotoAlbums');
    $check(count($albums['albums']) === 3 && $albums['albums'][2]['name'] === 'Family <script>', 'Album titles must remain data and smart albums must be available.');
    $widgetIDs = [];
    $before = count($transport->requests);
    foreach ([0, 1] as $index) {
        $childID = IPS_CreateInstance($photoModule);
        IPS_ConnectInstance($childID, $photoAccountID);
        foreach (['Enabled' => true, 'EnableIPSView' => true, 'AlbumID' => 'synthetic-album'] as $property => $value) {
            IPS_SetProperty($childID, $property, $value);
        }
        IPS_ApplyChanges($childID);
        $widget = IPS\InstanceManager::getInstanceInterface($childID);
        $widget->GetConfigurationForm();
        $check($widget->ReadPhotoWidget('model')['state']['items'] === [], 'An album is empty until manually synchronized.');
        $widgetIDs[] = $childID;
    }
    $check(count($transport->requests) === $before && $photoAccount->downloads === 0, 'Creating or opening a widget and reading its model must remain local.');
    $child = IPS\InstanceManager::getInstanceInterface($widgetIDs[0]);
    $album = ['AlbumID' => 'synthetic-album'];
    $page(array_merge($master('photo-1'), $master('hidden', true)));
    $check($child->Refresh() && $photoAccount->downloadedIDs === ['photo-1'], 'Explicit synchronization must download the eligible preview once.');
    $model = $widget->ReadPhotoWidget('model');
    $check(count($model['state']['items']) === 1 && $model['state']['items'][0]['id'] === 'photo-1', 'Other widgets reuse the published snapshot and hidden images stay private.');
    $check(!str_contains(json_encode($model), 'PRIVATE') && !str_contains(json_encode($model), 'downloadURL'), 'Signed Apple URLs and cookies must remain account private.');
    $oldImage = $widget->ReadPhotoWidget('image', 0, 'photo-1');
    $check($oldImage['success'] && $oldImage['mime'] === 'image/png', 'Known album member must produce a validated local preview.');

    $page($master('photo-1'));
    $page($master('photo-2'));
    $check($child->Refresh() && $photoAccount->downloadedIDs === ['photo-1', 'photo-2'], 'Resynchronization downloads only newly added previews.');
    $page(array_merge($master('photo-1', false, 'revision-2'), $master('photo-2')));
    $check($widget->Refresh() && $photoAccount->downloadedIDs === ['photo-1', 'photo-2', 'photo-1'], 'Resynchronization downloads the changed revision and reuses the other preview.');
    $snapshot = $forward('GetPhotoPage', $album);
    $image = $widget->ReadPhotoWidget('image', 0, 'photo-1');
    $check($snapshot['total'] === 2 && $image['version'] !== $oldImage['version'], 'A revised preview must carry a new local version without changing its photo ID.');
    $pageArgs = $album + ['PhotoID' => 'photo-1', 'Page' => 0];
    $before = count($transport->requests);
    $check($forward('GetPhotoImagePage', $pageArgs + ['Version' => $image['version']])['success'], 'Playback can read stored bytes.');
    $check(!$forward('GetPhotoImagePage', $pageArgs + ['Version' => $oldImage['version']])['success'], 'Playback cannot read an obsolete revision after synchronization.');
    $check(!$forward('GetPhotoImage', $album + ['PhotoID' => 'https://evil.invalid/image'])['success'], 'An unknown ID must never become an outbound request.');
    $check(!$forward('GetPhotoPage', $album + ['Offset' => -1])['success'], 'Invalid offsets must fail locally.');
    $check(!$forward('GetPhotoImage', $album + ['PhotoID' => 'photo-2', 'URL' => 'https://evil.invalid'])['success'], 'Extra forwarding parameters must be rejected.');
    $check(count($transport->requests) === $before && $photoAccount->downloads === 3, 'Model, pagination and image reads must never request Apple.');

    // Beta 0.49 stored the same sealed format in user/iPhotos.
    $attributesBeforeMigration = $photoAccount->GetPersistence()['attributes'];
    rename($privateDirectory, $formerUserDirectory);
    file_put_contents($formerUserDirectory . '/unrelated.txt', 'user file');
    file_put_contents($privateDirectory, 'blocked target');
    IPS_ApplyChanges($photoAccountID);
    $check(is_file($formerUserDirectory . '/scphoto-albums.json') && file_get_contents($privateDirectory) === 'blocked target', 'A failed move must leave the former album and the obstructing file intact.');
    $check($photoAccount->GetPersistence()['attributes']['PhotoError'] !== '', 'A failed move must surface a photo storage error.');
    unlink($privateDirectory);
    IPS_ApplyChanges($photoAccountID);
    IPS_ApplyChanges($photoAccountID);
    $afterMigration = $forward('GetPhotoImagePage', $pageArgs + ['Version' => $image['version']]);
    $check($forward('GetPhotoPage', $album) === $snapshot && ($afterMigration['success'] ?? false) && base64_decode($afterMigration['data'], true) === $legacyPreview, 'The album and image bytes must survive a retried move without a new synchronization.');
    $check(count($transport->requests) === $before && $photoAccount->downloads === 3, 'Applying the photo migration must remain entirely local.');
    $attributesAfterMigration = $photoAccount->GetPersistence()['attributes'];
    $check($attributesAfterMigration['PhotoError'] === ''
        && $attributesAfterMigration['PhotoStorageKey'] === $attributesBeforeMigration['PhotoStorageKey']
        && $attributesAfterMigration['PhotoStorageID'] === $attributesBeforeMigration['PhotoStorageID'], 'Successful migration must clear the error and preserve the account storage identity.');
    $check(glob($formerUserDirectory . '/scphoto-*') === [] && file_get_contents($formerUserDirectory . '/unrelated.txt') === 'user file', 'Only the managed files must be removed from the former location.');

    rename($privateDirectory, $legacyDirectory);
    IPS_ApplyChanges($photoAccountID);
    $check($forward('GetPhotoPage', $album) === $snapshot
        && $forward('GetPhotoImagePage', $pageArgs + ['Version' => $image['version']]) === $afterMigration
        && glob($legacyDirectory . '/scphoto-*') === []
        && count($transport->requests) === $before, 'The previous private folder must also migrate complete albums without a cloud request.');
    rmdir($legacyDirectory);

    $photoAccount->failID = 'failed';
    $page(array_merge($master('staged'), $master('failed')));
    $check(!$child->Refresh(), 'A failed preview download must fail the requested synchronization.');
    $check($forward('GetPhotoPage', $album) === $snapshot && $widget->ReadPhotoWidget('image', 0, 'photo-1')['success'], 'An interrupted synchronization must preserve the last complete snapshot.');
    $check(!$forward('GetPhotoImage', $album + ['PhotoID' => 'staged'])['success'], 'Staged images must not enter playback after an interrupted synchronization.');
    $before = count($transport->requests);
    $check(!$child->Refresh() && count($transport->requests) === $before, 'The existing backoff must also apply to manual synchronization.');

    $html = $child->GetVisualizationTile();
    $check(str_contains($html, 'SymconVisualizationInteraction') && str_contains($html, 'SymconHTTPOrigin') && !str_contains($html, '{{'), 'Native tile must keep the central visual helpers.');
    $shell = (new ReflectionMethod($child, 'PhotoPage'))->invoke($child, true, true);
    $check(!str_contains($shell, 'SYNTHETIC-PRIVATE') && !str_contains($shell, 'Family.jpg') && !str_contains($shell, $child->GetPersistence()['attributes']['PhotoToken']), 'Unauthenticated HTTP shell must exclude album details and its token.');
    $oldToken = $child->GetPersistence()['attributes']['PhotoToken'];
    IPS_SetProperty($widgetIDs[0], 'AlbumID', 'smart:favorites');
    IPS_ApplyChanges($widgetIDs[0]);
    $check($child->GetPersistence()['attributes']['PhotoToken'] !== $oldToken && $child->ReadPhotoWidget('model')['state']['items'] === [], 'Album changes rotate the token and replace the available album.');
    $check(!$child->ReadPhotoWidget('image', 0, 'photo-1')['success'], 'A widget cannot request a photo from a different album.');

    $persistence = $photoAccount->GetPersistence();
    $persistence['attributes']['PhotoRetryAt'] = 0;
    $photoAccount->clock = max(time(), $persistence['attributes']['RetryAt'] + 1);
    $photoAccount->ApplyPersistence($persistence);
    $catalog(403);
    $check(!$photoAccount->RefreshPhotoAlbums(), 'ADP/web access refusal must be a visible failure.');
    $check($widget->ReadPhotoWidget('image', 0, 'photo-1')['success'], 'Playback stays local during an Apple photo access error.');
    $catalog();
    $check($photoAccount->RefreshPhotoAlbums(), 'Explicit access approval can recover without a password login.');
    $catalog(401);
    $check(!$photoAccount->RefreshPhotoAlbums(), 'An expired Apple session must stop cloud Photos operations.');
    $inventoryAfterExpiry = json_decode($photoAccount->GetDevices(), true, 512, JSON_THROW_ON_ERROR);
    $check(!$inventoryAfterExpiry['success'] && $inventoryAfterExpiry['state'] === 'expired', 'A Photos session expiry must also invalidate Find My availability.');
    $check($widget->ReadPhotoWidget('image', 0, 'photo-1')['success'], 'A cloud session expiry must not prevent local playback.');
    IPS_SetProperty($photoAccountID, 'EnablePhotos', false);
    IPS_ApplyChanges($photoAccountID);
    $check($widget->ReadPhotoWidget('model')['revoked'] && glob(PhotosFixtureAccount::$root . '/' . $photoAccountID . '/*') === [], 'Disabling Photos revokes playback and removes all account-owned files.');
    $check($transport->queue === [], 'Every queued protocol operation must have been consumed.');
    foreach ($transport->requests as $request) {
        $check($request['method'] === 'POST' && str_contains($request['url'], '/private/') && !str_contains($request['url'], 'modify'), 'Photo transport is limited to read-only CloudKit queries.');
    }
    $queries = array_values(array_filter($transport->requests, static fn (array $request): bool => ($request['body']['query']['recordType'] ?? '') === 'CPLContainerRelationLiveByAssetDate'));
    $filters = array_column($queries[0]['body']['query']['filterBy'], 'fieldValue', 'fieldName');
    $check($filters['parentId']['value'] === 'synthetic-album' && $filters['direction']['value'] === 'DESCENDING' && $filters['startRank']['value'] === 1, 'Album paging must start from its last rank and use the fixed selected container.');
    $check($queries[0]['body']['resultsLimit'] === 40 && !in_array('locationEnc', $queries[0]['body']['desiredKeys'], true), 'Pages request at most twenty master/asset pairs and no photo GPS metadata.');
    $optionalSession = $session;
    unset($optionalSession['photos']);
    $optionalTransport = new AccountFixtureTransport();
    $optionalTransport->enqueue('POST', '/accountLogin', 200, ['dsInfo' => ['dsid' => 'synthetic', 'hsaVersion' => 2], 'hsaTrustedBrowser' => true, 'webservices' => ['findme' => ['url' => 'https://findme.icloud.com']]]);
    $optionalClient = new \ICloudServices\AppleClient($optionalTransport, $optionalSession);
    try {
        $optionalClient->photoAlbums();
        throw new RuntimeException('Missing optional Photos service claimed available.');
    }
    catch (AppleException $error) {
        $check($error->reason === 'photosUnavailable' && $optionalClient->session()['state'] === 'authenticated', 'Missing Photos must not break Find My or start password authentication.');
    }
    $check(count($optionalTransport->requests) === 1 && $optionalTransport->queue === [], 'Legacy sessions may discover Photos once through token accountLogin only.');
    foreach (['http://test.icloud-content.com/x', 'https://evil.invalid/x', 'https://icloud-content.com.evil.invalid/x', 'https://name:password@test.icloud.com/x', 'https://test.icloud.com:8088/x', 'https://127.0.0.1/x'] as $url) {
        try {
            ApplePhotoDownload::assertURL($url);
            throw new RuntimeException('Unsafe preview URL accepted.');
        } catch (AppleException $error) {
            $check($error->reason === 'endpoint', 'Unsafe hosts must fail locally.');
        }
    }
    ApplePhotoDownload::assertURL('https://test.icloud-content.com/image?signature=opaque');
    try {
        ApplePhotoDownload::mime('<svg onload="alert(1)"/>');
        throw new RuntimeException('SVG accepted');
    } catch (AppleException $error) {
        $check($error->reason === 'photoFormat', 'Executable images must not be served.');
    }
    $pending = new PhotosLibrary(static fn (string $path, array $body): array => ['records' => [['fields' => ['state' => ['value' => 'INDEXING']]]]]);
    try {
        $pending->albums();
        throw new RuntimeException('Indexing claimed ready.');
    } catch (AppleException $error) {
        $check($error->reason === 'photosIndexing', 'Unprepared libraries need an explicit state.');
    }
} finally {
    $photoAccount->Logout();
    if (is_file($privateDirectory)) {
        unlink($privateDirectory);
    }
    if (is_dir(PhotosFixtureAccount::$root . '/' . $photoAccountID)) {
        rmdir(PhotosFixtureAccount::$root . '/' . $photoAccountID);
    }
    if (is_dir($formerUserDirectory)) {
        if (is_file($formerUserDirectory . '/unrelated.txt')) {
            unlink($formerUserDirectory . '/unrelated.txt');
        }
        rmdir($formerUserDirectory);
    }
    if (is_dir(PhotosFixtureAccount::$root)) {
        rmdir(PhotosFixtureAccount::$root);
    }
}
echo "Photos protocol, account cache, widget transport and access boundaries passed.\n";
