<?php

declare(strict_types=1);

namespace ICloudServices;

/** HTTP visualization setup and frame rendering shared within this library. */
trait IPSViewFrame
{
    private const VISUALIZATION_HOOK_WARNING = 'The visualization WebHook is unavailable. Save the configuration to retry registration.';
    private const FORMER_VISUALIZATION_HOOK_PREFIX = 'SymconiCloud';

    /** Registers the volatile route during Create, with a retry on the next ApplyChanges. */
    protected function EnsureVisualizationHook(string $path, ?string $formerSuffix = null): void
    {
        if ($this->GetBuffer('VisualizationHookRegistered') === $path) {
            return;
        }
        if ($this->RegisterHook($path)) {
            $this->SetBuffer('VisualizationHookRegistered', $path);
            if ($formerSuffix !== null) {
                $this->UnregisterHook(self::FORMER_VISUALIZATION_HOOK_PREFIX . $formerSuffix . $this->InstanceID);
            }
            return;
        }
        $this->SetBuffer('VisualizationHookRegistered', '');
        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Der WebHook für die Visualisierung konnte nicht registriert werden. Die Instanz bleibt konfigurierbar und versucht die Registrierung beim nächsten Übernehmen erneut.');
    }

    protected function IsVisualizationHookAvailable(): bool
    {
        return $this->GetBuffer('VisualizationHookRegistered') !== '';
    }

    protected function VisualizationHookFormItems(): array
    {
        return $this->IsVisualizationHookAvailable() ? [] : [
            ['type' => 'Label', 'caption' => $this->Translate(self::VISUALIZATION_HOOK_WARNING)]
        ];
    }

    protected function VisualizationHookUnavailableHTML(): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head><body><p role="status">'
            . htmlspecialchars($this->Translate(self::VISUALIZATION_HOOK_WARNING), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></body></html>';
    }

    /** Uses the central origin, interaction and JSON helpers without copying their mechanisms. */
    protected function RenderIPSViewFrame(array $configuration): string
    {
        return strtr((string) file_get_contents(__DIR__ . '/visualization/ipsview-page.html'), [
            '{{LOADER_CONFIG}}' => $this->EncodeVisualizationHTMLJSON($configuration),
            '{{LOADER_SCRIPT}}' => $this->VisualizationInteractionScript() . "\n" . $this->VisualizationHTTPOriginScript()
                . "\n" . file_get_contents(__DIR__ . '/visualization/ipsview-page.js')
        ]);
    }
}
