<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/PhotoDiskCache.php';
require_once __DIR__ . '/ApplePhotoDownload.php';
require_once __DIR__ . '/helper/ChunkedJsonTransferHelper.php';

/** Library-local Photos capability and shared account cache; never changes the media library. */
trait PhotosAccount
{
    use \Burki24\SymconModuleHelper\ChunkedJsonTransferHelper;
    /** Normalized catalog only. Reading this method never initiates Apple authentication. */
    public function GetPhotoAlbums(): string
    {
        try {
            $this->assertPhotosAccount();
            if (!$this->ReadAttributeBoolean('PhotoAccess')) {
                throw new AppleException('photosAccess');
            }
            $catalog = json_decode($this->ReadAttributeString('PhotoAlbums'), true, 512, JSON_THROW_ON_ERROR);
            return json_encode(['success' => true, 'albums' => $catalog['albums'], 'updatedAt' => $catalog['updatedAt'], 'cache' => $this->PhotoCache()->stats()], JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $fallback = $exception instanceof AppleException && $exception->reason === 'photosAccess' ? 'Check Photos access and load albums' : 'Enable Photos and sign in to the account first.';
            return json_encode(['success' => false, 'albums' => [], 'updatedAt' => 0, 'error' => $this->ReadAttributeString('PhotoError') ?: $this->Translate($fallback)], JSON_THROW_ON_ERROR);
        }
    }

    /** Explicit read-only access check and catalog refresh. */
    public function RefreshPhotoAlbums(): bool
    {
        $success = $this->operatePhotos('catalog')['success'];
        $this->ReloadForm();
        return $success;
    }
    protected function RegisterPhotosAccount(): void
    {
        $this->RegisterPropertyBoolean('EnablePhotos', false);
        $this->RegisterPropertyString('PhotoCacheProfile', 'compact');
        $this->RegisterPropertyInteger('PhotoCacheImages', 50);
        $this->RegisterPropertyInteger('PhotoCacheMiB', 100);
        $this->RegisterAttributeString('PhotoAlbums', '{"albums":[],"updatedAt":0}');
        $this->RegisterAttributeString('PhotoAssets', '{}');
        $this->RegisterAttributeString('PhotoError', '');
        $this->RegisterAttributeBoolean('PhotoAccess', false);
        $this->RegisterAttributeInteger('PhotoRetryAt', 0);
    }

    protected function ClearPhotoState(): void
    {
        foreach ($this->photoTransfers() as $token => $transfer) {
            $this->ClearChunkedJsonTransfer($transfer['scope'], $token);
        }
        $this->SetBuffer('PhotoImageTransfers', '');
        $this->WriteAttributeString('PhotoAlbums', '{"albums":[],"updatedAt":0}');
        $this->WriteAttributeString('PhotoAssets', '{}');
        $this->WriteAttributeString('PhotoError', '');
        $this->WriteAttributeBoolean('PhotoAccess', false);
        $this->WriteAttributeInteger('PhotoRetryAt', 0);
        try {
            (new PhotoDiskCache($this->PhotoCacheDirectory(), 50, 104857600))->clear();
        }
        catch (\Throwable) {
            $this->WriteAttributeString('PhotoError', $this->Translate('Photo cache unavailable or configured limits are invalid.'));
        }
    }

    /** Private storage outside webfront/user and the Git module tree. */
    protected function PhotoCacheDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '/\\') . '/SymconiCloud/photos/' . $this->InstanceID;
    }

    protected function DownloadPhotoPreview(string $url): string
    {
        return ApplePhotoDownload::download($url);
    }

    protected function PhotoCache(): PhotoDiskCache
    {
        [$count, $bytes] = PhotoDiskCache::limits($this->ReadPropertyString('PhotoCacheProfile'), $this->ReadPropertyInteger('PhotoCacheImages'), $this->ReadPropertyInteger('PhotoCacheMiB'));
        return new PhotoDiskCache($this->PhotoCacheDirectory(), $count, $bytes);
    }

    protected function ForwardPhotosRequest(array $request): array
    {
        $command = $request['Command'];
        $allowed = ['DataID', 'Command'];
        if ($command !== 'GetPhotoAlbums' && $command !== 'RefreshPhotoAlbums') {
            $allowed[] = 'AlbumID';
        }
        if ($command === 'GetPhotoPage') {
            $allowed[] = 'Offset';
        }
        if (in_array($command, ['GetPhotoImage', 'GetPhotoImagePage', 'ClearPhotoImageTransfer'], true)) {
            $allowed[] = 'PhotoID';
        }
        if (in_array($command, ['GetPhotoImagePage', 'ClearPhotoImageTransfer'], true)) {
            $allowed[] = 'Token';
        }
        if ($command === 'GetPhotoImagePage') {
            $allowed[] = 'Page';
        }
        if (array_diff(array_keys($request), $allowed) !== []) {
            throw new \InvalidArgumentException();
        }
        if ($command === 'GetPhotoAlbums') {
            return json_decode($this->GetPhotoAlbums(), true, 512, JSON_THROW_ON_ERROR);
        }
        if ($command === 'RefreshPhotoAlbums') {
            return $this->operatePhotos('catalog');
        }
        if (!is_string($request['AlbumID'] ?? null) || strlen($request['AlbumID']) > 512
            || ($command === 'GetPhotoPage' && (!is_int($request['Offset'] ?? null) || $request['Offset'] < 0 || $request['Offset'] > 1000000000))
            || (in_array($command, ['GetPhotoImage', 'GetPhotoImagePage', 'ClearPhotoImageTransfer'], true) && (!is_string($request['PhotoID'] ?? null) || strlen($request['PhotoID']) > 512))
            || (in_array($command, ['GetPhotoImagePage', 'ClearPhotoImageTransfer'], true) && (!is_string($request['Token'] ?? null) || preg_match('/\A[a-f0-9]{32}\z/D', $request['Token']) !== 1))
            || ($command === 'GetPhotoImagePage' && (!is_int($request['Page'] ?? null) || $request['Page'] < 0 || $request['Page'] > 100))) {
            throw new \InvalidArgumentException();
        }
        $operation = match ($command) {
            'GetPhotoImage' => 'image', 'GetPhotoImagePage' => 'imagePage', 'ClearPhotoImageTransfer' => 'imageClear', default => 'page'
        };
        return $this->operatePhotos($operation, $request['AlbumID'], $request['Offset'] ?? 0, $request['PhotoID'] ?? '', $request['Token'] ?? '', $request['Page'] ?? 0);
    }

    private function assertPhotosAccount(): void
    {
        if (!$this->configurationValid() || !$this->ReadPropertyBoolean('EnablePhotos')
            || $this->credentialFingerprint() !== $this->ReadAttributeString('CredentialFingerprint') || ($this->readSession()['state'] ?? '') !== 'authenticated') {
            throw new AppleException('photosDisabled');
        }
    }

    private function operatePhotos(string $operation, string $albumID = '', int $offset = 0, string $photoID = '', string $token = '', int $pageNumber = 0): array
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return ['success' => false, 'error' => $this->Translate('Account request already running.')];
        }
        $client = null;
        $diagnosticOperation = match ($operation) {
            'catalog' => 'photoCatalog', 'page' => 'photoPage', default => 'photoImage'
        };
        $previousState = $this->readSession()['state'] ?? 'signedOut';
        try {
            $this->assertPhotosAccount();
            $cache = $this->PhotoCache();
            $cache->trim();
            if ($operation === 'catalog') {
                $this->AssertAccountRequestsAllowed();
                if ($this->ReadAttributeInteger('PhotoRetryAt') > $this->AccountProtectionTime()) {
                    throw new AppleException('rateLimited');
                }
                $client = $this->CreateAppleClient($this->readSession());
                $catalog = ['albums' => $client->photoAlbums(), 'updatedAt' => time()];
                $this->WriteAttributeString('PhotoAlbums', json_encode($catalog, JSON_THROW_ON_ERROR));
                $this->WriteAttributeString('PhotoError', '');
                $this->WriteAttributeInteger('TransientFailures', 0);
                $this->WriteAttributeBoolean('PhotoAccess', true);
                return ['success' => true] + $catalog;
            }
            if (!$this->ReadAttributeBoolean('PhotoAccess')) {
                throw new AppleException('photosAccess');
            }
            $catalog = json_decode($this->ReadAttributeString('PhotoAlbums'), true, 512, JSON_THROW_ON_ERROR);
            $album = null;
            foreach ($catalog['albums'] as $candidate) {
                if ($candidate['id'] === $albumID) {
                    $album = $candidate;
                    break;
                }
            }
            if ($album === null) {
                throw new AppleException('photosAlbum');
            }
            $scope = 'PhotoImage.' . substr(hash('sha256', $this->credentialFingerprint() . "\0" . $albumID . "\0" . $photoID), 0, 40);
            if (in_array($operation, ['imagePage', 'imageClear'], true)) {
                $transfers = $this->photoTransfers();
                if (($transfers[$token]['scope'] ?? '') !== $scope) {
                    throw new AppleException('photosAlbum');
                }
                if ($operation === 'imageClear') {
                    $this->ClearChunkedJsonTransfer($scope, $token);
                    unset($transfers[$token]);
                    $this->SetBuffer('PhotoImageTransfers', json_encode($transfers, JSON_THROW_ON_ERROR));
                    return ['success' => true];
                }
                return ['success' => true] + $this->ReadChunkedJsonTransferPage($scope, $token, $pageNumber);
            }
            $assets = json_decode($this->ReadAttributeString('PhotoAssets'), true, 512, JSON_THROW_ON_ERROR);
            if ($operation === 'page') {
                $this->AssertAccountRequestsAllowed();
                if ($this->ReadAttributeInteger('PhotoRetryAt') > $this->AccountProtectionTime()) {
                    throw new AppleException('rateLimited');
                }
                $client = $this->CreateAppleClient($this->readSession());
                $page = $client->photoPage($album, $offset);
                foreach ($page['items'] as &$item) {
                    $key = hash('sha256', $albumID . "\0" . $item['id']);
                    unset($assets[$key]);
                    $assets[$key] = $item;
                    unset($item['url'], $item['revision']);
                }
                unset($item);
                while (count($assets) > 2000) {
                    array_shift($assets);
                }
                $this->WriteAttributeString('PhotoAssets', json_encode($assets, JSON_THROW_ON_ERROR));
                $this->WriteAttributeString('PhotoError', '');
                $this->WriteAttributeInteger('TransientFailures', 0);
                return ['success' => true, 'album' => $album['name'], 'updatedAt' => time()] + $page;
            }
            $asset = $assets[hash('sha256', $albumID . "\0" . $photoID)] ?? null;
            if ($asset === null) {
                throw new AppleException('photosAlbum');
            }
            $key = $photoID . ':' . $asset['revision'];
            $bytes = $cache->get($key);
            if ($bytes === null) {
                $this->AssertAccountRequestsAllowed();
                if ($this->ReadAttributeInteger('PhotoRetryAt') > $this->AccountProtectionTime()) {
                    throw new AppleException('rateLimited');
                }
                ApplePhotoDownload::assertURL($asset['url']);
                $bytes = $this->DownloadPhotoPreview($asset['url']);
                ApplePhotoDownload::mime($bytes);
                if (!$cache->put($key, $bytes)) {
                    throw new AppleException('photosCache');
                }
                $this->RecordAccountDiagnostic('photoImage', 200, 'ok');
            }
            $transfers = $this->photoTransfers();
            foreach ($transfers as $oldToken => $old) {
                if ($old['expiresAt'] <= time()) {
                    $this->ClearChunkedJsonTransfer($old['scope'], $oldToken);
                    unset($transfers[$oldToken]);
                }
            }
            $this->SetBuffer('PhotoImageTransfers', json_encode($transfers, JSON_THROW_ON_ERROR));
            $metadata = $this->CreateChunkedJsonTransfer($scope, str_split(base64_encode($bytes), 131072));
            $transfers[$metadata['Token']] = ['scope' => $scope, 'expiresAt' => $metadata['ExpiresAt']];
            $this->SetBuffer('PhotoImageTransfers', json_encode($transfers, JSON_THROW_ON_ERROR));
            return ['success' => true, 'mime' => ApplePhotoDownload::mime($bytes), 'bytes' => strlen($bytes), 'transfer' => $metadata];
        } catch (\Throwable $exception) {
            $reason = $exception instanceof AppleException ? $exception->reason : 'photosCache';
            if ($exception instanceof AppleException) {
                $this->AccountRequestFailed($diagnosticOperation, $exception, $client !== null || $operation === 'image');
            } else {
                $this->RecordAccountDiagnostic($diagnosticOperation, 0, 'photosCache');
            }
            if (in_array($reason, ['network', 'server', 'photoDownload', 'photosCache'], true)) {
                $this->WriteAttributeInteger('PhotoRetryAt', $this->AccountProtectionTime() + 60);
            }
            if (in_array($reason, ['photosAccess', 'photosUnavailable', 'photosIndexing', 'photosDisabled', 'authentication', 'credentials'], true)) {
                $this->WriteAttributeBoolean('PhotoAccess', false);
            }
            $message = in_array($reason, ['requestsPaused', 'accountBlocked', 'accountLocked', 'rateLimited', 'retryBackoff', 'credentials', 'authentication', 'accessDenied', 'protectionState'], true) ? $this->errorMessage($reason) : $this->Translate(match ($reason) {
                'photosDisabled'               => 'Enable Photos and sign in to the account first.',
                'photosUnavailable'            => 'The Photos service is not available for this account.',
                'photosIndexing'               => 'The iCloud photo library is still being prepared.',
                'photosAccess'                 => 'Photo web access is unavailable or needs approval on a trusted device.',
                'photosAlbum'                  => 'Refresh albums and select an available photo album.',
                'photosCache'                  => 'Photo cache unavailable or configured limits are invalid.',
                'photoDownload', 'photoFormat' => 'Preview unavailable. Refresh photo data or choose another picture.',
                default                        => 'Photos could not be loaded. Check the account session and try again later.'
            });
            $this->WriteAttributeString('PhotoError', $message);
            return ['success' => false, 'error' => $message];
        } finally {
            try {
                if ($client !== null) {
                    $this->WriteAttributeString('Session', json_encode($client->session(), JSON_THROW_ON_ERROR));
                }
                $this->updateState();
            } finally {
                IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
            }
            if (($this->readSession()['state'] ?? 'signedOut') !== $previousState) {
                $this->notifyChildren();
            }
        }
    }

    private function photoTransfers(): array
    {
        return json_decode($this->GetBuffer('PhotoImageTransfers') ?: '{}', true, 32, JSON_THROW_ON_ERROR);
    }
}
