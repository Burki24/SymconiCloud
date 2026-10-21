<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Read-only, per-instance transport for the persistent IPSView map document. */
trait WoIstIPSViewTransport
{
    /** Serves a public HTTP shell or authenticated sanitized cache; never queries the account. */
    protected function ProcessHookData(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-WoIst-Token');
        $path = '/hook/' . $this->WoIstIPSViewHookName();
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $page = $uri === $path . '/page';
        if (($uri !== $path && !$page) || !$this->IsIPSViewHTMLPageEnabled()
            || !$this->WoIstIPSViewServerURLValid() || !$this->HasParent()
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
            header('Referrer-Policy: origin');
            $this->SendResponse(200, 'text/html', $this->renderIPSViewHTTPShell());
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
        if ($this->IsIPSViewHTMLPageEnabled() && $this->WoIstIPSViewServerURLValid()) {
            $this->RegisterHook($this->WoIstIPSViewHookName());
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
        $enabled = $this->IsIPSViewHTMLPageEnabled() && $this->WoIstIPSViewServerURLValid();
        if (!$enabled) {
            $this->UnregisterHook($this->WoIstIPSViewHookName());
            $this->WriteAttributeString('IPSViewTransportToken', '');
            $this->WriteAttributeString('IPSViewTransportState', '');
        } else {
            if ($this->ReadAttributeString('IPSViewTransportToken') === ''
                || $this->ReadAttributeInteger('IPSViewTransportConnection') !== $connection) {
                $this->WriteAttributeString('IPSViewTransportToken', bin2hex(random_bytes(32)));
                $this->WriteAttributeString('IPSViewTransportState', '');
            }
            $this->RegisterHook($this->WoIstIPSViewHookName());
        }
        $this->WriteAttributeInteger('IPSViewTransportConnection', $connection);
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
        $bootstrap['options']['design'] = $this->WoIstDesign(true);
        $this->WriteAttributeString('IPSViewTransportState', $this->encode($bootstrap));
    }
}
