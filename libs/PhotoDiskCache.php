<?php

declare(strict_types=1);

namespace ICloudServices;

require_once __DIR__ . '/ApplePhotoDownload.php';

/** Account-private album snapshots. The caller serializes writes with the account semaphore. */
final class PhotoDiskCache
{
    private const IMAGE_PAGE_BYTES = 131072;
    private const MAX_IMAGE_BYTES = 8388608;
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;
    private const ENCRYPTION_BYTES = self::NONCE_BYTES + self::TAG_BYTES;

    /** A null key is reserved for the old unencrypted cache format. */
    public function __construct(private readonly string $directory, private readonly int $maxImages, private readonly int $maxBytes, private readonly string $owner = '', private readonly ?string $key = null)
    {
        if ($maxImages < 1 || $maxBytes < 1) {
            throw new \InvalidArgumentException('Invalid photo cache limits.');
        }
    }

    public static function limits(string $profile, int $images, int $mib): array
    {
        if ($profile === 'custom' && ($images < 1 || $images > 100000 || $mib < 1 || $mib > 65536)) {
            throw new \InvalidArgumentException('Invalid photo cache limits.');
        }
        [$count, $size] = match ($profile) {
            'compact' => [50, 100], 'standard' => [250, 500], 'large' => [1000, 2048], 'custom' => [$images, $mib],
            default   => throw new \InvalidArgumentException('Unknown photo resource profile.')
        };
        return [$count, $size * 1048576];
    }

    /** Publishes the new album only after all selected previews are locally available. */
    public function synchronize(string $albumID, iterable $photos, \Closure $download): array
    {
        $albums = $this->albums();
        $albumKey = hash('sha256', $albumID);
        $previous = $this->referencedImages($albums);
        $legacy = $this->legacyImages();
        $this->prune($previous + $legacy, $legacy !== []);
        $staged = [];
        $items = [];
        $downloaded = 0;
        $bytes = 0;
        $limited = false;
        try {
            foreach ($photos as $photo) {
                $id = $photo['id'];
                if (isset($items[$id])) {
                    continue;
                }
                if (count($items) === $this->maxImages) {
                    $limited = true;
                    break;
                }
                $version = hash('sha256', $id . "\0" . $photo['revision']);
                $info = $previous[$version] ?? null;
                if ($info === null || $this->imageSize($version) !== $info['bytes']) {
                    $oldKey = hash('sha256', $id . ':' . $photo['revision']);
                    $oldContent = isset($legacy[$oldKey]) ? $this->readPreview($oldKey) : false;
                    if ($oldContent === false && ($photo['bytes'] ?? 0) > $this->maxBytes - $bytes) {
                        $limited = true;
                        break;
                    }
                    $content = $oldContent === false ? $download($photo['url']) : $oldContent;
                    $info = ['bytes' => strlen($content), 'mime' => ApplePhotoDownload::mime($content)];
                    if ($bytes + $info['bytes'] > $this->maxBytes) {
                        $limited = true;
                        break;
                    }
                    $this->writePreview($version, $content);
                    if (!isset($previous[$version])) {
                        $staged[] = $version;
                    }
                    $downloaded += (int) ($oldContent === false);
                    unset($content, $oldContent);
                }
                if ($bytes + $info['bytes'] > $this->maxBytes) {
                    $limited = true;
                    break;
                }
                preg_match('/\A.{0,160}/us', $photo['name'], $name);
                $items[$id] = ['id' => $id, 'name' => $name[0] ?? '', 'date' => $photo['date'], 'version' => $version] + $info;
                $bytes += $info['bytes'];
            }
            $album = ['items' => $items, 'limited' => $limited, 'updatedAt' => time()];
            $albums = [$albumKey => $album] + $albums;
            $albums = $this->applyLimits($albums);
            $this->save($albums);
        } catch (\Throwable $exception) {
            foreach ($staged as $version) {
                @unlink($this->imagePath($version));
            }
            throw $exception;
        }
        $this->prune($this->referencedImages($albums));
        return ['downloaded' => $downloaded, 'count' => count($items), 'limited' => $limited];
    }

