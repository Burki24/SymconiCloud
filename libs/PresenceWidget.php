<?php

declare(strict_types=1);

namespace SymconiCloud;

use RuntimeException;
use Throwable;

require_once __DIR__ . '/helper/IPSViewHTMLPageHelper.php';
require_once __DIR__ . '/helper/VisualizationAssetHelper.php';
require_once __DIR__ . '/helper/VisualizationThemeConfigurationHelper.php';
require_once __DIR__ . '/helper/IPSViewStyleConfigurationHelper.php';
require_once __DIR__ . '/helper/ResponsiveVisualizationHelper.php';
require_once __DIR__ . '/helper/HttpResponseHelper.php';
require_once __DIR__ . '/IPSViewFrame.php';

/**
 * SymconiCloud presence widget layout, images and cached IPSView output.
 * Uses the existing shared theme, style, HTTP, interaction and layout helpers.
 * Remains local until used by at least two different libraries.
 */
trait PresenceWidget
{
    use IPSViewFrame;

    private const PRESENCE_WIDGET_TEXT = [
        'tile'                 => 'Tile designer', 'ipsview' => 'IPSView designer', 'layout' => 'Layout and content',
        'colors'               => 'Colors', 'style' => 'Colors and typography', 'font' => 'Font size in pixels',
        'photos'               => 'Show device pictures', 'details' => 'Show details', 'size' => 'Picture size in pixels',
        'radius'               => 'Corner radius in pixels', 'spacing' => 'Spacing in pixels',
        'url'                  => 'Symcon server URL for IPSView (optional)',
        'urlHint'              => 'Normally the server address is detected automatically. Only enter an HTTP(S) origin without a path if the client cannot provide it.',
        'connection'           => 'Live updates unavailable. Retrying...', 'empty' => 'No devices selected.',
        'loader'               => 'Unable to open the IPSView widget. Check the server URL and apply the instance configuration.',
        'imageSizeError'       => 'Picture exceeds 128 KiB. Choose a smaller file; this picture will not be displayed.',
        'imageFormatError'     => 'Invalid picture. Choose a PNG, JPEG, GIF or WebP file; this picture will not be displayed.',
        'imageDimensionsError' => 'Picture exceeds 1024 x 1024 pixels. Reduce its dimensions; this picture will not be displayed.',
        'imageHint'            => 'Optional pictures: choose a Symcon image medium or a file from the computer running the console for each device. Use PNG, JPEG, GIF or WebP, at most 128 KiB and 1024 x 1024 pixels. Invalid pictures use initials.'
    ];

    /** Call from Create(); volatile hook registration must survive output toggles. */
    protected function RegisterStatusWidget(): void
    {
        $this->RegisterIPSViewHTMLPageProperties();
        $this->RegisterVisualizationThemeProperties();
        $this->RegisterIPSViewStyleProperties();
        $this->RegisterPropertyString('IPSViewServerURL', '');
        foreach (['Tile', 'IPSView'] as $prefix) {
            $this->RegisterPropertyBoolean($prefix . 'WidgetPhotos', true);
            $this->RegisterPropertyBoolean($prefix . 'WidgetDetails', true);
            $this->RegisterPropertyInteger($prefix . 'WidgetPictureSize', 48);
            $this->RegisterPropertyInteger($prefix . 'WidgetRadius', 12);
            $this->RegisterPropertyInteger($prefix . 'WidgetSpacing', 10);
        }
        $this->RegisterPropertyInteger('TileWidgetFontSize', 14);
        $this->RegisterAttributeString('StatusWidgetState', '{}');
        $this->RegisterAttributeString('StatusWidgetToken', '');
        $this->RegisterAttributeInteger('StatusWidgetConnection', 0);
        $this->SetVisualizationType(1);
        if (!$this->RegisterHook($this->StatusWidgetHookName())) {
            throw new RuntimeException('Could not register the status widget WebHook.');
        }
    }

    /** Call from ApplyChanges before publishing data; never changes domain state. */
    protected function ConfigureStatusWidget(string $ident, string $caption): void
    {
        $this->SetVisualizationType(1);
        $this->RegisterIPSViewStyleMediaMessages();
        $this->MaintainIPSViewHTMLVariable($ident, $caption, 2000, '', false);
        $connection = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if (!$this->IsIPSViewHTMLPageEnabled() || !$this->StatusWidgetServerURLValid()) {
            $this->WriteAttributeString('StatusWidgetToken', '');
        } elseif ($this->ReadAttributeString('StatusWidgetToken') === '' || $connection !== $this->ReadAttributeInteger('StatusWidgetConnection')) {
            $this->WriteAttributeString('StatusWidgetToken', bin2hex(random_bytes(32)));
        }
        $this->WriteAttributeInteger('StatusWidgetConnection', $connection);
    }

