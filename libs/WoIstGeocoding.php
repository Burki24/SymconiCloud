<?php

declare(strict_types=1);

namespace SymconiCloud;

/** WoIst-specific opt-in and background scheduling; HTTP and caching live centrally. */
trait WoIstGeocoding
{
    /** @return string Cached address only for the device's current authenticated position. */
    public function GetDeviceAddress(string $DeviceID, float $Latitude, float $Longitude): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($DeviceID, $Latitude, $Longitude): string
        {
            $state = $this->bootstrap($this->request('GetDevices'), false)['state'];
            if (!$state['success']) {
                return '';
            }
            foreach ($state['devices'] as $device) {
                $location = $device['location'];
                if ($device['id'] === $DeviceID && ($device['accountAvailable'] ?? true) && $location !== null
                    && $location['latitude'] === $Latitude && $location['longitude'] === $Longitude) {
                    return $device['address'] === '' ? '' : ($device['addressNotice'] === '' ? '' : $device['addressNotice'] . ': ') . $device['address'];
                }
            }
            return '';
        });
    }

    /**
     * Resolves at most one uncached coordinate. Display/poll callbacks never do HTTP.
     * A fresh account-cache read after I/O prevents old locations being republished.
     *
     * @return bool Whether one position was processed (including a cached provider failure).
     */
    public function ResolveAddresses(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            $lock = 'SymconiCloud.Geocoding.' . $this->InstanceID;
            if (!\IPS_SemaphoreEnter($lock, 0)) {
                $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
                return false;
            }
            try {
                $this->CloudDebugTimer('ResolveAddresses', 0, __FUNCTION__, __LINE__);
                $configuration = $this->WoIstGeocodingConfiguration();
                if ($configuration === null) {
                    $this->DebugFlowEvent($this->CloudDebugFunction(__FUNCTION__), __LINE__, 'AKTION', 'Adressauflösung ist ausgeschaltet oder nicht vollständig konfiguriert. Anbieter, Serveradresse beziehungsweise API-Key prüfen; Inhalte werden nicht ausgegeben.');
                    return false;
                }
                $state = $this->bootstrap($this->request('GetDevices'), false)['state'];
                if (!$state['success']) {
                    return false;
                }
                foreach ($state['devices'] as $device) {
                    $location = $device['location'];
                    if ($location === null || ($device['accountAvailable'] ?? true) === false) {
                        continue;
                    }
                    $zoom = $this->ReverseGeocodingPrecision($location['horizontalAccuracy'])['zoom'];
                    if ($this->WoIstReadAddress($configuration, $location, $zoom) !== null) {
                        continue;
                    }
                    if ($configuration['provider'] === 'geoapify') {
                        $this->DebugFlowEvent($this->CloudDebugFunction(__FUNCTION__), __LINE__, 'SENDEN', 'Adressauflösung für eine Position angefordert. Anbieterlimit, Wartezeit und Cache bleiben wirksam; Koordinaten und Schlüssel bleiben geschützt.', ['Anbieter' => 'Geoapify']);
                        $address = $this->ResolveGeoapifyAddress($configuration['key'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom, $configuration['limit']);
                    } else {
                        $this->DebugFlowEvent($this->CloudDebugFunction(__FUNCTION__), __LINE__, 'SENDEN', 'Adressauflösung für eine Position angefordert. Wartezeit und Cache bleiben wirksam; Koordinaten und Serveradresse bleiben geschützt.', ['Anbieter' => 'Nominatim']);
                        $address = $this->ResolveNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom);
                    }
                    $this->DebugFlowEvent($this->CloudDebugFunction(__FUNCTION__), __LINE__, $address !== '' ? 'EMPFANG' : 'FEHLER', $address !== '' ? 'Eine Adresse liegt vor; die Genauigkeitsregeln bleiben wirksam. Der Adressinhalt ist geschützt.' : 'Keine Adresse ermittelt. Anbieterfreigabe, Tageslimit, Wartezeit und vorangehende Meldungen prüfen; das ist keine bestätigte Adresse.');
                    if ($this->WoIstGeocodingConfiguration() !== $configuration) {
                        $this->ClearReverseGeocodingCache();
                    }
                    $this->publish($this->request('GetDevices'));
                    return true;
                }
                return false;
            } finally {
                \IPS_SemaphoreLeave($lock);
            }
        });
    }

    /** Refreshes only sibling device caches after address or source-state changes. */
    private function NotifyAddressDevices(): void
    {
        $enabled = $this->ReadPropertyBoolean('EnableAddressResolution');
        $wasEnabled = $this->ReadAttributeBoolean('AddressPropagationActive');
        $this->WriteAttributeBoolean('AddressPropagationActive', $enabled);
        if (!$enabled && !$wasEnabled) {
            return;
        }
        $parent = $this->GetParentID();
        if ($parent <= 0) {
            return;
        }
        foreach (\IPS_GetInstanceListByModuleID('{DEA72AD7-7877-4392-B64F-055B63D1E5C4}') as $instanceID) {
            try {
                $source = (int) \IPS_GetProperty($instanceID, 'AddressSource');
                if ((int) \IPS_GetInstance($instanceID)['ConnectionID'] === $parent && ($source === 0 || $source === $this->InstanceID)) {
                    \ICID_Update($instanceID);
                }
            } catch (\Throwable) {
                // A removed or broken optional consumer cannot interrupt map updates.
            }
        }
    }
    /** Registers optional settings and a dormant, documented Symcon timer. */
    private function RegisterWoIstGeocoding(): void
    {
        $this->RegisterReverseGeocodingCache();
        $this->RegisterAttributeBoolean('AddressPropagationActive', false);
        $this->RegisterPropertyBoolean('EnableAddressResolution', false);
        $this->RegisterPropertyString('NominatimServer', '');
        $this->RegisterPropertyString('AddressProvider', 'nominatim');
        $this->RegisterPropertyString('GeoapifyAPIKey', '');
        $this->RegisterPropertyInteger('GeoapifyDailyLimit', 2500);
        $this->RegisterPropertyString('AddressLanguage', 'de');
        $this->RegisterTimer('ResolveAddresses', 0, 'ICWI_ResolveAddresses($_IPS["TARGET"]);');
    }

    /** @return array|null Valid selected provider only; credentials stay server-side. */
    private function WoIstGeocodingConfiguration(): ?array
    {
        if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
            return null;
        }
        try {
            $language = trim($this->ReadPropertyString('AddressLanguage'));
            $provider = $this->ReadPropertyString('AddressProvider');
            if ($provider === 'geoapify') {
                $limit = $this->ReadPropertyInteger('GeoapifyDailyLimit');
                $this->GeoapifyDailyUsage($limit);
                return ['provider' => $provider, 'key' => $this->NormalizeGeoapifyKey($this->ReadPropertyString('GeoapifyAPIKey')),
                    'language'     => $this->NormalizeGeoapifyLanguage($language), 'limit' => $limit];
            }
            if ($provider !== 'nominatim') {
                return null;
            }
            $server = $this->NormalizeNominatimServer($this->ReadPropertyString('NominatimServer'));
            if (!preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/D', $language)) {
                return null;
            }
            return ['provider' => $provider, 'server' => $server, 'language' => $language];
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return string Cache-only address for the exact displayed position. */
    private function WoIstCachedAddress(?array $location): string
    {
        $configuration = $this->WoIstGeocodingConfiguration();
        if ($location === null || $configuration === null) {
            return '';
        }
        $zoom = $this->ReverseGeocodingPrecision($location['horizontalAccuracy'])['zoom'];
        return $this->WoIstReadAddress($configuration, $location, $zoom) ?? '';
    }

    private function WoIstReadAddress(array $configuration, array $location, int $zoom): ?string
    {
        return $configuration['provider'] === 'geoapify'
            ? $this->ReadGeoapifyAddress($configuration['key'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom)
            : $this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom);
    }

    /** Sanitized provider status; no key, endpoint or credential fingerprint enters HTML/JSON. */
    private function WoIstGeocodingStatus(): array
    {
        $configuration = $this->WoIstGeocodingConfiguration();
        if ($configuration === null || $configuration['provider'] !== 'geoapify') {
            return ['provider' => $configuration['provider'] ?? '', 'notice' => ''];
        }
        $usage = $this->GeoapifyDailyUsage($configuration['limit']);
        return ['provider' => 'geoapify', 'notice' => $usage['remaining'] > 0 ? ''
            : $this->Translate('Geoapify daily limit reached. Cached addresses remain available. New requests resume on the next UTC day if the limit is above zero.')];
    }

    /** Translates the central accuracy policy without claiming an exact OSM match. */
    private function WoIstAddressNotice(?array $location): string
    {
        if ($location === null || $this->WoIstGeocodingConfiguration() === null) {
            return '';
        }
        $precision = $this->ReverseGeocodingPrecision($location['horizontalAccuracy']);
        if (!$precision['approximate']) {
            return '';
        }
        if ($precision['level'] === 'unknown') {
            return $this->Translate('Approximate – city only (location accuracy unknown)');
        }
        $level = $this->Translate(match ($precision['level']) {
            'street' => 'street only',
            'area'   => 'area only',
            default  => 'city only'
        });
        return sprintf($this->Translate('Approximate – %s (location accuracy: about %s m)'), $level, (string) (int) ceil($precision['accuracy']));
    }

    /** Starts bounded background work only while authenticated positions have cache misses. */
    private function ScheduleWoIstGeocoding(array $state): void
    {
        $configuration = $this->WoIstGeocodingConfiguration();
        $pending = false;
        if ($configuration !== null && $state['success']) {
            foreach ($state['devices'] as $device) {
                $location = $device['location'];
                if ($location !== null && ($device['accountAvailable'] ?? true) && $this->WoIstReadAddress($configuration, $location, $this->ReverseGeocodingPrecision($location['horizontalAccuracy'])['zoom']) === null) {
                    $pending = true;
                    break;
                }
            }
        }
        $interval = $pending ? 2000 : 0;
        if ($pending && $configuration['provider'] === 'geoapify') {
            $usage = $this->GeoapifyDailyUsage($configuration['limit']);
            if ($usage['remaining'] === 0 && $usage['available']) {
                $interval = $configuration['limit'] === 0 ? 0 : max(1000, ($usage['nextReset'] - $this->ReverseGeocodingNow()) * 1000);
            }
        }
        $this->CloudDebugTimer('ResolveAddresses', $interval, __FUNCTION__, __LINE__);
    }
}