    public function page(string $albumID, int $offset): array
    {
        $album = $this->albums()[hash('sha256', $albumID)] ?? ['items' => [], 'limited' => false, 'updatedAt' => 0];
        $items = array_map(
            static fn (array $photo): array => array_diff_key($photo, ['bytes' => true, 'mime' => true]),
            array_slice(array_values($album['items']), $offset, 20)
        );
        $total = count($album['items']);
        return ['items' => $items, 'offset' => $offset, 'next' => $offset + count($items) < $total ? $offset + count($items) : null,
            'total'     => $total, 'limited' => $album['limited'], 'updatedAt' => $album['updatedAt'], 'snapshot' => $album['snapshot'] ?? ''];
    }

    /** Each page is read directly from the sealed local image for the selected album. */
    public function image(string $albumID, string $id, ?string $version = null, int $page = 0): array
    {
        $photo = $this->albums()[hash('sha256', $albumID)]['items'][$id] ?? null;
        if ($photo === null || $this->imageSize($photo['version']) !== $photo['bytes']) {
            throw new AppleException('photosAlbum');
        }
        if ($version === null) {
            return ['bytes' => $photo['bytes'], 'mime' => $photo['mime'], 'version' => $photo['version'],
                'pages'     => (int) ceil($photo['bytes'] / self::IMAGE_PAGE_BYTES)];
        }
        $offset = $page * self::IMAGE_PAGE_BYTES;
        if ($version !== $photo['version'] || $offset >= $photo['bytes']) {
            throw new AppleException('photosAlbum');
        }
        $length = min(self::IMAGE_PAGE_BYTES, $photo['bytes'] - $offset);
        $content = $this->readPreviewPage($version, $page, $length);
        if ($content === false || strlen($content) !== $length) {
            throw new AppleException('photosCache');
        }
        return ['page' => $page, 'version' => $version, 'data' => base64_encode($content)];
    }

    public function trim(): void
    {
        $albums = $this->albums();
        if (!is_file($this->storagePath('albums.json'))) {
            // Preserve previews from the former cache until the first explicit synchronization.
            return;
        }
        $trimmed = $this->applyLimits($albums);
        if ($trimmed !== $albums) {
            $this->save($trimmed);
        }
        $this->prune($this->referencedImages($trimmed));
    }