    /** Shared independent designers; semantic Tile colors and the complete IPSView style editor. */
    protected function StatusWidgetForm(array $form): array
    {
        foreach (['Tile', 'IPSView'] as $prefix) {
            $items = $prefix === 'IPSView' ? $this->IPSViewHTMLPageFormItems() : [];
            if ($prefix === 'IPSView') {
                $items[] = ['type' => 'ValidationTextBox', 'name' => 'IPSViewServerURL', 'caption' => $this->StatusWidgetText('url')];
                $items[] = ['type' => 'Label', 'caption' => $this->StatusWidgetText('urlHint')];
            }
            $items[] = ['type' => 'ExpansionPanel', 'caption' => $this->StatusWidgetText($prefix === 'Tile' ? 'colors' : 'style'),
                'items'        => $prefix === 'Tile' ? $this->VisualizationThemeFormItems() : $this->IPSViewStyleFormItems()];
            $layout = [];
            if ($prefix === 'Tile') {
                $layout[] = ['type' => 'NumberSpinner', 'name' => 'TileWidgetFontSize', 'caption' => $this->StatusWidgetText('font'), 'minimum' => 10, 'maximum' => 28];
            }
            foreach (['Photos' => 'photos', 'Details' => 'details'] as $property => $key) {
                $layout[] = ['type' => 'CheckBox', 'name' => $prefix . 'Widget' . $property, 'caption' => $this->StatusWidgetText($key)];
            }
            foreach (['PictureSize' => ['size', 24, 96], 'Radius' => ['radius', 0, 32], 'Spacing' => ['spacing', 4, 24]] as $property => [$key, $min, $max]) {
                $layout[] = ['type' => 'NumberSpinner', 'name' => $prefix . 'Widget' . $property, 'caption' => $this->StatusWidgetText($key), 'minimum' => $min, 'maximum' => $max];
            }
            $items[] = ['type' => 'ExpansionPanel', 'caption' => $this->StatusWidgetText('layout'), 'items' => $layout];
            $form['elements'][] = ['type' => 'ExpansionPanel', 'caption' => $this->StatusWidgetText(strtolower($prefix)), 'items' => $items];
        }
        $form['elements'][] = ['type' => 'Label', 'caption' => $this->StatusWidgetText('imageHint')];
        return $form;
    }

    /** Publishes a read-only model. IPSView polls the cache; its HTML variable stays stable. */
    protected function PublishStatusWidget(array $state): void
    {
        $this->WriteAttributeString('StatusWidgetState', $this->EncodeVisualizationHTMLJSON($state));
        $this->UpdateVisualizationValue($this->EncodeVisualizationHTMLJSON($this->StatusWidgetBootstrap(false)));
    }

    /** Compact HTML document, with no navigation or dashboard shell. */
    protected function RenderStatusWidget(bool $ipsView): string
    {
        if (!$ipsView) {
            return $this->RenderStatusWidgetPage(false);
        }
        return $this->RenderIPSViewFrame([
            'url'   => $this->StatusWidgetServerURLValid() ? $this->ReadPropertyString('IPSViewServerURL') . '/hook/' . $this->StatusWidgetHookName() . '/page' : '',
            'token' => $this->ReadAttributeString('StatusWidgetToken'),
            'title' => $this->Translate('Overall presence'),
            'error' => $this->StatusWidgetText('loader')
        ]);
    }

    /** Safe inline image, no remote requests, file paths, SVG or arbitrary media content. */
    protected function StatusWidgetImage(int $mediaID): string
    {
        try {
            if ($mediaID <= 0 || !IPS_MediaExists($mediaID)) {
                return '';
            }
            $media = IPS_GetMedia($mediaID);
            if ($media['MediaType'] !== 1 || !$media['MediaIsAvailable'] || $media['MediaSize'] > 131072) {
                return '';
            }
            return $this->StatusWidgetUpload(IPS_GetMediaContent($mediaID));
        } catch (Throwable) {
            return '';
        }
    }

    /** SelectFile supplies Base64 bytes, never a client or server filesystem path. */
    protected function StatusWidgetUpload(string $content): string
    {
        return $this->InspectStatusWidgetUpload($content)['image'];
    }

    /** Same validation for the unsaved console draft and rendered images. */
    protected function StatusWidgetUploadIssue(string $content): string
    {
        return $this->InspectStatusWidgetUpload($content)['issue'];
    }

    /** Serves only the cached model, never runs a domain action or an external request. */
    protected function ProcessHookData(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-Status-Widget-Token');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $path = '/hook/' . $this->StatusWidgetHookName();
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $page = $uri === $path . '/page';
        if (($uri !== $path && !$page) || !$this->IsIPSViewHTMLPageEnabled()
            || !$this->StatusWidgetServerURLValid() || $this->ReadAttributeInteger('StatusWidgetConnection') !== IPS_GetInstance($this->InstanceID)['ConnectionID']) {
            $this->SendPlainTextResponse(404, 'Not available.');
            return;
        }
        if ($page) {
            if ($method !== 'GET') {
                $this->SendPlainTextResponse(405, 'Method not allowed.');
                return;
            }
            header('Referrer-Policy: no-referrer');
            $this->SendResponse(200, 'text/html', $this->RenderStatusWidgetPage(true, true));
            return;
        }
        if ($method === 'OPTIONS') {
            $this->SendPlainTextResponse(204, '');
            return;
        }
        if ($method !== 'POST') {
            $this->SendPlainTextResponse(405, 'Method not allowed.');
            return;
        }
        $token = $this->ReadAttributeString('StatusWidgetToken');
        $provided = $_SERVER['HTTP_X_STATUS_WIDGET_TOKEN'] ?? '';
        if ($token === '' || !is_string($provided) || !hash_equals($token, $provided)) {
            $this->SendPlainTextResponse(403, 'Access denied.');
            return;
        }
        $this->SendResponse(200, 'application/json', $this->EncodeVisualizationHTMLJSON($this->StatusWidgetBootstrap(true)));
    }

