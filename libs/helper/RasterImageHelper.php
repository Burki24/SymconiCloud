<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use Throwable;

/**
 * Bounded, local raster preparation for console uploads and inline avatars.
 * No Symcon state, file paths, network requests or external executables.
 * Explicitly centralized by user decision before a second library adopts it.
 *
 * @version 1.0.0
 */
trait RasterImageHelper
{
    /**
     * Accepts Base64 PNG/JPEG/GIF/WebP, up to 10 MiB, 8192 px per side and
     * 16 million pixels. Returns at most 128 KiB and 512 px per side, without
     * enlarging or cropping. Larger images require GD; WebP preserves alpha,
     * with JPEG/PNG fallbacks. Animated images needing conversion use frame 1.
     * JPEG EXIF orientation is applied when EXIF support is available.
     * Callers persist/cache successful results; this pure method does not.
     *
     * @return array{content:string,mime:string,width:int,height:int,changed:bool,error:string}
     * Error codes: input_size, format, pixels, unavailable, conversion; empty
     * means success, including an intentionally empty optional image.
     */
    protected function PrepareRasterImage(string $content): array
    {
        $empty = ['content' => '', 'mime' => '', 'width' => 0, 'height' => 0, 'changed' => false, 'error' => ''];
        if ($content === '') {
            return $empty;
        }
        if (strlen($content) > 13981016) {
            return array_replace($empty, ['error' => 'input_size']);
        }
        $bytes = base64_decode($content, true);
        if (!is_string($bytes)) {
            return array_replace($empty, ['error' => 'format']);
        }
        if (strlen($bytes) > 10485760) {
            return array_replace($empty, ['error' => 'input_size']);
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1
            || !in_array($size['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return array_replace($empty, ['error' => 'format']);
        }
        if ($size[0] > 8192 || $size[1] > 8192 || $size[0] * $size[1] > 16000000) {
            return array_replace($empty, ['error' => 'pixels']);
        }
        $orientation = $size['mime'] === 'image/jpeg' ? $this->RasterImageOrientation($bytes) : 1;
        if ($size[0] <= 512 && $size[1] <= 512 && strlen($bytes) <= 131072 && $orientation === 1) {
            return array_replace($empty, ['content' => base64_encode($bytes), 'mime' => $size['mime'], 'width' => $size[0], 'height' => $size[1]]);
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecopyresampled')) {
            return array_replace($empty, ['error' => 'unavailable']);
        }
        try {
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                return array_replace($empty, ['error' => 'format']);
            }
            // Normalize phone-camera orientation before computing aspect ratio.
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) {
                3 => 180, 5, 8 => 90, 6, 7 => 270, default => 0
            };
            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0);
                if ($image === false) {
                    return array_replace($empty, ['error' => 'conversion']);
                }
            }
            $width = imagesx($image);
            $height = imagesy($image);
            for ($edge = 512; $edge >= 64; $edge = (int) floor($edge * 0.75)) {
                $scale = min(1, $edge / max($width, $height));
                $w = max(1, (int) round($width * $scale));
                $h = max(1, (int) round($height * $scale));
                $target = imagecreatetruecolor($w, $h);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
                if (!imagecopyresampled($target, $image, 0, 0, 0, 0, $w, $h, $width, $height)) {
                    return array_replace($empty, ['error' => 'conversion']);
                }
                $mime = function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0 ? 'image/webp' : ($size['mime'] === 'image/jpeg' ? 'image/jpeg' : 'image/png');
                foreach ($mime === 'image/png' ? [9] : [85, 70, 55, 40] as $quality) {
                    ob_start();
                    try {
                        $ok = match ($mime) {
                            'image/webp' => imagewebp($target, null, $quality),
                            'image/jpeg' => imagejpeg($target, null, $quality),
                            default      => imagepng($target, null, $quality)
                        };
                        $output = ob_get_contents();
                    } finally {
                        ob_end_clean();
                    }
                    if ($ok && is_string($output) && $output !== '' && strlen($output) <= 131072) {
                        return array_replace($empty, ['content' => base64_encode($output), 'mime' => $mime, 'width' => $w, 'height' => $h, 'changed' => true]);
                    }
                }
            }
        } catch (Throwable) {
            // Invalid image content must not break a module or leak file contents.
        }
        return array_replace($empty, ['error' => 'conversion']);
    }

    private function RasterImageOrientation(string $bytes): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            return 1;
        }
        try {
            fwrite($stream, $bytes);
            rewind($stream);
            $exif = @exif_read_data($stream, 'IFD0', true);
            $value = (int) ($exif['IFD0']['Orientation'] ?? 1);
            return $value >= 1 && $value <= 8 ? $value : 1;
        } finally {
            fclose($stream);
        }
    }
}
