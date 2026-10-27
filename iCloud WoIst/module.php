<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CloudDebug.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VisualizationAssetHelper.php';
require_once __DIR__ . '/../libs/helper/VisualizationThemeConfigurationHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewStyleConfigurationHelper.php';
require_once __DIR__ . '/../libs/helper/ResponsiveVisualizationHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewHTMLPageHelper.php';
require_once __DIR__ . '/../libs/helper/OpenFreeMapHelper.php';
require_once __DIR__ . '/../libs/WoIstDesign.php';
require_once __DIR__ . '/../libs/WoIstIPSViewTransport.php';
require_once __DIR__ . '/../libs/helper/HttpResponseHelper.php';
require_once __DIR__ . '/../libs/helper/ReverseGeocodingHelper.php';
require_once __DIR__ . '/../libs/WoIstGeocoding.php';
require_once __DIR__ . '/../libs/IPSViewFrame.php';
require_once __DIR__ . '/../libs/WoIstAccounts.php';

/** Assigned account device positions, rendered through the authenticated HTML SDK. */
class iCloudWoIst extends IPSModuleStrict
{
    use \SymconiCloud\CloudDebug;
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\VisualizationAssetHelper;
    use \Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;
    use \Burki24\SymconModuleHelper\IPSViewStyleConfigurationHelper;
    use \Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;
    use \Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
    use \Burki24\SymconModuleHelper\OpenFreeMapHelper;
    use \SymconiCloud\WoIstDesign;
    use \SymconiCloud\WoIstIPSViewTransport;
    use \Burki24\SymconModuleHelper\HttpResponseHelper;
    use \Burki24\SymconModuleHelper\ReverseGeocodingHelper;
    use \SymconiCloud\WoIstGeocoding;
    use \SymconiCloud\IPSViewFrame;
    use \SymconiCloud\WoIstAccounts;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    /** Registers map settings/cache polling; credentials and Apple refresh timers belong to accounts. */
    public function Create(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterIPSViewHTMLPageProperties();
            $this->RegisterWoIstIPSViewTransport();
            $this->RegisterWoIstDesignProperties();
            $this->RegisterVisualizationThemeProperties();
            $this->RegisterIPSViewStyleProperties();
            $this->RegisterPropertyInteger('StaleAfter', 900);
            $this->RegisterWoIstAccounts();
            $this->RegisterWoIstGeocoding();
            $this->RegisterPropertyString('TileURL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
            $this->RegisterPropertyString('TileAttribution', '© OpenStreetMap contributors');
            $this->SetVisualizationType(1);
        }, true);
    }

    /** Activates the HTML tile and reads the account cache without an Apple request. */
    public function ApplyChanges(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->SetVisualizationType(1);
            $this->RegisterIPSViewStyleMediaMessages();
            $this->MaintainIPSViewHTMLVariable('IPSViewWoIst', $this->Translate('WoIst for IPSView'), 100, '', false);
            $this->ConfigureWoIstIPSViewTransport();
            $this->CloudDebugTimer('UpdateAccounts', $this->configurationValid() && count($this->WoIstAccountIDs()) > ($this->HasParent() ? 1 : 0) ? 5000 : 0, __FUNCTION__, __LINE__);
            if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
                $this->ClearReverseGeocodingCache();
            }
            $this->Update();
            if ($this->IsIPSViewHTMLPageEnabled()) {
                $this->RegenerateIPSViewHTMLPages();
            }
        }, true);
    }

    /** Refreshes the active IPSView media style without replacing the map page. */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($TimeStamp, $SenderID, $Message, $Data): void
        {
            parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
            if ($this->IsIPSViewStyleMediaUpdate($SenderID, $Message)) {
                $this->Update();
            }
        }, true);
    }

    /** @return string Official SDK descriptor for sharing an account splitter. */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** @return string Static map settings without any Apple request. */
    public function GetConfigurationForm(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            $form = $this->LoadConfigurationForm();
            foreach ($form['elements'] as &$element) {
                if (($element['name'] ?? '') === 'AddressResolution') {
                    foreach ($element['items'] as &$item) {
                        if (($item['name'] ?? '') === 'GeocodingConfigurationWarning') {
                            $item['visible'] = $this->ReadPropertyBoolean('EnableAddressResolution') && $this->WoIstGeocodingConfiguration() === null;
                        }
                        if (($item['name'] ?? '') === 'GeoapifyUsage') {
                            $item['visible'] = $this->ReadPropertyBoolean('EnableAddressResolution') && $this->ReadPropertyString('AddressProvider') === 'geoapify';
                            $limit = $this->ReadPropertyInteger('GeoapifyDailyLimit');
                            if ($limit >= 0 && $limit <= 1000000) {
                                $usage = $this->GeoapifyDailyUsage($limit);
                                $item['caption'] = $usage['available'] && $usage['used'] !== PHP_INT_MAX ? sprintf($this->Translate('Geoapify requests today (UTC): %d / %d'), $usage['used'], $limit)
                                    : $this->Translate('Geoapify usage counter unavailable. New requests are blocked until the next UTC day.');
                            }
                        }
                    }
                    unset($item);
                }
            }
            unset($element);
            return $this->EncodeConfigurationForm($this->WoIstDesignForm($form));
        });
    }

    /** @return bool True if an authenticated cached account inventory was available. */
    public function Update(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            return $this->publish($this->request('GetDevices'));
        });
    }

    /** @return bool Availability after one explicit refresh per account, honoring each cooldown. */
    public function RefreshDevices(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            return $this->publish($this->request('RefreshDevices'));
        });
    }

    /** @return string Sanitized map bootstrap; reads only explicitly assigned account caches. */
    public function GetState(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->encode($this->bootstrap($this->request('GetDevices')));
        });
    }

    /** @return string Initial HTML tile with bundled assets and escaped device data. */
    public function GetVisualizationTile(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->renderPage(false, $this->bootstrap($this->request('GetDevices')));
        });
    }

    /** @return string Standalone HTML shell; data updates are delivered separately through the cache endpoint. */
    public function GetIPSViewHTML(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            $this->ConfigureWoIstIPSViewTransport();
            $options = $this->WoIstIPSViewTransportOptions();
            return $this->RenderIPSViewFrame([
                'url'   => isset($options['url']) ? $options['url'] . '/page' : '',
                'token' => $options['token'] ?? '',
                'title' => $this->Translate('WoIst'),
                'error' => $this->Translate('Configure the Symcon server URL for IPSView live updates.')
            ]);
        });
    }

    /** @param string $JSONString Normalized account notification. @return string Empty acknowledgement. */
    public function ReceiveData(string $JSONString): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSONString): string
        {
            try {
                $data = $this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA);
            } catch (Throwable) {
                $data = $this->unavailable();
            }
            $this->publish($this->request('GetDevices', $data));
            return '';
        });
    }

    /** @param string $Ident Allowlisted HTML-SDK/form action. @param mixed $Value Unused action payload. */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            if ($this->HandleIPSViewHTMLPageAction($Ident, $Value)) {
                return;
            }
            match ($Ident) {
                'GetState', 'Update' => $this->Update(),
                'RefreshDevices'     => $this->RefreshDevices(),
                default              => throw new InvalidArgumentException('Unsupported map action.')
            };
        }, true);
    }

    public function Destroy(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        { parent::Destroy(); }, true);
    }

    /** Symcon WebHooks evaluate classes with a synthetic reflection filename. */
    protected function VisualizationAssetDirectory(): string
    {
        return __DIR__ . '/visualization';
    }

    private function renderPage(bool $ipsView, array $bootstrap, bool $publicShell = false): string
    {
        $assets = $this->OpenFreeMapAssets('/hook/' . $this->WoIstIPSViewHookName() . '/assets/maplibre-gl.js');
        $bootstrap['mode'] = $ipsView ? 'ipsview' : 'symcon';
        $bootstrap['options']['viewStateKey'] = 'ICWI:' . $this->InstanceID . ':' . ($ipsView ? 'ipsview' : IPS_GetInstance($this->InstanceID)['ConnectionID']);
        $bootstrap['options']['openFreeMap'] = $publicShell ? null : $this->WoIstOpenFreeMap($ipsView);
        $bootstrap['options']['design'] = $this->WoIstDesign($ipsView);
        $bootstrap['options'] = array_replace($bootstrap['options'], $this->ResponsiveVisualizationLayoutOptions(!$ipsView));
        if ($ipsView && $this->IsIPSViewHTMLPageEnabled()) {
            $bootstrap['options']['ipsViewTransport'] = [
                'url' => '/hook/' . $this->WoIstIPSViewHookName(), 'token' => '', 'fragmentToken' => true, 'pollInterval' => 5000
            ];
        }
        return $this->RenderVisualizationHTMLPage($ipsView, [
            'title'              => $this->Translate('WoIst'),
            'visualizationTheme' => $this->VisualizationThemeCSS(),
            'ipsViewStyle'       => $this->WoIstThemeCSS(true),
            'state'              => $bootstrap['state'],
            'translations'       => $bootstrap['labels'],
            'options'            => $bootstrap['options'],
            'replacements'       => [
                '{{BOOTSTRAP}}'      => $this->encode($bootstrap),
                '{{VECTOR_CSS}}'     => $assets['css'],
                '{{VECTOR_JS}}'      => $assets['script'],
                '{{LEAFLET_CSS}}'    => (string) file_get_contents(__DIR__ . '/../libs/vendor/leaflet/leaflet.css'),
                '{{LEAFLET_JS}}'     => (string) file_get_contents(__DIR__ . '/../libs/vendor/leaflet/leaflet.js'),
                '{{RESPONSIVE_CSS}}' => $this->ResponsiveVisualizationCSS('#woist-app', 'woist-map', true),
                '{{DESIGN_CSS}}'     => $this->WoIstDesignCSS($ipsView)
            ]
        ]);
    }

    /** Public HTTP shell contains no account data, provider credentials or transport token. */
    private function renderIPSViewHTTPShell(): string
    {
        $bootstrap = $this->bootstrap($this->unavailable());
        $bootstrap['options'] = ['maxZoom' => 19];
        return $this->renderPage(true, $bootstrap, true);
    }

    private function request(string $command, ?array $notification = null): array
    {
        if (!$this->configurationValid()) {
            return $this->unavailable('Invalid map configuration.');
        }
        if (!$this->HasWoIstAccounts()) {
            return $this->unavailable('No iCloud account connected.');
        }
        try {
            return $this->CollectWoIstAccounts($command, $notification);
        } catch (Throwable) {
            // Never expose account exceptions or unprocessed transport responses.
        }
        return $this->unavailable();
    }

    private function publish(array $data): bool
    {
        $bootstrap = $this->bootstrap($data);
        $state = $bootstrap['state'];
        $this->CloudDebugStatus(!$this->configurationValid() ? 203 : (!$this->HasWoIstAccounts() ? 201 : ($state['success'] ? IS_ACTIVE : 202)), __FUNCTION__, __LINE__);
        $this->UpdateVisualizationValue($this->encode($bootstrap));
        $this->PublishWoIstIPSViewState($bootstrap);
        $this->ScheduleWoIstGeocoding($state);
        $this->NotifyAddressDevices();
        return $state['success'];
    }

    private function bootstrap(array $data): array
    {
        if (!$this->configurationValid() || !$this->HasWoIstAccounts()) {
            $data = $this->unavailable(!$this->configurationValid() ? 'Invalid map configuration.' : 'No iCloud account connected.');
        }
        $updated = is_int($data['updatedAt'] ?? null) && $data['updatedAt'] > 0 ? $data['updatedAt'] : 0;
        $success = ($data['success'] ?? false) === true && ($data['state'] ?? '') === 'authenticated' && $updated > 0;
        $devices = [];
        $seen = [];
        foreach (is_array($data['devices'] ?? null) ? $data['devices'] : [] as $device) {
            if (!is_array($device) || !is_string($device['id'] ?? null) || $device['id'] === '' || isset($seen[$device['id']])) {
                continue;
            }
            $seen[$device['id']] = true;
            $location = null;
            $raw = $device['location'] ?? null;
            if (is_array($raw) && $this->finiteNumber($raw['latitude'] ?? null) && $this->finiteNumber($raw['longitude'] ?? null)
                && abs((float) $raw['latitude']) <= 90 && abs((float) $raw['longitude']) <= 180
                && is_int($raw['timestamp'] ?? null) && $raw['timestamp'] > 0 && $raw['timestamp'] <= time() + 300) {
                $accuracy = $raw['horizontalAccuracy'] ?? null;
                $location = ['latitude'  => (float) $raw['latitude'], 'longitude' => (float) $raw['longitude'],
                    'horizontalAccuracy' => $this->finiteNumber($accuracy) && $accuracy > 0 ? (float) $accuracy : null,
                    'timestamp'          => $raw['timestamp'], 'isOld' => ($raw['isOld'] ?? false) === true];
            }
            $battery = $device['batteryLevel'] ?? null;
            $devices[] = [
                'id'           => $device['id'], 'name' => is_string($device['name'] ?? null) ? $device['name'] : '',
                'model'        => is_string($device['model'] ?? null) ? $device['model'] : '',
                'online'       => ($device['deviceStatus'] ?? 0) === 200,
                'accountId'    => $device['accountId'] ?? '', 'accountName' => $device['accountName'] ?? '', 'accountAvailable' => $device['accountAvailable'] ?? $success,
                'batteryLevel' => $this->finiteNumber($battery) && $battery >= 0 && $battery <= 1 ? (float) $battery : null,
                'location'     => $location, 'address' => $this->WoIstCachedAddress($location), 'addressNotice' => $this->WoIstAddressNotice($location)
            ];
        }
        $labels = [];
        foreach ([
            'title'                  => 'WoIst', 'refresh' => 'Refresh from Apple', 'fit' => 'Show all positions', 'devices' => 'Devices',
            'noDevices'              => 'No devices available.', 'noLocation' => 'No position available', 'stale' => 'Last known position',
            'current'                => 'Recent position', 'offline' => 'Offline', 'battery' => 'Battery', 'updated' => 'Account updated',
            'accounts'               => 'Account filter', 'allAccounts' => 'All accounts', 'unavailableAccount' => 'Unavailable account',
            'accuracy'               => 'Accuracy', 'mapUnavailable' => 'Map tiles unavailable. Device data is still shown.',
            'loadError'              => 'The map could not be loaded.', 'refreshing' => 'Refreshing…',
            'transportError'         => 'Live updates unavailable. Retrying…',
            'transportConfiguration' => 'Configure the Symcon server URL for IPSView live updates.',
            'cacheError'             => 'Account unavailable. Positions shown are last known.', 'ago' => 'ago', 'unknown' => 'Unknown'
        ] as $key => $text) {
            $labels[$key] = $this->Translate($text);
        }
        return [
            'state' => ['success' => $success, 'accountState' => is_string($data['state'] ?? null) ? $data['state'] : 'error',
                'error'           => is_string($data['error'] ?? null) ? $data['error'] : '', 'updatedAt' => $updated,
                'staleAfter'      => max(60, min(86400, $this->ReadPropertyInteger('StaleAfter'))), 'devices' => $devices,
                'geocoding'       => $this->WoIstGeocodingStatus(), 'accounts' => $data['accounts'] ?? []],
            'labels'  => $labels,
            'options' => [...$this->ResponsiveVisualizationLayoutOptions(), 'design' => $this->WoIstDesign(false), 'openFreeMap' => $this->WoIstOpenFreeMap(false), 'maxZoom' => 19]
        ];
    }

    private function finiteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private function configurationValid(): bool
    {
        try {
            $this->WoIstAccountIDs();
            return $this->ReadPropertyInteger('StaleAfter') >= 60 && $this->ReadPropertyInteger('StaleAfter') <= 86400;
        } catch (Throwable) {
            return false;
        }
    }

    private function unavailable(string $error = 'Account data unavailable.'): array
    {
        $this->CloudDebugProblem($error, __FUNCTION__, __LINE__);
        return ['success' => false, 'state' => 'error', 'updatedAt' => 0, 'devices' => [], 'error' => $this->Translate($error)];
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
