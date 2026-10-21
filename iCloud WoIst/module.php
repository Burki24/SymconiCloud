<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/VisualizationAssetHelper.php';
require_once __DIR__ . '/../libs/helper/VisualizationThemeHelper.php';
require_once __DIR__ . '/../libs/helper/ResponsiveVisualizationHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewHTMLPageHelper.php';
require_once __DIR__ . '/../libs/WoIstDesign.php';

/** One account's device positions, rendered through the authenticated HTML SDK. */
class iCloudWoIst extends IPSModuleStrict
{
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\VisualizationAssetHelper;
    use \Burki24\SymconModuleHelper\VisualizationThemeHelper;
    use \Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;
    use \Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
    use \SymconiCloud\WoIstDesign;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    /** Registers only map settings; credentials and refresh timers belong to the account. */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterIPSViewHTMLPageProperties();
        $this->RegisterWoIstDesignProperties();
        $this->RegisterPropertyInteger('StaleAfter', 900);
        $this->RegisterPropertyString('TileURL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
        $this->RegisterPropertyString('TileAttribution', 'Â© OpenStreetMap contributors');
        $this->SetVisualizationType(1);
    }

    /** Activates the HTML tile and reads the account cache without an Apple request. */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetVisualizationType(1);
        $this->MaintainIPSViewHTMLVariable('IPSViewWoIst', $this->Translate('WoIst for IPSView'), 100, '', false);
        $this->Update();
    }

    /** @return string Official SDK descriptor for sharing an account splitter. */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    /** @return string Static map settings without any Apple request. */
    public function GetConfigurationForm(): string
    {
        return $this->EncodeConfigurationForm($this->WoIstDesignForm($this->LoadConfigurationForm()));
    }

    /** @return bool True if an authenticated cached account inventory was available. */
    public function Update(): bool
    {
        return $this->publish($this->request('GetDevices'));
    }

    /** @return bool Result of one explicit account refresh, honoring the account's cooldown. */
    public function RefreshDevices(): bool
    {
        return $this->publish($this->request('RefreshDevices'));
    }

    /** @return string Sanitized map bootstrap; reads only the connected account cache. */
    public function GetState(): string
    {
        return $this->encode($this->bootstrap($this->request('GetDevices')));
    }

    /** @return string Initial HTML tile with bundled assets and escaped device data. */
    public function GetVisualizationTile(): string
    {
        return $this->renderPage(false, $this->bootstrap($this->request('GetDevices')));
    }

    /** @return string Standalone HTML output refreshed by the managed WebContent variable. */
    public function GetIPSViewHTML(): string
    {
        return $this->renderPage(true, $this->bootstrap($this->request('GetDevices')));
    }

    /** @param string $JSONString Normalized account notification. @return string Empty acknowledgement. */
    public function ReceiveData(string $JSONString): string
    {
        try {
            $data = $this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA);
        } catch (Throwable) {
            $data = $this->unavailable();
        }
        $this->publish($data);
        return '';
    }

    /** @param string $Ident Allowlisted HTML-SDK/form action. @param mixed $Value Unused action payload. */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->HandleIPSViewHTMLPageAction($Ident, $Value)) {
            return;
        }
        match ($Ident) {
            'GetState', 'Update' => $this->Update(),
            'RefreshDevices'     => $this->RefreshDevices(),
            default              => throw new InvalidArgumentException('Unsupported map action.')
        };
    }

    private function renderPage(bool $ipsView, array $bootstrap): string
    {
        $bootstrap['mode'] = $ipsView ? 'ipsview' : 'symcon';
        $bootstrap['options']['design'] = $this->WoIstDesign($ipsView);
        return $this->RenderVisualizationHTMLPage($ipsView, [
            'title'              => $this->Translate('WoIst'),
            'visualizationTheme' => $this->VisualizationThemeCSS(),
            'ipsViewStyle'       => $this->VisualizationThemeCSS(),
            'state'              => $bootstrap['state'],
            'translations'       => $bootstrap['labels'],
            'options'            => $bootstrap['options'],
            'replacements'       => [
                '{{BOOTSTRAP}}'      => $this->encode($bootstrap),
                '{{LEAFLET_CSS}}'    => (string) file_get_contents(__DIR__ . '/../libs/vendor/leaflet/leaflet.css'),
                '{{LEAFLET_JS}}'     => (string) file_get_contents(__DIR__ . '/../libs/vendor/leaflet/leaflet.js'),
                '{{RESPONSIVE_CSS}}' => $this->ResponsiveVisualizationCSS('#woist-app', 'woist-map'),
                '{{DESIGN_CSS}}'     => $this->WoIstDesignCSS($ipsView)
            ]
        ]);
    }

    private function request(string $command): array
    {
        if (!$this->configurationValid()) {
            return $this->unavailable('Invalid map configuration.');
        }
        if (!$this->HasParent()) {
            return $this->unavailable('No iCloud account connected.');
        }
        try {
            $reply = $this->SendDataToParent($this->EncodeDataFlowMessage(self::REQUEST_DATA, ['Command' => $command]));
            $data = json_decode($reply, true, 64, JSON_THROW_ON_ERROR);
            if (is_array($data) && is_bool($data['success'] ?? null) && is_array($data['devices'] ?? null) && array_is_list($data['devices'])) {
                return $data;
            }
        } catch (Throwable) {
            // Never expose account exceptions or unprocessed transport responses.
        }
        return $this->unavailable();
    }

    private function publish(array $data): bool
    {
        $bootstrap = $this->bootstrap($data);
        $state = $bootstrap['state'];
        $this->SetStatus(!$this->configurationValid() ? 203 : (!$this->HasParent() ? 201 : ($state['success'] ? IS_ACTIVE : 202)));
        $this->UpdateVisualizationValue($this->encode($bootstrap));
        if ($this->IsIPSViewHTMLPageEnabled()) {
            $html = $this->renderPage(true, $bootstrap);
            $variableID = $this->IPSViewHTMLVariableID('IPSViewWoIst');
            if ($variableID > 0 && GetValueString($variableID) !== $html) {
                $this->UpdateIPSViewHTMLVariable('IPSViewWoIst', $html);
            }
        }
        return $state['success'];
    }

    private function bootstrap(array $data): array
    {
        if (!$this->configurationValid() || !$this->HasParent()) {
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
                    'horizontalAccuracy' => $this->finiteNumber($accuracy) && $accuracy >= 0 ? (float) $accuracy : null,
                    'timestamp'          => $raw['timestamp'], 'isOld' => ($raw['isOld'] ?? false) === true];
            }
            $battery = $device['batteryLevel'] ?? null;
            $devices[] = [
                'id'           => $device['id'], 'name' => is_string($device['name'] ?? null) ? $device['name'] : '',
                'model'        => is_string($device['model'] ?? null) ? $device['model'] : '',
                'online'       => ($device['deviceStatus'] ?? 0) === 200,
                'batteryLevel' => $this->finiteNumber($battery) && $battery >= 0 && $battery <= 1 ? (float) $battery : null,
                'location'     => $location
            ];
        }
        $labels = [];
        foreach ([
            'title'      => 'WoIst', 'refresh' => 'Refresh from Apple', 'fit' => 'Show all positions', 'devices' => 'Devices',
            'noDevices'  => 'No devices available.', 'noLocation' => 'No position available', 'stale' => 'Last known position',
            'current'    => 'Recent position', 'offline' => 'Offline', 'battery' => 'Battery', 'updated' => 'Account updated',
            'accuracy'   => 'Accuracy', 'mapUnavailable' => 'Map tiles unavailable. Device data is still shown.',
            'loadError'  => 'The map could not be loaded.', 'refreshing' => 'Refreshingâ€¦',
            'cacheError' => 'Account unavailable. Positions shown are last known.', 'ago' => 'ago', 'unknown' => 'Unknown'
        ] as $key => $text) {
            $labels[$key] = $this->Translate($text);
        }
        return [
            'state' => ['success' => $success, 'accountState' => is_string($data['state'] ?? null) ? $data['state'] : 'error',
                'error'           => is_string($data['error'] ?? null) ? $data['error'] : '', 'updatedAt' => $updated,
                'staleAfter'      => max(60, min(86400, $this->ReadPropertyInteger('StaleAfter'))), 'devices' => $devices],
            'labels'  => $labels,
            'options' => ['design'  => $this->WoIstDesign(false), 'tileUrl' => $this->configurationValid() ? $this->ReadPropertyString('TileURL') : '',
                'attribution'       => $this->configurationValid() ? $this->ReadPropertyString('TileAttribution') : '', 'maxZoom' => 19]
        ];
    }

    private function finiteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    private function configurationValid(): bool
    {
        $url = $this->ReadPropertyString('TileURL');
        $parts = parse_url($url);
        return $this->ReadPropertyInteger('StaleAfter') >= 60 && $this->ReadPropertyInteger('StaleAfter') <= 86400
            && is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment'])
            && !preg_match('/[\x00-\x20\x7f]/', $url) && str_contains($url, '{z}') && str_contains($url, '{x}') && str_contains($url, '{y}')
            && trim($this->ReadPropertyString('TileAttribution')) !== '';
    }

    private function unavailable(string $error = 'Account data unavailable.'): array
    {
        return ['success' => false, 'state' => 'error', 'updatedAt' => 0, 'devices' => [], 'error' => $this->Translate($error)];
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
