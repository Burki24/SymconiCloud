<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/AppleException.php';

/** Read-only CloudKit queries and normalization; no cookies or arbitrary record mutations. */
final class PhotosLibrary
{
    public function __construct(private readonly \Closure $query)
    {
    }

    public function albums(): array
    {
        $response = ($this->query)('records/query', ['query' => ['recordType' => 'CheckIndexingState'], 'zoneID' => ['zoneName' => 'PrimarySync']]);
        if (($response['records'][0]['fields']['state']['value'] ?? '') !== 'FINISHED') {
            throw new AppleException('photosIndexing');
        }
        $albums = [['id' => 'smart:all', 'name' => 'All photos', 'kind' => 'all'], ['id' => 'smart:favorites', 'name' => 'Favorites', 'kind' => 'favorites']];
        $marker = null;
        for ($page = 0; $page < 25; ++$page) {
            $body = ['query' => ['recordType' => 'CPLAlbumByPositionLive'], 'zoneID' => ['zoneName' => 'PrimarySync'], 'resultsLimit' => 100];
            if ($marker !== null) {
                $body['continuationMarker'] = $marker;
            }
            $response = ($this->query)('records/query', $body);
            self::records($response);
            foreach ($response['records'] ?? [] as $record) {
                $name = base64_decode((string) ($record['fields']['albumNameEnc']['value'] ?? ''), true);
                if (!is_string($record['recordName'] ?? null) || $record['recordName'] === '----Root-Folder----'
                    || ($record['fields']['isDeleted']['value'] ?? false) || $name === false || $name === '' || preg_match('//u', $name) !== 1) {
                    continue;
                }
                if (strlen($record['recordName']) <= 512 && !in_array($record['recordName'], array_column($albums, 'id'), true)) {
                    $albums[] = ['id' => $record['recordName'], 'name' => $name, 'kind' => 'album'];
                }
            }
            $marker = $response['continuationMarker'] ?? null;
            if (!is_string($marker) || $marker === '') {
                return $albums;
            }
        }
        throw new AppleException('response');
    }

    public function page(array $album, int $offset): array
    {
        if ($offset < 0 || !in_array($album['kind'] ?? '', ['all', 'favorites', 'album'], true)) {
            throw new AppleException('response');
        }
        $kind = $album['kind'];
        $countType = match ($kind) {
            'all' => 'CPLAssetByAddedDate', 'favorites' => 'CPLAssetInSmartAlbumByAssetDate:Favorite', default => 'CPLContainerRelationNotDeletedByAssetDate:' . $album['id']
        };
        $count = ($this->query)('internal/records/query/batch', ['batch' => [[
            'resultsLimit' => 1, 'zoneID' => ['zoneName' => 'PrimarySync'], 'zoneWide' => true,
            'query'        => ['recordType' => 'HyperionIndexCountLookup', 'filterBy' => ['fieldName' => 'indexCountID', 'comparator' => 'IN', 'fieldValue' => ['type' => 'STRING_LIST', 'value' => [$countType]]]]
        ]]]);
        $total = $count['batch'][0]['records'][0]['fields']['itemCount']['value'] ?? null;
        if (!is_int($total) || $total < 0) {
            throw new AppleException('response');
        }
        if ($offset >= $total) {
            return ['items' => [], 'offset' => $offset, 'next' => null, 'total' => $total];
        }
        $filters = [['fieldName' => 'startRank', 'comparator' => 'EQUALS', 'fieldValue' => ['type' => 'INT64', 'value' => $total - 1 - $offset]],
            ['fieldName' => 'direction', 'comparator' => 'EQUALS', 'fieldValue' => ['type' => 'STRING', 'value' => 'DESCENDING']]];
        if ($kind !== 'all') {
            $filters[] = ['fieldName' => $kind === 'favorites' ? 'smartAlbum' : 'parentId', 'comparator' => 'EQUALS', 'fieldValue' => ['type' => 'STRING', 'value' => $kind === 'favorites' ? 'FAVORITE' : $album['id']]];
        }
        $response = ($this->query)('records/query', ['query' => ['recordType' => $kind === 'album' ? 'CPLContainerRelationLiveByAssetDate' : ($kind === 'favorites' ? 'CPLAssetAndMasterInSmartAlbumByAssetDate' : 'CPLAssetAndMasterByAddedDate'), 'filterBy' => $filters],
            'resultsLimit'                                   => 40, 'zoneID' => ['zoneName' => 'PrimarySync'], 'desiredKeys' => ['recordName', 'masterRef', 'assetDate', 'isHidden', 'isDeleted', 'isExpunged', 'filenameEnc', 'resJPEGMedRes', 'resJPEGLargeRes', 'resJPEGFullRes', 'resJPEGMedFingerprint', 'resJPEGLargeFingerprint', 'resJPEGFullFingerprint', 'resVidSmallRes', 'resVidMedRes']]);
        $assets = [];
        $masters = [];
        foreach (self::records($response) as $record) {
            if (($record['recordType'] ?? '') === 'CPLAsset') {
                $assets[$record['fields']['masterRef']['value']['recordName'] ?? ''] = $record['fields'];
            } elseif (($record['recordType'] ?? '') === 'CPLMaster') {
                $masters[] = $record;
            }
        }
        $items = [];
        foreach ($masters as $master) {
            $id = $master['recordName'] ?? '';
            $fields = $master['fields'];
            $asset = $assets[$id] ?? null;
            if (!is_string($id) || $id === '' || strlen($id) > 512 || $asset === null || !empty($asset['isHidden']['value']) || !empty($asset['isDeleted']['value'])
                || !empty($asset['isExpunged']['value']) || isset($fields['resVidSmallRes']) || isset($fields['resVidMedRes'])) {
                continue;
            }
            foreach (['resJPEGLarge', 'resJPEGMed', 'resJPEGFull'] as $version) {
                $resource = $fields[$version . 'Res']['value'] ?? [];
                if (is_string($resource['downloadURL'] ?? null) && (int) ($resource['size'] ?? 0) > 0 && (int) $resource['size'] <= 8388608) {
                    $name = base64_decode((string) ($fields['filenameEnc']['value'] ?? ''), true);
                    $items[] = ['id' => $id, 'name' => is_string($name) && preg_match('//u', $name) === 1 ? $name : '',
                        'date'       => max(0, (int) (($asset['assetDate']['value'] ?? 0) / 1000)),
                        'url'        => $resource['downloadURL'], 'revision' => (string) ($fields[$version . 'Fingerprint']['value'] ?? hash('sha256', (string) parse_url($resource['downloadURL'], PHP_URL_PATH)))];
                    break;
                }
            }
        }
        $next = $offset + count($masters);
        return ['items' => $items, 'offset' => $offset, 'next' => count($masters) > 0 && $next < $total ? $next : null, 'total' => $total];
    }

    private static function records(array $response): array
    {
        if (!is_array($response['records'] ?? null) || count($response['records']) > 200) {
            throw new AppleException('response');
        }
        foreach ($response['records'] as $record) {
            if (!is_array($record) || isset($record['serverErrorCode'])) {
                throw new AppleException('photosAccess');
            }
        }
        return $response['records'];
    }
}
