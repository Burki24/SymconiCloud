<?php

declare(strict_types=1);

namespace SymconiCloud;

/** WoIst-specific opt-in and background scheduling; HTTP and caching live centrally. */
trait WoIstGeocoding
{
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
                if ($location === null || $this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language']) !== null) {
                    continue;
                }
                $this->ResolveNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language']);
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
    /** Registers optional settings and a dormant, documented Symcon timer. */
    private function RegisterWoIstGeocoding(): void
    {
        $this->RegisterReverseGeocodingCache();
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
        return $this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language']) ?? '';
    }

    /** Starts bounded background work only while authenticated positions have cache misses. */
    private function ScheduleWoIstGeocoding(array $state): void
    {
        $configuration = $this->WoIstGeocodingConfiguration();
        $pending = false;
        if ($configuration !== null && $state['success']) {
            foreach ($state['devices'] as $device) {
                $location = $device['location'];
                if ($location !== null && $this->ReadNominatimAddress($configuration['server'], $location['latitude'], $location['longitude'], $configuration['language']) === null) {
                    $pending = true;
                    break;
                }
            }
        }
        $this->SetTimerInterval('ResolveAddresses', $pending ? 2000 : 0);
    }
}
