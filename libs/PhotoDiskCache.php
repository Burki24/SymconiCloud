<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/ApplePhotoDownload.php';

/** Account-private album snapshots. The caller serializes writes with the account semaphore. */
final class PhotoDiskCache
{
    private const IMAGE_PAGE_BYTES = 131072;
    private const MAX_IMAGE_BYTES = 8388608;

    public function __construct(private readonly string $directory, private readonly int $maxImages, private readonly int $maxBytes, private readonly string $owner = '')
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
                    $oldContent = isset($legacy[$oldKey]) && $this->imageSize($oldKey) > 0 ? @file_get_contents($this->imagePath($oldKey)) : false;
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
                    $this->atomic($this->imagePath($version), $content);
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
        $content = @file_get_contents($this->imagePath($version), false, null, $offset, $length);
        if ($content === false || strlen($content) !== $length) {
            throw new AppleException('photosCache');
        }
        return ['page' => $page, 'version' => $version, 'data' => base64_encode($content)];
    }

    public function trim(): void
    {
        $albums = $this->albums();
        if (!is_file($this->directory . '/albums.json')) {
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
        foreach (['albums.json', 'index.json'] as $name) {
            $file = $this->directory . '/' . $name;
            if ((is_file($file) || is_link($file)) && !unlink($file)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
        }
        $this->prune([]);
    }

    public function stats(): array
    {
        $images = $this->referencedImages($this->albums());
        return ['images' => count($images), 'bytes' => array_sum(array_column($images, 'bytes'))];
    }

    private function albums(): array
    {
        $path = $this->directory . '/albums.json';
        if (is_link($this->directory) || is_link($path)) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        if (!is_file($path)) {
            return [];
        }
        $manifest = json_decode((string) @file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        return ($manifest['owner'] ?? null) === $this->owner ? $manifest['albums'] : [];
    }

    private function legacyImages(): array
    {
        $path = $this->directory . '/index.json';
        if (!is_file($path) || is_link($path)) {
            return [];
        }
        $index = json_decode((string) @file_get_contents($path), true);
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
        $bytes = (int) filesize($path);
        return $bytes <= self::MAX_IMAGE_BYTES ? $bytes : 0;
    }

    private function imagePath(string $version): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $version) !== 1) {
            throw new \RuntimeException('Invalid photo cache key.');
        }
        return $this->directory . '/' . $version . '.bin';
    }

    private function save(array $albums): void
    {
        foreach ($albums as &$album) {
            $album['snapshot'] = hash('sha256', json_encode([$album['items'], $album['limited']], JSON_THROW_ON_ERROR));
        }
        unset($album);
        $this->atomic($this->directory . '/albums.json', json_encode(['owner' => $this->owner, 'albums' => $albums], JSON_THROW_ON_ERROR));
    }

    private function prune(array $keep, bool $keepLegacyIndex = false): void
    {
        if (!is_dir($this->directory) || is_link($this->directory)) {
            return;
        }
        foreach (scandir($this->directory) ?: [] as $name) {
            if (($name === 'index.json' && !$keepLegacyIndex) || preg_match('/\Aphoto-[A-Za-z0-9.]+\z/D', $name) === 1
                || (preg_match('/\A([a-f0-9]{64})\.bin\z/D', $name, $match) === 1 && !isset($keep[$match[1]]))) {
                $path = $this->directory . '/' . $name;
                if ((is_file($path) || is_link($path)) && !unlink($path)) {
                    throw new \RuntimeException('Photo cache unavailable.');
                }
            }
        }
    }

    private function atomic(string $path, string $bytes): void
    {
        if (is_link($this->directory) || (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory))) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        $temporary = tempnam($this->directory, 'photo-');
        if ($temporary === false) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        try {
            if (dirname($temporary) !== realpath($this->directory) || file_put_contents($temporary, $bytes) !== strlen($bytes)
                || !chmod($temporary, 0600) || !rename($temporary, $path)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
