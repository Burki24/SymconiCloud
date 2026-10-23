<?php

declare(strict_types=1);

namespace SymconiCloud;

/** HTTP widget embedding shared by WoIst and Presence within this library. */
trait IPSViewFrame
{
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
