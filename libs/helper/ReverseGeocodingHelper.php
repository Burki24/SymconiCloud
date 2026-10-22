<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

require_once __DIR__ . '/PersistentJsonCacheHelper.php';

/**
 * Optional server-side Nominatim reverse geocoding with bounded persistent cache.
 * Consumers own opt-in, scheduling and serialization. ReadNominatimAddress never
 * performs I/O. ResolveNominatimAddress must run in a serialized background worker.
 * No account credentials or device identifiers are accepted or transmitted.
 *
 * @version 1.0.0
 */
trait ReverseGeocodingHelper
{
    use PersistentJsonCacheHelper;

    /** Registers the module-local address cache; contains private location data. */
    protected function RegisterReverseGeocodingCache(): void
    {
        $this->RegisterPersistentJsonCache('ReverseGeocodingCache');
    }

    /** Clears cached addresses and provider cooldowns, for example when disabling the feature. */
    protected function ClearReverseGeocodingCache(): void
    {
        $this->ClearPersistentJsonCache('ReverseGeocodingCache');
    }

    /**
     * Validates a running private Nominatim base URL, optionally with a path prefix.
     * Public OSM Nominatim is excluded for private device tracking; GitHub URLs
     * identify source repositories, not running geocoding services.
     *
     * @throws \InvalidArgumentException On unsupported URL, credentials, query or fragment.
     */
    protected function NormalizeNominatimServer(string $server): string
    {
        $server = trim($server);
        $parts = parse_url($server);
        if (!filter_var($server, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || preg_match('/[\x00-\x20\x7f\\\\]/', $server)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('A private Nominatim HTTP(S) base URL is required.');
        }
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        if ($host === '' || $host === 'github.com' || $host === 'nominatim.openstreetmap.org' || str_ends_with($host, '.nominatim.openstreetmap.org')) {
            throw new \InvalidArgumentException('Configure a running private Nominatim server.');
        }
        return strtolower($parts['scheme']) . '://' . $host
            . (isset($parts['port']) ? ':' . $parts['port'] : '') . rtrim($parts['path'] ?? '', '/');
    }

    /**
     * Returns a cached plain address, '' for a cached miss/cooldown, or null if pending.
     * Coordinates are rounded to six decimals; entries expire after 24 hours.
     * Server and language are part of the key. No network or persistent writes.
     *
     * @throws \InvalidArgumentException On invalid server, coordinate or language.
     */
    protected function ReadNominatimAddress(string $server, float $latitude, float $longitude, string $language = 'de'): ?string
    {
        [$scope, $key] = $this->NominatimCacheKeys($server, $latitude, $longitude, $language);
        $cache = $this->ReverseGeocodingCache();
        $entry = $cache['entries'][$key] ?? null;
        $now = $this->ReverseGeocodingNow();
        if (is_array($entry) && ($entry['expires'] ?? 0) > $now && is_string($entry['address'] ?? null)) {
            return $entry['address'];
        }
        return ($cache['cooldowns'][$scope] ?? 0) > $now ? '' : null;
    }

    /**
     * Resolves one position, reusing cache. Provider failures return '' and impose
     * a five-minute server cooldown; uncovered coordinates are cached for one hour.
     * At most 512 entries are retained. Call only after explicit user opt-in.
     *
     * @throws \InvalidArgumentException On invalid server, coordinate or language.
     */
    protected function ResolveNominatimAddress(string $server, float $latitude, float $longitude, string $language = 'de'): string
    {
        $cached = $this->ReadNominatimAddress($server, $latitude, $longitude, $language);
        if ($cached !== null) {
            return $cached;
        }
        [$scope, $key] = $this->NominatimCacheKeys($server, $latitude, $longitude, $language);
        $url = $this->NormalizeNominatimServer($server) . '/reverse?' . http_build_query([
            'lat'    => number_format($latitude, 6, '.', ''), 'lon' => number_format($longitude, 6, '.', ''),
            'format' => 'jsonv2', 'addressdetails' => 1, 'accept-language' => $language
        ], '', '&', PHP_QUERY_RFC3986);
        $now = $this->ReverseGeocodingNow();
        $address = '';
        $failed = false;
        try {
            $response = $this->RequestNominatim($url);
            $body = $response['body'] ?? '';
            if (!in_array($response['status'] ?? 0, [200, 404], true) || !is_string($body) || strlen($body) > 65536) {
                throw new \UnexpectedValueException('Geocoding response unavailable.');
            }
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data) || (!is_string($data['display_name'] ?? null) && !is_string($data['error'] ?? null))) {
                throw new \UnexpectedValueException('Invalid geocoding result.');
            }
            if (($response['status'] ?? 0) === 200 && is_string($data['display_name'] ?? null)) {
                $address = trim($data['display_name']);
                if (strlen($address) > 2048 || preg_match('//u', $address) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $address)) {
                    throw new \UnexpectedValueException('Invalid address text.');
                }
            }
        } catch (\Throwable) {
            // External error bodies, URLs and coordinates must never reach logs or UI.
            $address = '';
            $failed = true;
        }
        $cache = $this->ReverseGeocodingCache();
        $cache['entries'] = array_filter($cache['entries'] ?? [], static fn ($entry): bool => is_array($entry) && ($entry['expires'] ?? 0) > $now);
        unset($cache['entries'][$key]);
        $cache['entries'][$key] = ['address' => $address, 'expires' => $now + ($failed ? 300 : ($address === '' ? 3600 : 86400))];
        $cache['entries'] = array_slice($cache['entries'], -512, null, true);
        $cache['cooldowns'] = array_filter($cache['cooldowns'] ?? [], static fn ($until): bool => is_int($until) && $until > $now);
        if ($failed) {
            $cache['cooldowns'][$scope] = $now + 300;
        }
        $cache['cooldowns'] = array_slice($cache['cooldowns'], -16, null, true);
        $this->WritePersistentJsonCache('ReverseGeocodingCache', $cache);
        return $address;
    }

    /** Clock seam for deterministic expiry tests. */
    protected function ReverseGeocodingNow(): int
    {
        return time();
    }

    /**
     * Bounded HTTP GET: verified TLS, no redirects, no cookies or credentials.
     *
     * @return array{status:int,body:string}
     */
    protected function RequestNominatim(string $url): array
    {
        $handle = curl_init($url);
        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'Symcon-ModuleHelper/ReverseGeocoding (+https://github.com/Burki24/Symcon_ModuleHelper)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION  => static function ($curl, string $chunk) use (&$body): int
            {
                if (strlen($body) + strlen($chunk) > 65536) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }
        ]);
        try {
            $ok = curl_exec($handle);
            return ['status' => $ok === false ? 0 : (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
        } finally {
            curl_close($handle);
        }
    }

    /** @return array{string,string} Namespace and coordinate key; raw locations are not used as attribute keys. */
    private function NominatimCacheKeys(string $server, float $latitude, float $longitude, string $language): array
    {
        $server = $this->NormalizeNominatimServer($server);
        if (!is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180
            || !preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/D', $language)) {
            throw new \InvalidArgumentException('Invalid geocoding coordinate or language.');
        }
        $scope = hash('sha256', $server . '|' . strtolower($language));
        return [$scope, hash('sha256', $scope . '|' . number_format($latitude, 6, '.', '') . '|' . number_format($longitude, 6, '.', ''))];
    }

    /** @return array<string,mixed> Corrupt local cache is recoverable without affecting the visualization. */
    private function ReverseGeocodingCache(): array
    {
        try {
            $cache = $this->ReadPersistentJsonCache('ReverseGeocodingCache');
            return ['entries' => is_array($cache['entries'] ?? null) ? $cache['entries'] : [],
                'cooldowns'   => is_array($cache['cooldowns'] ?? null) ? $cache['cooldowns'] : []];
        } catch (\UnexpectedValueException) {
            return [];
        }
    }
}
