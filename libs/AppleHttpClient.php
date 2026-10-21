<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/AppleTransport.php';
require_once __DIR__ . '/AppleException.php';

/** HTTPS transport with a domain-aware cookie engine and no redirects. */
final class AppleHttpClient implements AppleTransport
{
    private \CurlHandle $handle;

    /** @param list<string> $cookies Previously persisted cookie records. */
    public function __construct(array $cookies = [])
    {
        if (!extension_loaded('curl')) {
            throw new AppleException('dependency');
        }
        $this->handle = curl_init();
        curl_setopt($this->handle, CURLOPT_COOKIEFILE, '');
        foreach ($cookies as $cookie) {
            if (is_string($cookie) && !str_contains($cookie, "\n") && !str_contains($cookie, "\r")) {
                curl_setopt($this->handle, CURLOPT_COOKIELIST, $cookie);
            }
        }
    }

    /** Only Apple's HTTPS origins may receive authenticated requests. */
    public static function assertURL(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || !($host === 'idmsa.apple.com' || $host === 'setup.icloud.com'
                || preg_match('/\A[a-z0-9-]+(?:\.[a-z0-9-]+)*\.icloud\.com\z/D', $host) === 1)) {
            throw new AppleException('endpoint');
        }
    }

    /** @inheritDoc */
    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        self::assertURL($url);
        if (!in_array($method, ['GET', 'POST', 'PUT'], true)) {
            throw new AppleException('request');
        }
        $responseHeaders = [];
        $responseBody = '';
        $requestHeaders = ['Accept: application/json', 'Content-Type: application/json', 'Origin: https://www.icloud.com', 'Referer: https://www.icloud.com/'];
        foreach ($headers as $name => $value) {
            if (strpbrk($name . $value, "\r\n") !== false) {
                throw new AppleException('request');
            }
            $requestHeaders[] = $name . ': ' . $value;
        }
        curl_setopt_array($this->handle, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER     => $requestHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 SymconiCloud/0.1',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int
            {
                if (str_starts_with($line, 'HTTP/')) {
                    $responseHeaders = [];
                }
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $name = strtolower(trim($name));
                    if (in_array($name, ['scnt', 'x-apple-id-session-id', 'x-apple-session-token', 'x-apple-twosv-trust-token', 'x-apple-id-account-country', 'retry-after'], true)) {
                        $responseHeaders[$name] = trim($value);
                    }
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody): int
            {
                if (strlen($responseBody) + strlen($chunk) > 4 * 1024 * 1024) {
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            }
        ]);
        // CURLOPT_POSTFIELDS may select POST; set the intended verb last.
        curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $method);
        if (curl_exec($this->handle) === false) {
            throw new AppleException('network');
        }
        $status = (int) curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
        $decoded = [];
        if (trim($responseBody) !== '') {
            try {
                $value = json_decode($responseBody, true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($value)) {
                    throw new \JsonException();
                }
                $decoded = $value;
            } catch (\JsonException) {
                // Errors can legitimately be HTML; expose only HTTP status.
                if ($status >= 200 && $status < 300) {
                    throw new AppleException('response', $status);
                }
            }
        }
        return ['status' => $status, 'headers' => $responseHeaders, 'body' => $decoded];
    }

    /** @inheritDoc */
    public function cookies(): array
    {
        return curl_getinfo($this->handle, CURLINFO_COOKIELIST) ?: [];
    }
}
