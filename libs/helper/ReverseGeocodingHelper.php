<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

require_once __DIR__ . '/PersistentJsonCacheHelper.php';

/**
 * Optional Nominatim/Geoapify reverse geocoding with bounded persistent cache.
 * Consumers own opt-in, scheduling and serialization. ReadNominatimAddress never
 * performs I/O. ResolveNominatimAddress must run in a serialized background worker.
 * No Apple credentials or device identifiers are accepted or transmitted.
 *
 * @version 1.1.0
 */
trait ReverseGeocodingHelper
{
    use PersistentJsonCacheHelper;

    /** Registers the module-local address cache; contains private location data. */
    protected function RegisterReverseGeocodingCache(): void
    {
        $this->RegisterPersistentJsonCache('ReverseGeocodingCache');
        $this->RegisterPersistentJsonCache('GeoapifyDailyUsage');
    }

    /** Clears cached addresses and provider cooldowns, for example when disabling the feature. */
    protected function ClearReverseGeocodingCache(): void
    {
        $this->ClearPersistentJsonCache('ReverseGeocodingCache');
    }

    /**
     * Conservative maximum address detail from a reported horizontal radius in metres.
     * These tiers are display policy, not a guarantee that an OSM address is exact.
     * Missing, zero, negative or non-finite accuracy is unknown and uses city detail.
     *
     * @return array{zoom:int,level:string,approximate:bool,accuracy:?float}
     */
    protected function ReverseGeocodingPrecision(?float $accuracy): array
    {
        $known = $accuracy !== null && is_finite($accuracy) && $accuracy > 0;
        [$zoom, $level] = match (true) {
            !$known           => [10, 'unknown'],
            $accuracy <= 20   => [18, 'building'],
            $accuracy <= 100  => [17, 'street'],
            $accuracy <= 1000 => [14, 'area'],
            default           => [10, 'city']
        };
        return ['zoom' => $zoom, 'level' => $level, 'approximate' => $zoom !== 18, 'accuracy' => $known ? $accuracy : null];
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
     * Server, language and requested zoom are part of the key. No network or persistent writes.
     * Zoom defaults to 18 for existing callers; accuracy-aware consumers pass the policy zoom.
     *
     * @throws \InvalidArgumentException On invalid server, coordinate, language or zoom.
     */
    protected function ReadNominatimAddress(string $server, float $latitude, float $longitude, string $language = 'de', int $zoom = 18): ?string
    {
        [$scope, $key] = $this->NominatimCacheKeys($server, $latitude, $longitude, $language, $zoom);
        return $this->ReadReverseGeocodingAddress($scope, $key);
    }

    /**
     * Resolves one position, reusing cache. Provider failures return '' and impose
     * a five-minute server cooldown; uncovered coordinates are cached for one hour.
     * At most 512 entries are retained. Call only after explicit user opt-in.
     * Zoom 0..18 limits address detail. Structured address fields use the same compact
     * street/house, postcode/place, country layout as Geoapify, without raw-text fallback.
     *
     * @throws \InvalidArgumentException On invalid server, coordinate, language or zoom.
     */
    protected function ResolveNominatimAddress(string $server, float $latitude, float $longitude, string $language = 'de', int $zoom = 18): string
    {
        $cached = $this->ReadNominatimAddress($server, $latitude, $longitude, $language, $zoom);
        if ($cached !== null) {
            return $cached;
        }
        [$scope, $key] = $this->NominatimCacheKeys($server, $latitude, $longitude, $language, $zoom);
        $url = $this->NormalizeNominatimServer($server) . '/reverse?' . http_build_query([
            'lat'    => number_format($latitude, 6, '.', ''), 'lon' => number_format($longitude, 6, '.', ''),
            'format' => 'jsonv2', 'addressdetails' => 1, 'accept-language' => $language, 'zoom' => $zoom, 'layer' => 'address'
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
            if (!is_array($data) || (!is_array($data['address'] ?? null) && !is_string($data['error'] ?? null))) {
                throw new \UnexpectedValueException('Invalid geocoding result.');
            }
            if (($response['status'] ?? 0) === 200 && is_array($data['address'] ?? null)) {
                $address = $this->CompactReverseGeocodingAddress($data['address'], $zoom);
                if (strlen($address) > 2048 || preg_match('//u', $address) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $address)) {
                    throw new \UnexpectedValueException('Invalid address text.');
                }
            }
        } catch (\Throwable) {
            // External error bodies, URLs and coordinates must never reach logs or UI.
            $address = '';
            $failed = true;
        }
        $this->StoreReverseGeocodingAddress($scope, $key, $address, $failed, $now);
        return $address;
    }

    /** Validates a Geoapify credential without exposing its value in exceptions. */
    protected function NormalizeGeoapifyKey(string $apiKey): string
    {
        $apiKey = trim($apiKey);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,256}$/D', $apiKey)) {
            throw new \InvalidArgumentException('A valid Geoapify API key is required.');
        }
        return $apiKey;
    }

    /** Geoapify uses two-letter languages; regional variants select their base language. */
    protected function NormalizeGeoapifyLanguage(string $language): string
    {
        if (!preg_match('/^[a-zA-Z]{2}(?:-[a-zA-Z0-9]{2,8})?$/D', $language)) {
            throw new \InvalidArgumentException('A two-letter Geoapify language is required.');
        }
        return strtolower(substr($language, 0, 2));
    }

    /**
     * Cache-only Geoapify lookup. Same null/pending and empty/miss contract as Nominatim.
     * Credentials are hashed in cache scopes and are never returned or persisted here.
     */
    protected function ReadGeoapifyAddress(string $apiKey, float $latitude, float $longitude, string $language = 'de', int $zoom = 18): ?string
    {
        $apiKey = $this->NormalizeGeoapifyKey($apiKey);
        $language = $this->NormalizeGeoapifyLanguage($language);
        [$scope, $key] = $this->NominatimCacheKeys('https://api.geoapify.com/v1/geocode', $latitude, $longitude, $language, $zoom, hash('sha256', $apiKey));
        return $this->ReadReverseGeocodingAddress($scope, $key);
    }

    /**
     * Persistent instance-local UTC request budget, independent of address-cache clearing.
     * 0 disables new requests. Used includes failed HTTP attempts and survives key changes.
     * Consumers must serialize ResolveGeoapifyAddress per instance. Shared keys across
     * instances/systems require budgets whose sum fits the provider's account allowance.
     *
     * @return array{day:string,used:int,remaining:int,available:bool,nextReset:int}
     */
    protected function GeoapifyDailyUsage(int $dailyLimit): array
    {
        if ($dailyLimit < 0 || $dailyLimit > 1000000) {
            throw new \InvalidArgumentException('Geoapify daily limit must be between 0 and 1000000.');
        }
        $now = $this->ReverseGeocodingNow();
        $today = gmdate('Y-m-d', $now);
        try {
            $usage = $this->ReadPersistentJsonCache('GeoapifyDailyUsage');
        } catch (\UnexpectedValueException) {
            $usage = ['invalid' => true];
        }
        $valid = $usage === [] || (is_string($usage['day'] ?? null)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $usage['day'])
            && checkdate((int) substr($usage['day'], 5, 2), (int) substr($usage['day'], 8, 2), (int) substr($usage['day'], 0, 4))
            && is_int($usage['used'] ?? null) && $usage['used'] >= 0);
        $available = $valid && ($usage['day'] ?? $today) <= $today;
        $used = !$available ? PHP_INT_MAX : (($usage['day'] ?? '') === $today ? $usage['used'] : 0);
        return ['day'   => $today, 'used' => $used, 'remaining' => max(0, $dailyLimit - $used),
            'available' => $available, 'nextReset' => $now - ($now % 86400) + 86400];
    }

    /**
     * At most one counted HTTP request to the fixed Geoapify HTTPS endpoint.
     * Uses the shared accuracy zoom and filters structured coarse results locally.
     * Positive/negative TTLs and provider cooldowns match Nominatim. Cache hits are
     * still available at the daily limit. No automatic switch to another provider.
     */
    protected function ResolveGeoapifyAddress(string $apiKey, float $latitude, float $longitude, string $language = 'de', int $zoom = 18, int $dailyLimit = 2500): string
    {
        $usage = $this->GeoapifyDailyUsage($dailyLimit);
        $cached = $this->ReadGeoapifyAddress($apiKey, $latitude, $longitude, $language, $zoom);
        if ($cached !== null) {
            return $cached;
        }
        if ($usage['remaining'] === 0) {
            if (!$usage['available']) {
                $this->WritePersistentJsonCache('GeoapifyDailyUsage', ['day' => $usage['day'], 'used' => PHP_INT_MAX]);
            }
            return '';
        }
        $apiKey = $this->NormalizeGeoapifyKey($apiKey);
        $language = $this->NormalizeGeoapifyLanguage($language);
        [$scope, $key] = $this->NominatimCacheKeys('https://api.geoapify.com/v1/geocode', $latitude, $longitude, $language, $zoom, hash('sha256', $apiKey));
        $url = 'https://api.geoapify.com/v1/geocode/reverse?' . http_build_query([
            'lat'    => number_format($latitude, 6, '.', ''), 'lon' => number_format($longitude, 6, '.', ''),
            'format' => 'json', 'lang' => $language, 'limit' => 1,
            'type'   => $zoom === 18 ? 'building' : ($zoom >= 16 ? 'street' : ($zoom >= 12 ? 'locality' : 'city')),
            'apiKey' => $apiKey
        ], '', '&', PHP_QUERY_RFC3986);
        // Reserve before I/O: even interrupted or failed requests consume the local budget.
        $this->WritePersistentJsonCache('GeoapifyDailyUsage', ['day' => $usage['day'], 'used' => $usage['used'] + 1]);
        $now = $this->ReverseGeocodingNow();
        $address = '';
        $failed = false;
        try {
            $response = $this->RequestGeoapify($url);
            $body = $response['body'] ?? '';
            if (($response['status'] ?? 0) !== 200 || !is_string($body) || strlen($body) > 65536) {
                throw new \UnexpectedValueException('Geoapify response unavailable.');
            }
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !is_array($data['results'] ?? null) || !array_is_list($data['results'])) {
                throw new \UnexpectedValueException('Invalid Geoapify response.');
            }
            if ($data['results'] !== []) {
                $result = $data['results'][0];
                if (!is_array($result)) {
                    throw new \UnexpectedValueException('Invalid Geoapify address.');
                }
                $parts = [];
                foreach (['housenumber' => 'house_number', 'street' => 'road', 'suburb' => 'suburb', 'district' => 'city_district', 'city' => 'city',
                    'county'            => 'county', 'state' => 'state', 'postcode' => 'postcode', 'country' => 'country'] as $from => $to) {
                    $parts[$to] = $result[$from] ?? null;
                }
                $address = $this->CompactReverseGeocodingAddress($parts, $zoom);
                if (strlen($address) > 2048 || preg_match('//u', $address) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $address)) {
                    throw new \UnexpectedValueException('Invalid address text.');
                }
            }
        } catch (\Throwable) {
            // Never expose provider bodies, request URLs, credentials or coordinates.
            $address = '';
            $failed = true;
        }
        $this->StoreReverseGeocodingAddress($scope, $key, $address, $failed, $now);
        return $address;
    }

    /** Counted Geoapify transport seam; shares bounded TLS/redirect handling with Nominatim. */
    protected function RequestGeoapify(string $url): array
    {
        return $this->RequestReverseGeocoding($url);
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
        return $this->RequestReverseGeocoding($url);
    }

    private function ReadReverseGeocodingAddress(string $scope, string $key): ?string
    {
        $cache = $this->ReverseGeocodingCache();
        $entry = $cache['entries'][$key] ?? null;
        $now = $this->ReverseGeocodingNow();
        if (is_array($entry) && ($entry['expires'] ?? 0) > $now && is_string($entry['address'] ?? null)) {
            return $entry['address'];
        }
        return ($cache['cooldowns'][$scope] ?? 0) > $now ? '' : null;
    }

    private function StoreReverseGeocodingAddress(string $scope, string $key, string $address, bool $failed, int $now): void
    {
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
    }

    /** Shared bounded HTTP implementation; request URLs must never be logged by callers. */
    private function RequestReverseGeocoding(string $url): array
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
    private function NominatimCacheKeys(string $server, float $latitude, float $longitude, string $language, int $zoom, string $credentialScope = ''): array
    {
        $server = $this->NormalizeNominatimServer($server);
        if (!is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180
            || !preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/D', $language) || $zoom < 0 || $zoom > 18) {
            throw new \InvalidArgumentException('Invalid geocoding coordinate, language or zoom.');
        }
        $scope = hash('sha256', $server . '|' . strtolower($language) . ($credentialScope === '' ? '' : '|' . $credentialScope));
        return [$scope, hash('sha256', 'compact-v1|' . $scope . '|' . $zoom . '|' . number_format($latitude, 6, '.', '') . '|' . number_format($longitude, 6, '.', ''))];
    }

    /**
     * Common compact layout from normalized provider fields. Never parses a provider's
     * display_name/formatted text. House numbers require building detail; administrative
     * hierarchies are reduced to one place fallback when no city/village is available.
     */
    private function CompactReverseGeocodingAddress(mixed $address, int $zoom): string
    {
        if (!is_array($address)) {
            return '';
        }
        $first = static function (array $fields) use ($address): string
        {
            foreach ($fields as $field) {
                $value = $address[$field] ?? null;
                if (!is_string($value) || trim($value) === '') {
                    continue;
                }
                if (strlen($value) > 2048 || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                    throw new \UnexpectedValueException('Invalid address component.');
                }
                return preg_replace('/\s+/u', ' ', trim($value));
            }
            return '';
        };
        $street = $zoom >= 16 ? $first(['road', 'pedestrian', 'residential', 'footway', 'path', 'cycleway', 'square']) : '';
        if ($zoom === 18 && $street !== '') {
            $street = trim($street . ' ' . $first(['house_number']));
        }
        $area = $zoom >= 12 ? $first(['neighbourhood', 'quarter', 'suburb', 'city_district', 'borough']) : '';
        $place = $zoom >= 10 ? $first(['city', 'town', 'village', 'municipality', 'hamlet']) : '';
        if ($place === '') {
            $place = $area;
        }
        if ($place === '' && $zoom >= 8) {
            $place = $first(['county', 'state_district']);
        }
        if ($place === '' && $zoom >= 5) {
            $place = $first(['state']);
        }
        $postcode = $zoom >= 12 ? $first(['postcode']) : '';
        $area = $zoom >= 12 && $zoom < 16 && $area !== $place ? $area : '';
        $postalPlace = trim($postcode . ' ' . $place);
        $country = $first(['country']);
        return implode(', ', array_unique(array_filter([$street, $area, $postalPlace, $country], static fn (string $part): bool => $part !== '')));
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