    public function clear(): void
    {
        if (!is_dir($this->directory) || is_link($this->directory)) {
            return;
        }
        // Keep the indexes available for a retry until image cleanup succeeds.
        $this->prune([], true);
        foreach (['albums.json', 'index.json'] as $name) {
            $file = $this->storagePath($name);
            if ((is_file($file) || is_link($file)) && !unlink($file)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
        }
    }

    public function stats(): array
    {
        $images = $this->referencedImages($this->albums());
        return ['images' => count($images), 'bytes' => array_sum(array_column($images, 'bytes'))];
    }

    public function hasStoredPhotos(): bool
    {
        return !is_link($this->directory) && (is_file($this->storagePath('albums.json')) || is_file($this->storagePath('index.json')));
    }

    /** Remove source files only after the encrypted destination has been published. */
    public function importStoredPhotos(self $source): void
    {
        if (!$source->hasStoredPhotos()) {
            return;
        }
        $sealedSource = $source->key !== null;
        if ($sealedSource && (strlen($source->key) !== 32 || realpath($source->directory) === realpath($this->directory))) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        $albums = $this->albums();
        if ($sealedSource || ($albums === [] && $this->legacyImages() === [])) {
            $sourceAlbums = $source->albums($sealedSource);
            if ($sourceAlbums !== []) {
                $this->importAlbumSnapshots($source, $sourceAlbums, $albums);
            } elseif ($albums === [] && $this->legacyImages() === []) {
                $this->importLegacyIndex($source);
            }
        }
        $source->clear();
    }

    private function importAlbumSnapshots(self $source, array $sourceAlbums, array $albums): void
    {
        $incoming = [];
        foreach ($sourceAlbums as $id => $album) {
            if (!isset($albums[$id]) || $album['updatedAt'] > $albums[$id]['updatedAt']) {
                $incoming[$id] = $album;
            }
        }
        if ($incoming === []) {
            return;
        }
        $incoming = $source->applyLimits($incoming);
        $previous = $this->referencedImages($albums);
        foreach ($source->referencedImages($incoming) as $version => $photo) {
            if (isset($previous[$version]) && $this->imageSize($version) === $photo['bytes']) {
                continue;
            }
            $content = $source->readPreview($version);
            if ($content === false || strlen($content) !== $photo['bytes']) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
            $this->writePreview($version, $content);
        }
        $merged = $incoming + $albums;
        uasort($merged, static fn (array $left, array $right): int => $right['updatedAt'] <=> $left['updatedAt']);
        $merged = $this->applyLimits($merged);
        $this->save($merged);
        $this->prune($this->referencedImages($merged));
    }

    private function importLegacyIndex(self $source): void
    {
        $index = [];
        $bytes = 0;
        foreach (array_reverse($source->legacyImages(), true) as $version => $unused) {
            if (count($index) >= $this->maxImages) {
                break;
            }
            if (!is_string($version) || preg_match('/\A[a-f0-9]{64}\z/D', $version) !== 1) {
                continue;
            }
            $size = $source->imageSize($version);
            if ($size === 0 || $bytes + $size > $this->maxBytes) {
                continue;
            }
            $content = $source->readPreview($version);
            if ($content === false) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
            $this->writePreview($version, $content);
            $index[$version] = $size;
            $bytes += $size;
        }
        if ($index !== []) {
            $this->atomic($this->storagePath('index.json'), $this->seal(json_encode($index, JSON_THROW_ON_ERROR), 'index'));
        }
    }

    private function albums(bool $requireOwner = false): array
    {
        $path = $this->storagePath('albums.json');
        if (is_link($this->directory) || is_link($path)) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        if ($this->key === '' || !is_file($path)) {
            return [];
        }
        $manifest = json_decode($this->open((string) @file_get_contents($path), 'albums'), true, 32, JSON_THROW_ON_ERROR);
        if ($requireOwner && (($manifest['owner'] ?? null) !== $this->owner || !is_array($manifest['albums'] ?? null))) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        return ($manifest['owner'] ?? null) === $this->owner ? $manifest['albums'] : [];
    }

    private function legacyImages(): array
    {
        $path = $this->storagePath('index.json');
        if ($this->key === '' || !is_file($path) || is_link($path)) {
            return [];
        }
        $index = json_decode($this->open((string) @file_get_contents($path), 'index'), true);
        return is_array($index) ? $index : [];
    }

    private function applyLimits(array $albums): array
    {
        $images = [];
        $bytes = 0;
        foreach ($albums as &$album) {
            foreach ($album['items'] as $id => $photo) {
                $version = $photo['version'];
                if (isset($images[$version])) {
                    continue;
                }
                if (count($images) >= $this->maxImages || $bytes + $photo['bytes'] > $this->maxBytes || $this->imageSize($version) !== $photo['bytes']) {
                    unset($album['items'][$id]);
                    $album['limited'] = true;
                    continue;
                }
                $images[$version] = true;
                $bytes += $photo['bytes'];
            }
        }
        unset($album);
        return $albums;
    }

    private function referencedImages(array $albums): array
    {
        $images = [];
        foreach ($albums as $album) {
            foreach ($album['items'] as $photo) {
                $images[$photo['version']] = $photo;
            }
        }
        return $images;
    }

    private function imageSize(string $version): int
    {
        $path = $this->imagePath($version);
        if (!is_file($path) || is_link($path)) {
            return 0;
        }
        $bytes = (int) @filesize($path);
        if ($this->key !== null) {
            $bytes -= self::ENCRYPTION_BYTES * (int) ceil($bytes / (self::IMAGE_PAGE_BYTES + self::ENCRYPTION_BYTES));
        }
        return $bytes > 0 && $bytes <= self::MAX_IMAGE_BYTES ? $bytes : 0;
    }

    private function imagePath(string $version): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $version) !== 1) {
            throw new \RuntimeException('Invalid photo cache key.');
        }
        return $this->storagePath($version . '.bin');
    }

    private function storagePath(string $name): string
    {
        return $this->directory . '/' . ($this->key === null ? '' : 'scphoto-') . $name;
    }

    private function temporaryPrefix(): string
    {
        return $this->key === null ? 'photo-' : 'scphoto-tmp-';
    }

    private function save(array $albums): void
    {
        foreach ($albums as &$album) {
            $album['snapshot'] = hash('sha256', json_encode([$album['items'], $album['limited']], JSON_THROW_ON_ERROR));
        }
        unset($album);
        $content = json_encode(['owner' => $this->owner, 'albums' => $albums], JSON_THROW_ON_ERROR);
        $this->atomic($this->storagePath('albums.json'), $this->seal($content, 'albums'));
    }

    private function prune(array $keep, bool $keepLegacyIndex = false): void
    {
        if (!is_dir($this->directory) || is_link($this->directory)) {
            return;
        }
        $imagePrefix = $this->key === null ? '' : 'scphoto-';
        $indexName = $imagePrefix . 'index.json';
        $temporaryPrefix = $this->temporaryPrefix();
        foreach (scandir($this->directory) ?: [] as $name) {
            if (($name === $indexName && !$keepLegacyIndex) || preg_match('/\A' . $temporaryPrefix . '[A-Za-z0-9.]+\z/D', $name) === 1
                || (preg_match('/\A' . $imagePrefix . '([a-f0-9]{64})\.bin\z/D', $name, $match) === 1 && !isset($keep[$match[1]]))) {
                $path = $this->directory . '/' . $name;
                if ((is_file($path) || is_link($path)) && !unlink($path)) {
                    throw new \RuntimeException('Photo cache unavailable.');
                }
            }
        }
    }

    private function readPreview(string $version): string|false
    {
        $bytes = $this->imageSize($version);
        if ($bytes === 0) {
            return false;
        }
        $content = '';
        for ($offset = 0, $page = 0; $offset < $bytes; $offset += self::IMAGE_PAGE_BYTES, ++$page) {
            $length = min(self::IMAGE_PAGE_BYTES, $bytes - $offset);
            $slice = $this->readPreviewPage($version, $page, $length);
            if ($slice === false || strlen($slice) !== $length) {
                return false;
            }
            $content .= $slice;
        }
        return $content;
    }

    private function readPreviewPage(string $version, int $page, int $length): string|false
    {
        $overhead = $this->key === null ? 0 : self::ENCRYPTION_BYTES;
        $offset = $page * (self::IMAGE_PAGE_BYTES + $overhead);
        $stored = @file_get_contents($this->imagePath($version), false, null, $offset, $length + $overhead);
        return $stored === false ? false : $this->open($stored, $version . ':' . $page);
    }

    private function writePreview(string $version, string $content): void
    {
        $this->atomic($this->imagePath($version), function (string $temporary) use ($version, $content): void
        {
            $file = fopen($temporary, 'wb');
            if ($file === false) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
            try {
                $size = strlen($content);
                for ($offset = 0, $page = 0; $offset < $size; $offset += self::IMAGE_PAGE_BYTES, ++$page) {
                    $bytes = $this->seal(substr($content, $offset, self::IMAGE_PAGE_BYTES), $version . ':' . $page);
                    if (fwrite($file, $bytes) !== strlen($bytes)) {
                        throw new \RuntimeException('Photo cache unavailable.');
                    }
                }
            } finally {
                fclose($file);
            }
        });
    }

    private function seal(string $content, string $context): string
    {
        if ($this->key === null) {
            return $content;
        }
        if (strlen($this->key) !== 32 || !function_exists('openssl_encrypt')) {
            throw new \RuntimeException('Photo cache encryption unavailable.');
        }
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $sealed = @openssl_encrypt($content, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $context, self::TAG_BYTES);
        if ($sealed === false || strlen($tag) !== self::TAG_BYTES) {
            throw new \RuntimeException('Photo cache encryption unavailable.');
        }
        return $nonce . $tag . $sealed;
    }

    private function open(string $stored, string $context): string
    {
        if ($this->key === null) {
            return $stored;
        }
        if (strlen($this->key) !== 32 || strlen($stored) < self::ENCRYPTION_BYTES || !function_exists('openssl_decrypt')) {
            throw new \RuntimeException('Photo cache decryption unavailable.');
        }
        $nonce = substr($stored, 0, self::NONCE_BYTES);
        $tag = substr($stored, self::NONCE_BYTES, self::TAG_BYTES);
        $content = @openssl_decrypt(substr($stored, self::ENCRYPTION_BYTES), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag, $context);
        if ($content === false) {
            throw new \RuntimeException('Photo cache decryption unavailable.');
        }
        return $content;
    }

    private function atomic(string $path, string|\Closure $bytes): void
    {
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory))) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        $temporary = $this->directory . '/' . $this->temporaryPrefix() . bin2hex(random_bytes(12));
        $created = @fopen($temporary, 'xb');
        if ($created === false) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        fclose($created);
        try {
            if (!chmod($temporary, 0600)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
            if ($bytes instanceof \Closure) {
                $bytes($temporary);
            } elseif (file_put_contents($temporary, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
            if (!rename($temporary, $path)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
