<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account.php';
ob_end_clean();

use SymconiCloud\AppleException;
use SymconiCloud\ApplePhotoDownload;
use SymconiCloud\PhotosLibrary;

class PhotosFixtureAccount extends AccountFixtureModule
{
    public static string $root;
    public int $downloads = 0;

    protected function PhotoCacheDirectory(): string
    {
        return self::$root . '/' . $this->InstanceID;
    }

    protected function DownloadPhotoPreview(string $url): string
    {
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
foreach (['Enabled' => true, 'EnablePhotos' => true, 'Username' => 'photos@example.invalid', 'Password' => 'SYNTHETIC', 'PhotoCacheProfile' => 'custom', 'PhotoCacheImages' => 1, 'PhotoCacheMiB' => 1] as $property => $value) {
    IPS_SetProperty($photoAccountID, $property, $value);
}
IPS_ApplyChanges($photoAccountID);
$persistence = $photoAccount->GetPersistence();
$session = json_decode($sessionJSON, true, 512, JSON_THROW_ON_ERROR);
$session['photos'] = 'https://p01-ckdatabasews.icloud.com';
$persistence['attributes']['Session'] = json_encode($session, JSON_THROW_ON_ERROR);
$photoAccount->ApplyPersistence($persistence);
$photoAccount->transport = new AccountFixtureTransport();
$transport = $photoAccount->transport;
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
$master = static fn (string $id, bool $hidden = false): array => [
    ['recordType' => 'CPLMaster', 'recordName' => $id, 'fields' => ['filenameEnc' => ['value' => base64_encode('Family.jpg')], 'resJPEGLargeRes' => ['value' => ['size' => 68, 'downloadURL' => 'https://test.icloud-content.com/preview/' . $id . '?signature=SYNTHETIC-PRIVATE']], 'resJPEGLargeFingerprint' => ['value' => 'revision-1']]],
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
    foreach ([0, 1] as $index) {
        $childID = IPS_CreateInstance($photoModule);
        IPS_ConnectInstance($childID, $photoAccountID);
        foreach (['Enabled' => true, 'EnableIPSView' => true, 'AlbumID' => 'synthetic-album'] as $property => $value) {
            IPS_SetProperty($childID, $property, $value);
        }
        $before = count($transport->requests);
        IPS_ApplyChanges($childID);
        $widget = IPS\InstanceManager::getInstanceInterface($childID);
        $widget->GetConfigurationForm();
        $check(count($transport->requests) === $before, 'Applying widget configuration and opening its form must not contact Apple.');
        $page(array_merge($master('photo-1'), $master('hidden', true)));
        $check($widget->Refresh(), 'Explicit widget refresh must load album metadata.');
        $model = $widget->ReadPhotoWidget('model');
        $check(count($model['state']['items']) === 1 && $model['state']['items'][0]['id'] === 'photo-1', 'Hidden images must not reach the browser model.');
        $check(!str_contains(json_encode($model), 'PRIVATE') && !str_contains(json_encode($model), 'downloadURL'), 'Signed Apple URLs and cookies must remain account private.');
        $image = $widget->ReadPhotoWidget('image', 0, 'photo-1');
        $check($image['success'] && $image['mime'] === 'image/png', 'Known album member must produce a validated raster preview.');
        $widgetIDs[] = $childID;
    }
    $check($photoAccount->downloads === 1, 'Two widgets must reuse the same account disk cache and download only once.');
    $page($master('photo-2'));
    $later = $forward('GetPhotoPage', ['AlbumID' => 'synthetic-album', 'Offset' => 1]);
    $check($later['offset'] === 1 && $later['next'] === null, 'Large albums must remain pageable beyond the cache count.');
    $check($forward('GetPhotoImage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'photo-2'])['success'], 'Later image must enter the cache.');
    $check($forward('GetPhotoAlbums')['cache']['images'] === 1 && $photoAccount->downloads === 2, 'Account count limit applies across all widgets.');
    $check(!$forward('GetPhotoImage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'https://evil.invalid/image'])['success'], 'Unknown client URLs must never become outbound image requests.');
    $check(!$forward('GetPhotoPage', ['AlbumID' => 'synthetic-album', 'Offset' => -1])['success'], 'Invalid offsets must be rejected before transport.');
    $check(!$forward('GetPhotoImage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'photo-2', 'URL' => 'https://evil.invalid'])['success'], 'Extra forwarding parameters must be rejected.');
    $child = IPS\InstanceManager::getInstanceInterface($widgetIDs[0]);
    $html = $child->GetVisualizationTile();
    $check(str_contains($html, 'SymconVisualizationInteraction') && str_contains($html, 'SymconHTTPOrigin') && !str_contains($html, '{{'), 'Complete native tile must compose central wheel, origin and rendering helpers.');
    $shell = (new ReflectionMethod($child, 'PhotoPage'))->invoke($child, true, true);
    $check(!str_contains($shell, 'SYNTHETIC-PRIVATE') && !str_contains($shell, 'Family.jpg') && !str_contains($shell, $child->GetPersistence()['attributes']['PhotoToken']), 'Unauthenticated HTTP shell must not expose images, metadata or its access token.');
    $oldToken = $child->GetPersistence()['attributes']['PhotoToken'];
    IPS_SetProperty($widgetIDs[0], 'AlbumID', 'smart:favorites');
    IPS_ApplyChanges($widgetIDs[0]);
    $check($child->GetPersistence()['attributes']['PhotoToken'] !== $oldToken && $child->ReadPhotoWidget('model')['state']['items'] === [], 'Album change must rotate bearer access and clear the previous model.');
    $catalog(403);
    $check(!$photoAccount->RefreshPhotoAlbums(), 'ADP/web access refusal must be a visible failure.');
    $check(!$forward('GetPhotoAlbums')['success'] && !$forward('GetPhotoImage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'photo-2'])['success'], 'Revoked Photos access must also deny images already cached on disk.');
    $check($child->ReadPhotoWidget('model')['revoked'], 'Widgets must clear private pictures when account access is revoked.');
    $catalog();
    $check($photoAccount->RefreshPhotoAlbums(), 'Explicit access approval can recover without a password login.');
    $transport->enqueue('POST', '/internal/records/query/batch?', 503);
    $check(!$forward('GetPhotoPage', ['AlbumID' => 'synthetic-album', 'Offset' => 0])['success'], 'A server failure must stop the current metadata operation.');
    $beforeFailureRetry = count($transport->requests);
    $check(!$forward('GetPhotoPage', ['AlbumID' => 'synthetic-album', 'Offset' => 0])['success'] && count($transport->requests) === $beforeFailureRetry, 'Photo failures must back off instead of producing a tight retry loop.');
    $check($forward('GetPhotoImage', ['AlbumID' => 'synthetic-album', 'PhotoID' => 'photo-2'])['success'], 'Already cached images remain usable during photo-specific network backoff.');
    $persistence = $photoAccount->GetPersistence();
    $persistence['attributes']['PhotoRetryAt'] = 0;
    // Begin the independent token-expiry scenario after both cooldowns elapsed.
    $photoAccount->clock = $persistence['attributes']['RetryAt'] + 1;
    $photoAccount->ApplyPersistence($persistence);
    $catalog(401);
    $check(!$photoAccount->RefreshPhotoAlbums(), 'An expired Apple session must stop Photos operations.');
    $inventoryAfterExpiry = json_decode($photoAccount->GetDevices(), true, 512, JSON_THROW_ON_ERROR);
    $check(!$inventoryAfterExpiry['success'] && $inventoryAfterExpiry['state'] === 'expired', 'A Photos session expiry must also invalidate Find My availability.');
    IPS_SetProperty($photoAccountID, 'EnablePhotos', false);
    IPS_ApplyChanges($photoAccountID);
    $check(!is_file(PhotosFixtureAccount::$root . '/' . $photoAccountID . '/index.json'), 'Disabling Photos clears account-owned disk images.');
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
    $optionalClient = new \SymconiCloud\AppleClient($optionalTransport, $optionalSession);
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
    if (is_dir(PhotosFixtureAccount::$root . '/' . $photoAccountID)) {
        rmdir(PhotosFixtureAccount::$root . '/' . $photoAccountID);
    }
    if (is_dir(PhotosFixtureAccount::$root)) {
        rmdir(PhotosFixtureAccount::$root);
    }
}
echo "Photos protocol, account cache, widget transport and access boundaries passed.\n";
