<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/PhotoDiskCache.php';
require_once __DIR__ . '/ApplePhotoDownload.php';

/** Library-local Photos capability and shared account cache; never changes the media library. */
trait PhotosAccount
{
    /** Normalized catalog only. Reading this method never initiates Apple authentication. */
    public function GetPhotoAlbums(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            try {
                if (!$this->photosConfigured()) {
                    throw new AppleException('photosDisabled');
                }
                $catalog = $this->photoCatalog();
                if ($catalog['updatedAt'] === 0) {
                    throw new AppleException('photosAccess');
                }
                return json_encode(['success' => true, 'albums' => $catalog['albums'], 'updatedAt' => $catalog['updatedAt'], 'cache' => $this->PhotoCache()->stats()], JSON_THROW_ON_ERROR);
            } catch (\Throwable $exception) {
                $fallback = $exception instanceof AppleException && $exception->reason === 'photosAccess' ? 'Check Photos access and load albums' : 'Enable Photos and sign in to the account first.';
                return json_encode(['success' => false, 'albums' => [], 'updatedAt' => 0, 'error' => $this->ReadAttributeString('PhotoError') ?: $this->Translate($fallback)], JSON_THROW_ON_ERROR);
            }
        });
    }

    /** Explicit read-only access check and catalog refresh. */
    public function RefreshPhotoAlbums(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            $success = $this->operatePhotos('catalog')['success'];
            $this->ReloadForm();
            return $success;
        });
    }
    protected function RegisterPhotosAccount(): void
    {
        $this->RegisterPropertyBoolean('EnablePhotos', false);
        $this->RegisterPropertyString('PhotoCacheProfile', 'compact');
        $this->RegisterPropertyInteger('PhotoCacheImages', 50);
        $this->RegisterPropertyInteger('PhotoCacheMiB', 100);
        $this->RegisterAttributeString('PhotoAlbums', '{"albums":[],"updatedAt":0}');
        $this->RegisterAttributeString('PhotoAssets', '{}'); // Clears signed URLs persisted by older versions.
        $this->RegisterAttributeString('PhotoStorageID', '');
        $this->RegisterAttributeString('PhotoStorageKey', '');
        $this->RegisterAttributeString('PhotoError', '');
        $this->RegisterAttributeBoolean('PhotoAccess', false);
        $this->RegisterAttributeInteger('PhotoRetryAt', 0);
    }

    protected function ClearPhotoState(): void
    {
        $this->WriteAttributeString('PhotoAlbums', '{"albums":[],"updatedAt":0}');
        $this->WriteAttributeString('PhotoAssets', '{}');
        $this->WriteAttributeString('PhotoStorageID', '');
        $this->WriteAttributeString('PhotoStorageKey', '');
        $this->WriteAttributeString('PhotoError', '');
        $this->WriteAttributeBoolean('PhotoAccess', false);
        $this->WriteAttributeInteger('PhotoRetryAt', 0);
        try {
            (new PhotoDiskCache($this->PhotoCacheDirectory(), 50, 104857600, '', ''))->clear();
            (new PhotoDiskCache($this->LegacyPhotoCacheDirectory(), 50, 104857600))->clear();
        }
        catch (\Throwable) {
            $this->WriteAttributeString('PhotoError', $this->Translate('Photo cache unavailable or configured limits are invalid.'));
        }
    }

    /** Encrypted previews under the Symcon user directory. The key stays in the account. */
    protected function PhotoCacheDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '/\\') . '/user/iPhotos/' . $this->InstanceID;
    }

    protected function LegacyPhotoCacheDirectory(): string
    {
        return rtrim(IPS_GetKernelDir(), '/\\') . '/SymconiCloud/photos/' . $this->InstanceID;
    }

    protected function DownloadPhotoPreview(string $url): string
    {
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Eine freigegebene Apple-Fotovorschau wird geladen. Downloadadresse und Bildinhalt bleiben geschützt.');
        $image = ApplePhotoDownload::download($url);
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Fotodownload abgeschlossen; Cachegrenzen und Bildformat werden weiterhin geprüft.', ['BilddateiBytes' => strlen($image)]);
        return $image;
    }

    protected function PhotoCache(): PhotoDiskCache
    {
        [$count, $bytes] = PhotoDiskCache::limits($this->ReadPropertyString('PhotoCacheProfile'), $this->ReadPropertyInteger('PhotoCacheImages'), $this->ReadPropertyInteger('PhotoCacheMiB'));
        $key = $this->ReadAttributeString('PhotoStorageKey');
        $key = preg_match('/\A[a-f0-9]{64}\z/D', $key) === 1 ? hex2bin($key) : '';
        return new PhotoDiskCache($this->PhotoCacheDirectory(), $count, $bytes, $this->ReadAttributeString('PhotoStorageID'), $key);
    }

    protected function ApplyPhotoStorageConfiguration(): void
    {
        if ($this->ReadAttributeString('PhotoAssets') !== '{}') {
            $this->WriteAttributeString('PhotoAssets', '{}');
        }
        $this->importLegacyPhotoStorage();
        $this->PhotoCache()->trim();
    }

    protected function ForwardPhotosRequest(array $request): array
    {
        $command = $request['Command'];
        $allowed = match ($command) {
            'GetPhotoAlbums', 'RefreshPhotoAlbums' => [],
            'SyncPhotoAlbum'                       => ['AlbumID'],
            'GetPhotoPage'                         => ['AlbumID', 'Offset'],
            'GetPhotoImage'                        => ['AlbumID', 'PhotoID'],
            'GetPhotoImagePage'                    => ['AlbumID', 'PhotoID', 'Version', 'Page'],
            default                                => throw new \InvalidArgumentException()
        };
        if (array_diff(array_keys($request), ['DataID', 'Command', ...$allowed]) !== []) {
            throw new \InvalidArgumentException();
        }
        if ($command === 'GetPhotoAlbums') {
            return json_decode($this->GetPhotoAlbums(), true, 512, JSON_THROW_ON_ERROR);
        }
        if ($command === 'RefreshPhotoAlbums') {
            return $this->operatePhotos('catalog');
        }
        $albumID = $request['AlbumID'] ?? null;
        if (!is_string($albumID) || $albumID === '' || strlen($albumID) > 512) {
            throw new \InvalidArgumentException();
        }
        if ($command === 'SyncPhotoAlbum') {
            return $this->operatePhotos('sync', $albumID);
        }
        $offset = $request['Offset'] ?? 0;
        $id = $request['PhotoID'] ?? '';
        $version = $request['Version'] ?? '';
        $page = $request['Page'] ?? 0;
        if (!is_int($offset) || $offset < 0 || $offset > 1000000000 || !is_string($id) || strlen($id) > 512
            || ($command === 'GetPhotoImagePage' && (!is_string($version) || preg_match('/\A[a-f0-9]{64}\z/D', $version) !== 1
                || !is_int($page) || $page < 0 || $page >= 64))) {
            throw new \InvalidArgumentException();
        }
        return $this->readLocalPhotos($command, $albumID, $offset, $id, $version, $page);
    }

    private function importLegacyPhotoStorage(): void
    {
        [$count, $bytes] = PhotoDiskCache::limits($this->ReadPropertyString('PhotoCacheProfile'), $this->ReadPropertyInteger('PhotoCacheImages'), $this->ReadPropertyInteger('PhotoCacheMiB'));
        $legacy = new PhotoDiskCache($this->LegacyPhotoCacheDirectory(), $count, $bytes, $this->ReadAttributeString('PhotoStorageID'));
        if ($legacy->hasStoredPhotos()) {
            $this->initializePhotoStorage();
            $this->PhotoCache()->importStoredPhotos($legacy);
        }
    }

    private function initializePhotoStorage(): void
    {
        if ($this->ReadAttributeString('PhotoStorageKey') === '') {
            $this->PhotoCache()->clear();
            $this->WriteAttributeString('PhotoStorageKey', bin2hex(random_bytes(32)));
        }
        if ($this->ReadAttributeString('PhotoStorageID') === '') {
            $this->WriteAttributeString('PhotoStorageID', bin2hex(random_bytes(16)));
        }
    }

    private function photosConfigured(): bool
    {
        return $this->configurationValid() && $this->ReadPropertyBoolean('EnablePhotos')
            && $this->credentialFingerprint() === $this->ReadAttributeString('CredentialFingerprint');
    }

    private function photoCatalog(): array
    {
        return json_decode($this->ReadAttributeString('PhotoAlbums'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function readLocalPhotos(string $command, string $albumID, int $offset, string $id, string $version, int $page): array
    {
        if (!$this->photosConfigured()) {
            return ['success' => false, 'revoked' => true];
        }
        try {
            $cache = $this->PhotoCache();
            if ($command === 'GetPhotoPage') {
                $local = $cache->page($albumID, $offset);
                if ($local['updatedAt'] === 0 && !in_array($albumID, array_column($this->photoCatalog()['albums'], 'id'), true)) {
                    return ['success' => false, 'revoked' => true, 'error' => $this->Translate('Refresh albums and select an available photo album.')];
                }
                return ['success' => true] + $local;
            }
            return ['success' => true] + match ($command) {
                'GetPhotoImage'     => $cache->image($albumID, $id),
                'GetPhotoImagePage' => $cache->image($albumID, $id, $version, $page)
            };
        } catch (\Throwable) {
            return ['success' => false, 'error' => $this->Translate('Local photo preview unavailable. Synchronize the album again.')];
        }
    }

    private function loadPhotoCatalog(AppleClient $client): array
    {
        $catalog = ['albums' => $client->photoAlbums(), 'updatedAt' => time()];
        $this->WriteAttributeString('PhotoAlbums', json_encode($catalog, JSON_THROW_ON_ERROR));
        $this->WriteAttributeBoolean('PhotoAccess', true);
        return $catalog;
    }

    /** Iterate the cloud inventory exclusively during the explicitly requested synchronization. */
    private function cloudAlbumPhotos(AppleClient $client, array $album): \Generator
    {
        $offset = 0;
        $total = null;
        do {
            $page = $client->photoPage($album, $offset);
            $total ??= $page['total'];
            if ($page['total'] !== $total) {
                throw new AppleException('response');
            }
            yield from $page['items'];
            $next = $page['next'];
            if ($next !== null && $next <= $offset) {
                throw new AppleException('response');
            }
            $offset = $next;
        } while ($offset !== null);
    }

    private function operatePhotos(string $operation, string $albumID = ''): array
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
            return ['success' => false, 'error' => $this->Translate('Account request already running.')];
        }
        $client = null;
        $diagnosticOperation = $operation === 'catalog' ? 'photoCatalog' : 'photoPage';
        $previousState = $this->readSession()['state'] ?? 'signedOut';
        try {
            if (!$this->photosConfigured() || $previousState !== 'authenticated') {
                throw new AppleException('photosDisabled');
            }
            $this->AssertAccountRequestsAllowed();
            if ($this->ReadAttributeInteger('PhotoRetryAt') > $this->AccountProtectionTime()) {
                throw new AppleException('rateLimited');
            }
            $client = $this->CreateAppleClient($this->readSession());
            if ($operation === 'catalog') {
                $result = $this->loadPhotoCatalog($client);
            } else {
                $catalog = $this->photoCatalog();
                if (!$this->ReadAttributeBoolean('PhotoAccess') || time() - $catalog['updatedAt'] >= 3600) {
                    $catalog = $this->loadPhotoCatalog($client);
                }
                $album = array_column($catalog['albums'], null, 'id')[$albumID] ?? null;
                if ($album === null) {
                    throw new AppleException('photosAlbum');
                }
                $this->importLegacyPhotoStorage();
                $this->initializePhotoStorage();
                $result = $this->PhotoCache()->synchronize($albumID, $this->cloudAlbumPhotos($client, $album), function (string $url): string
                {
                    $this->AssertAccountRequestsAllowed();
                    $preview = $this->DownloadPhotoPreview($url);
                    $this->RecordAccountDiagnostic('photoImage', 200, 'ok');
                    return $preview;
                });
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Album lokal abgeglichen. Die Wiedergabe verwendet die gespeicherten Vorschauen.', [
                    'Vorschauen' => $result['count'], 'Downloads' => $result['downloaded'], 'SpeichergrenzeErreicht' => $result['limited']
                ]);
            }
            $this->WriteAttributeString('PhotoError', '');
            $this->WriteAttributeInteger('TransientFailures', 0);
            $this->WriteAttributeInteger('PhotoRetryAt', 0);
            return ['success' => true] + $result;
        } catch (\Throwable $exception) {
            $reason = $exception instanceof AppleException ? $exception->reason : 'photosCache';
            if ($exception instanceof AppleException) {
                $this->AccountRequestFailed($diagnosticOperation, $exception, $client !== null);
            } else {
                $this->RecordAccountDiagnostic($diagnosticOperation, 0, 'photosCache');
            }
            if (in_array($reason, ['network', 'server', 'photoDownload', 'photosCache'], true)) {
                $this->WriteAttributeInteger('PhotoRetryAt', $this->AccountProtectionTime() + 60);
            }
            if (in_array($reason, ['photosAccess', 'photosUnavailable', 'photosIndexing', 'photosDisabled', 'authentication', 'credentials'], true)) {
                $this->WriteAttributeBoolean('PhotoAccess', false);
            }
            $message = in_array($reason, ['requestsPaused', 'accountBlocked', 'accountLocked', 'accountReviewRequired', 'termsRequired', 'rateLimited', 'retryBackoff', 'credentials', 'authentication', 'accessDenied', 'protectionState'], true) ? $this->errorMessage($reason) : $this->Translate(match ($reason) {
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
}
