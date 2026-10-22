<?php

declare(strict_types=1);

namespace SymconiCloud;

/** WoIst-specific opt-in and background scheduling; HTTP and caching live centrally. */
trait WoIstGeocoding
{
    /** @return string Cached address only for the device's current authenticated position. */
    public function GetDeviceAddress(string $DeviceID, float $Latitude, float $Longitude): string
    {
        $state = $this->bootstrap($this->request('GetDevices'))['state'];
        if (!$state['success']) {
            return '';
        }
        foreach ($state['devices'] as $device) {
            $location = $device['location'];
            if ($device['id'] === $DeviceID && $location !== null
                && $location['latitude'] === $Latitude && $location['longitude'] === $Longitude) {
                return $device['address'] === '' ? '' : ($device['addressNotice'] === '' ? '' : $device['addressNotice'] . ': ') . $device['address'];
            }
        }
        return '';
    }

    /**
     * Resolves at most one uncached coordinate. Display/poll callbacks never do HTTP.
     * A fresh account-cache read after I/O prevents old locations being republished.
     *
     * @return bool Whether one position was processed (including a cached provider failure).
     */
    public function ResolveAddresses(): bool
    {
        $lock = 'SymconiCloud.Geocoding.' . $this->InstanceID;
        if (!\IPS_SemaphoreEnter($lock, 0)) {
            return false;
        }
        try {
            $this->SetTimerInterval('ResolveAddresses', 0);
            $configuration = $this->WoIstGeocodingConfiguration();
            if ($configuration === null) {
                return false;
            }
            $state = $this->bootstrap($this->request('GetDevices'))['state'];
            if (!$state['success']) {
                return false;
            }
            foreach ($state['devices'] as $device) {
                $location = $device['location'];
                if ($location === null) {
                    continue;
                }
                $zoom = $this->ReverseGeocodingPrecision($location['horizontalAccuracy'])['zoom'];
                if ($this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom) !== null) {
                    continue;
                }
                $this->ResolveNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom);
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
        $this->RegisterPropertyString('AddressLanguage', 'de');
        $this->RegisterTimer('ResolveAddresses', 0, 'ICWI_ResolveAddresses($_IPS["TARGET"]);');
    }

    /** @return array{server:string,language:string}|null Disabled or invalid configuration performs no lookup. */
    private function WoIstGeocodingConfiguration(): ?array
    {
        if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
            return null;
        }
        try {
            $server = $this->NormalizeNominatimServer($this->ReadPropertyString('NominatimServer'));
            $language = trim($this->ReadPropertyString('AddressLanguage'));
            if (!preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/D', $language)) {
                return null;
            }
            return ['server' => $server, 'language' => $language];
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
        return $this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom) ?? '';
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
                if ($location !== null && $this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language'], $this->ReverseGeocodingPrecision($location['horizontalAccuracy'])['zoom']) === null) {
                    $pending = true;
                    break;
                }
            }
        }
        $this->SetTimerInterval('ResolveAddresses', $pending ? 2000 : 0);
    }
}
