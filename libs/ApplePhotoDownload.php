<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/AppleException.php';

/** Signed Apple preview URLs only; never attaches account cookies or follows unchecked redirects. */
final class ApplePhotoDownload
{
    public static function assertURL(string $url): void
    {
        $parts = parse_url($url);
        if (strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f]/', $url) || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || preg_match('/\A[a-z0-9-]+(?:\.[a-z0-9-]+)*\.(?:icloud-content\.com|icloud\.com)\z/D', strtolower($parts['host'] ?? '')) !== 1) {
            throw new AppleException('endpoint');
        }
    }

    public static function mime(string $bytes): string
    {
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] > 8192 || $size[1] > 8192 || $size[0] * $size[1] > 20000000
            || !in_array($size['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true) || strlen($bytes) > 8388608) {
            throw new AppleException('photoFormat');
        }
        return $size['mime'];
    }

    public static function download(string $url): string
    {
        $deadline = microtime(true) + 30;
        for ($redirect = 0; $redirect < 3; ++$redirect) {
            $remaining = (int) ceil($deadline - microtime(true));
            if ($remaining < 1) {
                throw new AppleException('network');
            }
            self::assertURL($url);
            $handle = curl_init($url);
            $bytes = '';
            $location = '';
            curl_setopt_array($handle, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER                         => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => min(10, $remaining), CURLOPT_TIMEOUT => $remaining,
                CURLOPT_HEADERFUNCTION                         => static function ($handle, string $line) use (&$location): int
                {
                    if (stripos($line, 'Location:') === 0) {
                        $location = trim(substr($line, 9));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$bytes): int
                {
                    if (strlen($bytes) + strlen($chunk) > 8388608) {
                        return 0;
                    }
                    $bytes .= $chunk;
                    return strlen($chunk);
                }]);
            if (curl_exec($handle) === false) {
                throw new AppleException('network');
            }
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($status === 200) {
                self::mime($bytes);
                return $bytes;
            }
            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== '') {
                $url = $location;
                continue;
            }
            throw new AppleException('photoDownload', $status);
        }
        throw new AppleException('photoDownload');
    }
}
