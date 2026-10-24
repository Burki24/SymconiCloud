<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Account-private photo files. Caller holds the account semaphore for every operation. */
final class PhotoDiskCache
{
    public function __construct(private readonly string $directory, private readonly int $maxImages, private readonly int $maxBytes)
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

    public function get(string $key): ?string
    {
        $index = $this->index();
        $hash = hash('sha256', $key);
        if (!isset($index[$hash]) || $index[$hash] > min($this->maxBytes, 8388608) || !is_file($this->path($hash)) || is_link($this->path($hash))) {
            return null;
        }
        $bytes = file_get_contents($this->path($hash));
        unset($index[$hash]);
        $index[$hash] = strlen((string) $bytes);
        $this->save($index);
        return $bytes === false ? null : $bytes;
    }

    public function put(string $key, string $bytes): bool
    {
        $size = strlen($bytes);
        if ($size === 0 || $size > $this->maxBytes) {
            return false;
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        $index = $this->index(true);
        $hash = hash('sha256', $key);
        if (isset($index[$hash])) {
            $this->remove($hash);
            unset($index[$hash]);
        }
        while ($index !== [] && (count($index) >= $this->maxImages || array_sum($index) + $size > $this->maxBytes)) {
            $oldest = array_key_first($index);
            $this->remove($oldest);
            unset($index[$oldest]);
        }
        $this->atomic($this->path($hash), $bytes);
        $index[$hash] = $size;
        $this->save($index);
        return true;
    }

    public function trim(): void
    {
        $index = $this->index(true);
        while ($index !== [] && (count($index) > $this->maxImages || array_sum($index) > $this->maxBytes)) {
            $oldest = array_key_first($index);
            $this->remove($oldest);
            unset($index[$oldest]);
        }
        if (is_dir($this->directory)) {
            $this->save($index);
        }
    }

    public function clear(): void
    {
        if (!is_dir($this->directory) || is_link($this->directory)) {
            return;
        }
        foreach (scandir($this->directory) ?: [] as $name) {
            $path = $this->directory . '/' . $name;
            if ((preg_match('/\A(?:[a-f0-9]{64}\.bin|photo-[A-Za-z0-9.]+)\z/D', $name) === 1 || $name === 'index.json') && (is_file($path) || is_link($path))) {
                if (!unlink($path)) {
                    throw new \RuntimeException('Photo cache unavailable.');
                }
            }
        }
    }

    public function stats(): array
    {
        $index = $this->index();
        return ['images' => count($index), 'bytes' => array_sum($index)];
    }

    private function index(bool $cleanup = false): array
    {
        if (is_link($this->directory)) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        $file = $this->directory . '/index.json';
        $saved = is_file($file) && !is_link($file) ? json_decode((string) file_get_contents($file), true) : [];
        $index = [];
        foreach (is_array($saved) ? $saved : [] as $hash => $size) {
            if (is_string($hash) && preg_match('/\A[a-f0-9]{64}\z/D', $hash) === 1 && is_file($this->path($hash)) && !is_link($this->path($hash))) {
                $index[$hash] = filesize($this->path($hash));
            }
        }
        // Recover a crash between writing an image and publishing its index.
        // Only this cache's fixed filenames are owned; unrelated files stay intact.
        foreach ($cleanup && is_dir($this->directory) ? (scandir($this->directory) ?: []) : [] as $name) {
            if (preg_match('/\A([a-f0-9]{64})\.bin\z/D', $name, $match) === 1 && !isset($index[$match[1]])) {
                $this->remove($match[1]);
            } elseif (preg_match('/\Aphoto-[A-Za-z0-9.]+\z/D', $name) === 1 && is_file($this->directory . '/' . $name)) {
                unlink($this->directory . '/' . $name);
            }
        }
        return $index;
    }

    private function path(string $hash): string
    {
        return $this->directory . '/' . $hash . '.bin';
    }

    private function remove(string $hash): void
    {
        if (file_exists($this->path($hash)) || is_link($this->path($hash))) {
            if (!unlink($this->path($hash))) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
        }
    }

    private function save(array $index): void
    {
        $this->atomic($this->directory . '/index.json', json_encode($index, JSON_THROW_ON_ERROR));
    }

    private function atomic(string $path, string $bytes): void
    {
        $temporary = tempnam($this->directory, 'photo-');
        if ($temporary === false) {
            throw new \RuntimeException('Photo cache unavailable.');
        }
        try {
            if (file_put_contents($temporary, $bytes) !== strlen($bytes) || !chmod($temporary, 0600) || !rename($temporary, $path)) {
                throw new \RuntimeException('Photo cache unavailable.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