    protected function StatusWidgetText(string $key): string
    {
        return $this->Translate(self::PRESENCE_WIDGET_TEXT[$key]);
    }

    private function InspectStatusWidgetUpload(string $content): array
    {
        if ($content === '') {
            return ['image' => '', 'issue' => ''];
        }
        if (strlen($content) > 174764) {
            return ['image' => '', 'issue' => self::PRESENCE_WIDGET_TEXT['imageSizeError']];
        }
        $bytes = base64_decode($content, true);
        if (!is_string($bytes)) {
            return ['image' => '', 'issue' => self::PRESENCE_WIDGET_TEXT['imageFormatError']];
        }
        if (strlen($bytes) > 131072) {
            return ['image' => '', 'issue' => self::PRESENCE_WIDGET_TEXT['imageSizeError']];
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1
            || !in_array($size['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return ['image' => '', 'issue' => self::PRESENCE_WIDGET_TEXT['imageFormatError']];
        }
        if ($size[0] > 1024 || $size[1] > 1024) {
            return ['image' => '', 'issue' => self::PRESENCE_WIDGET_TEXT['imageDimensionsError']];
        }
        return ['image' => 'data:' . $size['mime'] . ';base64,' . base64_encode($bytes), 'issue' => ''];
    }

    /** The unauthenticated HTTP shell must never contain device data or its access token. */
    private function RenderStatusWidgetPage(bool $ipsView, bool $publicShell = false): string
    {
        $bootstrap = $this->StatusWidgetBootstrap($ipsView);
        if ($publicShell) {
            $bootstrap['state'] = ['title' => $this->Translate('Overall presence'), 'summary' => [], 'members' => []];
        }
        return $this->RenderVisualizationHTMLPage($ipsView, [
            'title'              => (string) ($bootstrap['state']['title'] ?? ''), 'state' => $bootstrap['state'],
            'options'            => $bootstrap['options'],
            'visualizationTheme' => $this->VisualizationThemeCSS(),
            'ipsViewStyle'       => $this->VisualizationBaseThemeCSS() . $this->IPSViewStyleCSSVariables(':root'),
            'rootFontSize'       => $bootstrap['options']['design']['fontSize'],
            'replacements'       => ['{{RESPONSIVE_CSS}}' => $this->ResponsiveVisualizationCSS('#status-widget', 'status-widget', true),
                '{{HTTP_ORIGIN_SCRIPT}}'                  => $this->VisualizationHTTPOriginScript()]
        ]);
    }

    private function StatusWidgetHookName(): string
    {
        return 'SymconStatusWidget' . $this->InstanceID;
    }

    private function StatusWidgetServerURLValid(): bool
    {
        $url = $this->ReadPropertyString('IPSViewServerURL');
        if ($url === '') {
            return true;
        }
        $parts = parse_url($url);
        return is_array($parts) && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && ($parts['path'] ?? '') === '' && !preg_match('/[\x00-\x20\x7f]/', $url);
    }

    private function StatusWidgetBootstrap(bool $ipsView): array
    {
        $prefix = $ipsView ? 'IPSView' : 'Tile';
        $design = ['photos' => $this->ReadPropertyBoolean($prefix . 'WidgetPhotos'), 'details' => $this->ReadPropertyBoolean($prefix . 'WidgetDetails')];
        foreach (['PictureSize' => [24, 96], 'Radius' => [0, 32], 'Spacing' => [4, 24]] as $name => [$min, $max]) {
            $design[lcfirst($name)] = max($min, min($max, $this->ReadPropertyInteger($prefix . 'Widget' . $name)));
        }
        $design['fontSize'] = $ipsView ? $this->IPSViewStyleRootFontSize() : max(10, min(28, $this->ReadPropertyInteger('TileWidgetFontSize'))) . 'px';
        return ['state' => json_decode($this->ReadAttributeString('StatusWidgetState'), true, 512, JSON_THROW_ON_ERROR),
            'options'   => array_merge($this->ResponsiveVisualizationLayoutOptions(!$ipsView), ['design' => $design,
                'themeCSS'                                                                               => $ipsView ? $this->VisualizationBaseThemeCSS() . $this->IPSViewStyleCSSVariables(':root') : $this->VisualizationThemeCSS(),
                'connectionError'                                                                        => $this->StatusWidgetText('connection'), 'empty' => $this->StatusWidgetText('empty'),
                'transport'                                                                              => $ipsView ? ['url' => '/hook/' . $this->StatusWidgetHookName(), 'token' => '', 'fragmentToken' => true] : null])];
    }
}
