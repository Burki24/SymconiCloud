<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use InvalidArgumentException;

/**
 * Pure geofence evaluation with accuracy envelopes, hysteresis and sample confirmation.
 * State is supplied and persisted by the caller; no I/O or platform dependency.
 *
 * @version 1.2.0
 */
trait GeofenceHelper
{
    /**
     * Combines independent tri-state results: any present wins; absence requires
     * a non-empty selection of exclusively absent members. Otherwise unknown.
     *
     * @param list<int> $states -1 unknown, 0 absent, 1 present.
     * @throws InvalidArgumentException Invalid member states or a non-list input.
     */
    protected function AggregateGeofencePresence(array $states): int
    {
        if (!array_is_list($states)) {
            throw new InvalidArgumentException('Presence aggregation requires a list.');
        }
        foreach ($states as $state) {
            if (!is_int($state) || !in_array($state, [-1, 0, 1], true)) {
                throw new InvalidArgumentException('Invalid presence state.');
            }
        }
        if (in_array(1, $states, true)) {
            return 1;
        }
        return $states === [] || in_array(-1, $states, true) ? -1 : 0;
    }

    /**
     * Optional hysteresis/maxAccuracy/confirmSeconds override global settings.
     * customGPS=false explicitly inherits all globals and ignores dormant values.
     * Missing override fields inherit; legacy zones retain their normalized shape.
     * @return list<array<string,mixed>>
     */
    protected function NormalizeGeofenceZones(array $zones): array
    {
        if (!array_is_list($zones) || count($zones) < 1 || count($zones) > 64) {
            throw new InvalidArgumentException('Configure between one and 64 zones.');
        }
        $result = [];
        $seen = [];
        foreach ($zones as $zone) {
            if (!is_array($zone) || !is_string($zone['id'] ?? null) || preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,47}\z/D', $zone['id']) !== 1
                || isset($seen[$zone['id']]) || !is_string($zone['name'] ?? null)
                || preg_match('/\A[^\x00-\x1F\x7F]{1,80}\z/uD', trim($zone['name'])) !== 1
                || !$this->GeofenceCoordinate($zone['latitude'] ?? null, 90) || !$this->GeofenceCoordinate($zone['longitude'] ?? null, 180)
                || !$this->GeofenceNumber($zone['radius'] ?? null) || (float) $zone['radius'] < 1 || (float) $zone['radius'] > 1000000) {
                throw new InvalidArgumentException('Zones require unique IDs, names, valid coordinates and radii.');
            }
            $seen[$zone['id']] = true;
            $normalized = ['id' => $zone['id'], 'name' => trim($zone['name']), 'latitude' => (float) $zone['latitude'],
                'longitude'     => (float) $zone['longitude'], 'radius' => (float) $zone['radius']];
            if (array_key_exists('customGPS', $zone) && !is_bool($zone['customGPS'])) {
                throw new InvalidArgumentException('Invalid zone GPS inheritance flag.');
            }
            if (($zone['customGPS'] ?? true) !== false) {
                foreach (['hysteresis' => [0, 10000], 'maxAccuracy' => [1, 10000], 'confirmSeconds' => [0, 3600]] as $key => [$min, $max]) {
                    if (!array_key_exists($key, $zone) || $zone[$key] === null) {
                        continue;
                    }
                    if (!$this->GeofenceNumber($zone[$key]) || $zone[$key] < $min || $zone[$key] > $max) {
                        throw new InvalidArgumentException('Invalid zone GPS settings.');
                    }
                    $normalized[$key] = (float) $zone[$key];
                }
            }
            $result[] = $normalized;
        }
        return $result;
    }

    /**
     * Resolves and validates per-zone overrides without changing legacy zones.
     * @return array<string,array<string,int|float>> Effective settings by zone ID.
     * @throws InvalidArgumentException Invalid globals, overrides or radius/buffer.
     */
    protected function ResolveGeofenceZoneSettings(array $zones, array $settings = []): array
    {
        $settings += ['maxAge' => 900, 'maxAccuracy' => 100, 'hysteresis' => 25, 'confirmSeconds' => 30, 'maxSpeed' => 300];
        if (count($settings) !== 5) {
            throw new InvalidArgumentException('Invalid evaluation settings.');
        }
        foreach (['maxAge' => [30, 86400], 'maxAccuracy' => [1, 10000], 'hysteresis' => [0, 10000], 'confirmSeconds' => [0, 3600], 'maxSpeed' => [0, 2000]] as $key => [$min, $max]) {
            if (!$this->GeofenceNumber($settings[$key]) || $settings[$key] < $min || $settings[$key] > $max) {
                throw new InvalidArgumentException('Invalid evaluation settings.');
            }
        }
        $resolved = [];
        foreach ($this->NormalizeGeofenceZones($zones) as $zone) {
            $effective = array_replace($settings, array_intersect_key($zone, array_flip(['hysteresis', 'maxAccuracy', 'confirmSeconds'])));
            if ($zone['radius'] <= $effective['hysteresis']) {
                throw new InvalidArgumentException('Zone radius must exceed hysteresis.');
            }
            $resolved[$zone['id']] = $effective;
        }
        return $resolved;
    }

    /** Great-circle distance on the mean-radius Earth, in metres. */
    protected function GeofenceDistanceMeters(float $latitude, float $longitude, float $otherLatitude, float $otherLongitude): float
    {
        if (!$this->GeofenceCoordinate($latitude, 90) || !$this->GeofenceCoordinate($otherLatitude, 90)
            || !$this->GeofenceCoordinate($longitude, 180) || !$this->GeofenceCoordinate($otherLongitude, 180)) {
            throw new InvalidArgumentException('Invalid distance coordinates.');
        }
        $a = sin(deg2rad($otherLatitude - $latitude) / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin(deg2rad($otherLongitude - $longitude) / 2) ** 2;
        return 6371008.8 * 2 * asin(sqrt(max(0.0, min(1.0, $a))));
    }

    /** Clears occupancy after a source failure while retaining the last observed fix watermark. */
    protected function InvalidateGeofenceState(array $state): array
    {
        $empty = $this->GeofenceEmptyState();
        return array_replace(
            $empty,
            array_intersect_key($state, $empty),
            ['zone'           => null, 'since' => 0, 'candidate' => null, 'candidateSince' => 0, 'reason' => 'missing',
                'qualityZone' => ($state['zone'] ?? '') !== '' && ($state['zone'] ?? null) !== null ? $state['zone'] : ($state['qualityZone'] ?? null)]
        );
    }

    /**
     * Evaluates a fix, retaining known assignments inside uncertain boundaries.
     * First reliable fixes establish state immediately; later transitions require
     * distinct samples and elapsed confirmation time. Invalid fixes clear occupancy.
     * Repeated samples may age or advance duration but never confirm transitions.
     * The caller must reset state when the source, zones or settings change.
     *
     * @param array      $zones     One to 64 circular zones, with optional per-zone GPS overrides; see NormalizeGeofenceZones().
     * @param string     $reference Existing zone ID used for presence and centre distance.
     * @param array|null $location  Generic fix: latitude, longitude, accuracy, timestamp, optional isOld/isInaccurate.
     * @param array      $state     Previous returned state, or an empty array for a new source.
     * @param int        $now       Positive Unix seconds supplied by the caller.
     * @param array      $settings  maxAge (s), maxAccuracy (m), hysteresis (m), confirmSeconds (s), maxSpeed (km/h; zero disables).
     *
     * @return array{state:array,presence:int,zone:?string,since:int,duration:int,distance:?float,pending:bool,reason:string}
     *
     * @throws InvalidArgumentException Invalid zones, reference, time or settings.
     */
    protected function EvaluateGeofence(array $zones, string $reference, ?array $location, array $state, int $now, array $settings = []): array
    {
        $zones = $this->NormalizeGeofenceZones($zones);
        $indexed = array_column($zones, null, 'id');
        $settings += ['maxAge' => 900, 'maxAccuracy' => 100, 'hysteresis' => 25, 'confirmSeconds' => 30, 'maxSpeed' => 300];
        if (!isset($indexed[$reference]) || $now < 1 || count($settings) !== 5) {
            throw new InvalidArgumentException('Select a reference zone and valid evaluation settings.');
        }
        $zoneSettings = $this->ResolveGeofenceZoneSettings($zones, $settings);
        $state = $this->GeofenceReadState($state, $indexed);
        if ($now < $state['evaluatedAt']) {
            $state = $this->GeofenceEmptyState();
        }
        $state['evaluatedAt'] = $now;
        $unknown = function (string $reason) use (&$state, $reference, $now): array
        {
            $state = $this->InvalidateGeofenceState($state);
            $state['reason'] = $reason;
            return $this->GeofenceResult($state, $reference, $now, null);
        };
        if ($location === null || !$this->GeofenceCoordinate($location['latitude'] ?? null, 90)
            || !$this->GeofenceCoordinate($location['longitude'] ?? null, 180)) {
            return $unknown('missing');
        }
        $timestamp = $location['timestamp'] ?? null;
        if (!is_int($timestamp) || $timestamp < 1 || $timestamp > $now + 30) {
            return $unknown('timestamp');
        }
        if (($location['isOld'] ?? false) !== false || $now - $timestamp > $settings['maxAge']) {
            return $unknown('stale');
        }
        $accuracy = $location['accuracy'] ?? null;
        $sourceZone = $state['zone'] !== null && $state['zone'] !== '' ? $state['zone'] : $state['qualityZone'];
        if (!$this->GeofenceNumber($accuracy) || $accuracy <= 0
            || ($sourceZone !== null && $accuracy > $zoneSettings[$sourceZone]['maxAccuracy'])
            || ($location['isInaccurate'] ?? false) !== false) {
            return $unknown('inaccurate');
        }
        if ($state['lastTimestamp'] > 0 && $timestamp - $state['lastTimestamp'] > $settings['maxAge']) {
            $state = $this->GeofenceEmptyState();
            $state['evaluatedAt'] = $now;
            $state['qualityZone'] = $sourceZone;
        }
        $latitude = (float) $location['latitude'];
        $longitude = (float) $location['longitude'];
        $newSample = $timestamp > $state['lastTimestamp'];
        if ($timestamp < $state['lastTimestamp']) {
            return $unknown('outOfOrder');
        }
        if ($state['point'] !== null) {
            $previous = $state['point'];
            $moved = $this->GeofenceDistanceMeters($latitude, $longitude, $previous['latitude'], $previous['longitude']);
            if (!$newSample && $moved > 0.01) {
                return $unknown('conflicting');
            }
            if ($newSample && $settings['maxSpeed'] > 0
                && max(0, $moved - (float) $accuracy - $previous['accuracy']) / ($timestamp - $state['lastTimestamp']) * 3.6 > $settings['maxSpeed']) {
                return $unknown('jump');
            }
        }
        $distances = [];
        $outside = [];
        $candidate = null;
        foreach ($zones as $zone) {
            $distance = $this->GeofenceDistanceMeters($latitude, $longitude, $zone['latitude'], $zone['longitude']);
            $distances[$zone['id']] = $distance;
            $effective = $zoneSettings[$zone['id']];
            $outside[$zone['id']] = $distance - $accuracy >= $zone['radius'] + $effective['hysteresis'];
            if (($candidate === null || $zone['id'] === $reference) && $accuracy <= $effective['maxAccuracy']
                && $distance + $accuracy <= $zone['radius'] - $effective['hysteresis']) {
                $candidate = $zone['id'];
            }
        }
        $distance = $distances[$reference];
        // A reliable destination may use its own accuracy far from the reference.
        // Near the reference, or without any established/qualified zone, its
        // stricter quality requirement still prevents a false absence assertion.
        if ($accuracy > $zoneSettings[$reference]['maxAccuracy']
            && (!$outside[$reference] || ($candidate === null && $sourceZone === null))) {
            return $unknown('inaccurate');
        }
        if (!$newSample) {
            return $this->GeofenceResult($state, $reference, $now, $distance);
        }
        $state['lastTimestamp'] = $timestamp;
        $state['point'] = ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => (float) $accuracy];
        $held = false;
        if ($candidate !== $reference && $state['zone'] !== null && $state['zone'] !== '' && $candidate !== $state['zone'] && !$outside[$state['zone']]) {
            $candidate = $state['zone'];
            $held = true;
        }
        if ($candidate !== $reference && !$outside[$reference] && $state['zone'] === $reference) {
            $candidate = $reference;
            $held = true;
        } elseif ($candidate !== $reference && !$outside[$reference] && $state['zone'] === null) {
            $candidate = null;
        }
        if ($candidate === null) {
            if (!in_array(false, $outside, true)) {
                $candidate = '';
            } elseif ($state['zone'] !== null && ($state['zone'] === '' || !$outside[$state['zone']])) {
                $candidate = $state['zone'];
                $held = true;
            } else {
                $result = $unknown('boundary');
                $result['distance'] = $distance;
                return $result;
            }
        }
        if ($candidate === $state['zone']) {
            $state['candidate'] = null;
            $state['candidateSince'] = 0;
            $state['reason'] = $held ? 'held' : 'valid';
        } elseif (($state['zone'] === null && ($sourceZone === null || $sourceZone === $candidate))
            || $this->GeofenceConfirmationSeconds($zoneSettings, $sourceZone, $candidate) == 0) {
            $state['zone'] = $candidate;
            $state['since'] = min($timestamp, $now);
            $state['candidate'] = null;
            $state['candidateSince'] = 0;
            $state['reason'] = 'valid';
        } else {
            if ($state['candidate'] !== $candidate) {
                $state['candidate'] = $candidate;
                $state['candidateSince'] = $timestamp;
            } elseif ($timestamp - $state['candidateSince'] >= $this->GeofenceConfirmationSeconds($zoneSettings, $sourceZone, $candidate)) {
                $state['zone'] = $candidate;
                $state['since'] = min($timestamp, $now);
                $state['candidate'] = null;
                $state['candidateSince'] = 0;
            }
            $state['reason'] = $state['candidate'] !== null ? 'pending' : 'valid';
        }
        if ($state['zone'] !== null) {
            $state['qualityZone'] = $state['zone'] !== '' ? $state['zone'] : null;
        }
        return $this->GeofenceResult($state, $reference, $now, $distance);
    }

    /** Both departure and arrival must finish their confirmation interval. */
    private function GeofenceConfirmationSeconds(array $settings, ?string $source, string $destination): float
    {
        return max((float) ($settings[$source ?? '']['confirmSeconds'] ?? 0), (float) ($settings[$destination]['confirmSeconds'] ?? 0));
    }

    private function GeofenceNumber(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value);
    }

    private function GeofenceCoordinate(mixed $value, int $limit): bool
    {
        return $this->GeofenceNumber($value) && abs((float) $value) <= $limit;
    }

    private function GeofenceEmptyState(): array
    {
        return ['version'   => 1, 'zone' => null, 'since' => 0, 'candidate' => null, 'candidateSince' => 0,
            'lastTimestamp' => 0, 'point' => null, 'evaluatedAt' => 0, 'reason' => 'missing', 'qualityZone' => null];
    }

    private function GeofenceReadState(array $state, array $zones): array
    {
        $empty = $this->GeofenceEmptyState();
        $state += ['qualityZone' => null];
        if (($state['version'] ?? null) !== 1 || array_diff_key($empty, $state) !== []) {
            return $empty;
        }
        foreach (['zone', 'candidate', 'qualityZone'] as $key) {
            if ($key === 'qualityZone' && $state[$key] === '') {
                return $empty;
            }
            if ($state[$key] !== null && (!is_string($state[$key]) || ($state[$key] !== '' && !isset($zones[$state[$key]])))) {
                return $empty;
            }
        }
        foreach (['since', 'candidateSince', 'lastTimestamp', 'evaluatedAt'] as $key) {
            if (!is_int($state[$key]) || $state[$key] < 0) {
                return $empty;
            }
        }
        $point = $state['point'];
        if (($state['lastTimestamp'] > 0 && !is_array($point)) || ($point !== null && (!is_array($point)
            || !$this->GeofenceCoordinate($point['latitude'] ?? null, 90) || !$this->GeofenceCoordinate($point['longitude'] ?? null, 180)
            || !$this->GeofenceNumber($point['accuracy'] ?? null) || $point['accuracy'] <= 0))) {
            return $empty;
        }
        if (!is_string($state['reason']) || $state['since'] > $state['lastTimestamp'] || $state['candidateSince'] > $state['lastTimestamp']) {
            return $empty;
        }
        return array_intersect_key($state, $empty);
    }

    private function GeofenceResult(array $state, string $reference, int $now, ?float $distance): array
    {
        $inZone = $state['zone'] !== null && $state['zone'] !== '';
        return ['state' => $state, 'presence' => $state['zone'] === null ? -1 : (int) ($state['zone'] === $reference),
            'zone'      => $state['zone'], 'since' => $inZone ? $state['since'] : 0,
            'duration'  => $inZone ? max(0, $now - $state['since']) : 0, 'distance' => $distance,
            'pending'   => $state['candidate'] !== null, 'reason' => $state['reason']];
    }
}
