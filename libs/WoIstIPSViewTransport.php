<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Per-instance cache transport with one explicit, authenticated refresh operation. */
trait WoIstIPSViewTransport
{
    /** Serves public assets, authenticated cache data, or an explicitly requested account refresh. */
    protected function ProcessHookData(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-WoIst-Token');
        $path = '/hook/' . $this->WoIstIPSViewHookName();
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $page = $uri === $path . '/page';
        $refresh = $uri === $path . '/refresh';
        // The renderer is public library code, usable by native tiles even
        // when the optional IPSView output is disabled. No cache data is read.
        if ($uri === $path . '/assets/maplibre-gl.js') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                header('Allow: GET');
                $this->SendPlainTextResponse(405, 'Method not allowed.');
                return;
            }
            header('Content-Encoding: gzip');
            $this->SendResponse(200, 'application/javascript', $this->OpenFreeMapRendererAsset());
            return;
        }

        if (($uri !== $path && !$page && !$refresh) || !$this->IsIPSViewHTMLPageEnabled()
            || !$this->WoIstIPSViewServerURLValid() || !$this->HasWoIstAccounts()
            || $this->ReadAttributeInteger('IPSViewTransportConnection') !== IPS_GetInstance($this->InstanceID)['ConnectionID']) {
            $this->SendPlainTextResponse(404, 'Not available.');
            return;
        }
        if ($page) {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                header('Allow: GET');
                $this->SendPlainTextResponse(405, 'Method not allowed.');
                return;
            }
            $html = $this->renderIPSViewHTTPShell();
            if ($html === '') {
                $this->SendPlainTextResponse(503, 'Map document unavailable.');
                return;
            }
            header('Referrer-Policy: origin');
            $this->SendResponse(200, 'text/html', $html);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            $this->SendPlainTextResponse(204, '');
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST, OPTIONS');
            $this->SendPlainTextResponse(405, 'Method not allowed.');
            return;
        }
        $token = $this->ReadAttributeString('IPSViewTransportToken');
        $provided = $_SERVER['HTTP_X_WOIST_TOKEN'] ?? '';
        if ($token === '' || !is_string($provided) || !hash_equals($token, $provided)) {
            $this->SendPlainTextResponse(403, 'Access denied.');
            return;
        }
        if ($refresh) {
            // RefreshDevices delegates authentication, serialization and cooldown
            // to the existing account module; no generic remote action is exposed.
            $this->RefreshDevices();
        }
        $state = $this->ReadAttributeString('IPSViewTransportState');
        if ($state === '') {
            $this->SendPlainTextResponse(503, 'State unavailable.');
            return;
        }
        $this->SendResponse(200, 'application/json', $state);
    }
    private function RegisterWoIstIPSViewTransport(): void
    {
        $this->RegisterPropertyString('IPSViewServerURL', '');
        $this->RegisterAttributeString('IPSViewTransportToken', '');
        $this->RegisterAttributeString('IPSViewTransportState', '');
        $this->RegisterAttributeInteger('IPSViewTransportConnection', 0);
        $this->RegisterAttributeString('IPSViewTransportAccounts', '');
        // Volatile hooks belong to Create and must survive output toggles.
        // ProcessHookData enforces activation and authorization independently.
        if (!$this->RegisterHook($this->WoIstIPSViewHookName())) {
            throw new \RuntimeException('Could not register the WoIst WebHook.');
        }
    }

    private function WoIstIPSViewHookName(): string
    {
        return 'SymconiCloudWoIst' . $this->InstanceID;
    }

    private function WoIstIPSViewServerURLValid(): bool
    {
        $url = $this->ReadPropertyString('IPSViewServerURL');
        if ($url === '') {
            return true;
        }
        $parts = parse_url($url);
        return is_array($parts) && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && ($parts['path'] ?? '') === '' && !preg_match('/[\x00-\x20\x7f]/', $url);
    }

    private function ConfigureWoIstIPSViewTransport(): void
    {
        $connection = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $accounts = $this->configurationValid() ? hash('sha256', json_encode($this->WoIstAccountIDs(), JSON_THROW_ON_ERROR)) : '';
        $enabled = $this->IsIPSViewHTMLPageEnabled() && $this->WoIstIPSViewServerURLValid();
        if (!$enabled) {
            $this->WriteAttributeString('IPSViewTransportToken', '');
            $this->WriteAttributeString('IPSViewTransportState', '');
        } else {
            if ($this->ReadAttributeString('IPSViewTransportToken') === ''
                || $this->ReadAttributeInteger('IPSViewTransportConnection') !== $connection
                || $this->ReadAttributeString('IPSViewTransportAccounts') !== $accounts) {
                $this->WriteAttributeString('IPSViewTransportToken', bin2hex(random_bytes(32)));
                $this->WriteAttributeString('IPSViewTransportState', '');
            }
        }
        $this->WriteAttributeInteger('IPSViewTransportConnection', $connection);
        $this->WriteAttributeString('IPSViewTransportAccounts', $accounts);
    }

    private function WoIstIPSViewTransportOptions(): array
    {
        if (!$this->WoIstIPSViewServerURLValid()) {
            return [];
        }
        return [
            'url'          => $this->ReadPropertyString('IPSViewServerURL') . '/hook/' . $this->WoIstIPSViewHookName(),
            'token'        => $this->ReadAttributeString('IPSViewTransportToken'),
            'pollInterval' => 5000
        ];
    }

    private function PublishWoIstIPSViewState(array $bootstrap): void
    {
        $this->ConfigureWoIstIPSViewTransport();
        if (!$this->IsIPSViewHTMLPageEnabled() || !$this->WoIstIPSViewServerURLValid()) {
            return;
        }
        $bootstrap['mode'] = 'ipsview';
        $bootstrap['options'] = array_replace($bootstrap['options'], $this->ResponsiveVisualizationLayoutOptions(false));
        $bootstrap['options']['design'] = $this->WoIstDesign(true);
        $bootstrap['options']['openFreeMap'] = $this->WoIstOpenFreeMap(true);
        $this->WriteAttributeString('IPSViewTransportState', $this->encode($bootstrap));
    }
}
