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
require_once __DIR__ . '/helper/RasterImageHelper.php';
require_once __DIR__ . '/IPSViewFrame.php';

/**
 * SymconiCloud presence widget layout, images and cached IPSView output.
 * Uses the existing shared theme, style, HTTP, interaction and layout helpers.
 * Remains local until used by at least two different libraries.
 */
trait PresenceWidget
{
    use IPSViewFrame;
    use \Burki24\SymconModuleHelper\RasterImageHelper;

    private const PRESENCE_WIDGET_TEXT = [
        'tile'                 => 'Tile designer', 'ipsview' => 'IPSView designer', 'layout' => 'Layout and content',
        'variant'              => 'Widget variant',
        'variantsHint'         => 'IPSView provides Widget and WidgetCompact as separate HTML variables. WidgetCompact shows device pictures, names and zones without distance, stay duration or diagnostic details.',
        'colors'               => 'Colors', 'style' => 'Colors and typography', 'font' => 'Font size in pixels',
        'photos'               => 'Show device pictures', 'details' => 'Show details', 'size' => 'Picture size in pixels',
        'radius'               => 'Corner radius in pixels', 'spacing' => 'Spacing in pixels',
        'url'                  => 'Symcon server URL for IPSView (optional)',
        'urlHint'              => 'Normally the server address is detected automatically. Only enter an HTTP(S) origin without a path if the client cannot provide it.',
        'connection'           => 'Live updates unavailable. Retrying...', 'empty' => 'No devices selected.',
        'loader'               => 'Unable to open the IPSView widget. Check the server URL and apply the instance configuration.',
        'imageSizeError'       => 'Picture exceeds the 10 MiB upload limit. Choose a smaller file.',
        'imageFormatError'     => 'Invalid picture. Choose a PNG, JPEG, GIF or WebP file; this picture will not be displayed.',
        'imageDimensionsError' => 'Picture exceeds 8192 pixels per side or 16 million pixels. Choose a smaller image.',
        'imageUnavailable'     => 'Automatic picture preparation requires PHP GD on the Symcon server.',
        'imageConversionError' => 'The picture could not be prepared. Choose another image.',
        'imageHint'            => 'Optional PNG, JPEG, GIF or WebP pictures: choose a Symcon medium or a local file. Uploads up to 10 MiB and 16 million pixels are automatically reduced to at most 512 pixels per side and 128 KiB. Images are not enlarged. Invalid pictures use initials.'
    ];

    /** Call from Create(); volatile hook registration must survive output toggles. */
    protected function RegisterStatusWidget(): void
    {
        $this->RegisterIPSViewHTMLPageProperties();
        $this->RegisterVisualizationThemeProperties();
        $this->RegisterIPSViewStyleProperties();
        $this->RegisterPropertyString('IPSViewServerURL', '');
        $this->RegisterPropertyString('TileWidgetVariant', 'Widget');
        foreach (['Tile', 'IPSView'] as $prefix) {
            $this->RegisterPropertyBoolean($prefix . 'WidgetPhotos', true);
            $this->RegisterPropertyBoolean($prefix . 'WidgetDetails', true);
            $this->RegisterPropertyInteger($prefix . 'WidgetPictureSize', 48);
            $this->RegisterPropertyInteger($prefix . 'WidgetRadius', 12);
            $this->RegisterPropertyInteger($prefix . 'WidgetSpacing', 10);
        }
        $this->RegisterPropertyInteger('TileWidgetFontSize', 14);
        $this->RegisterAttributeString('StatusWidgetState', '{}');
        $this->RegisterAttributeString('StatusWidgetPictures', '{}');
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
        $this->WriteAttributeString('StatusWidgetPictures', '{}');
        $this->SetVisualizationType(1);
        $this->RegisterIPSViewStyleMediaMessages();
        $this->MaintainIPSViewHTMLVariable($ident, $caption, 2000, '', false);
        $this->MaintainIPSViewHTMLVariable('IPSViewPresenceCompact', $this->Translate('Presence WidgetCompact for IPSView'), 2001, '', false);
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
            if ($prefix === 'IPSView') {
                $items[] = $this->StatusWidgetColorGuide();
            }
            $layout = [];
            if ($prefix === 'Tile') {
                $layout[] = ['type' => 'Select', 'name' => 'TileWidgetVariant', 'caption' => $this->StatusWidgetText('variant'),
                    'options'       => [['caption' => 'Widget', 'value' => 'Widget'], ['caption' => 'WidgetCompact', 'value' => 'WidgetCompact']]];
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
        $form['elements'][] = ['type' => 'Label', 'caption' => $this->StatusWidgetText('variantsHint')];
        return $form;
    }

    /** Publishes a read-only model. IPSView polls the cache; its HTML variable stays stable. */
    protected function PublishStatusWidget(array $state): void
    {
        $this->WriteAttributeString('StatusWidgetState', $this->EncodeVisualizationHTMLJSON($state));
        $this->UpdateVisualizationValue($this->EncodeVisualizationHTMLJSON($this->StatusWidgetBootstrap(false, $this->ReadPropertyString('TileWidgetVariant') === 'WidgetCompact')));
    }

    /** Compact HTML document, with no navigation or dashboard shell. */
    protected function RenderStatusWidget(bool $ipsView, bool $compact = false): string
    {
        if (!$ipsView) {
            return $this->RenderStatusWidgetPage(false, false, $compact);
        }
        return $this->RenderIPSViewFrame([
            'url'   => $this->StatusWidgetServerURLValid() ? $this->ReadPropertyString('IPSViewServerURL') . '/hook/' . $this->StatusWidgetHookName() . ($compact ? '/compact/page' : '/page') : '',
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
        $compact = in_array($uri, [$path . '/compact', $path . '/compact/page'], true);
        $page = in_array($uri, [$path . '/page', $path . '/compact/page'], true);
        if (($uri !== $path && $uri !== $path . '/compact' && !$page) || !$this->IsIPSViewHTMLPageEnabled()
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
            $this->SendResponse(200, 'text/html', $this->RenderStatusWidgetPage(true, true, $compact));
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
        $this->SendResponse(200, 'application/json', $this->EncodeVisualizationHTMLJSON($this->StatusWidgetBootstrap(true, $compact)));
    }

    protected function StatusWidgetText(string $key): string
    {
        return $this->Translate(self::PRESENCE_WIDGET_TEXT[$key]);
    }

    /** Module-specific guide, encoded at build time with the central SVGPreviewHelper. */
    private function StatusWidgetColorGuide(): array
    {
        $roles = [
            'ViewBackground'          => 'Widget background behind the header and device cards.',
            'ControlBackground'       => 'Background of an unselected device card.',
            'LabelText'               => 'Device names, heading and avatar initials.',
            'TextInactive'            => 'Distance, stay duration, diagnostic text and summary counts in Widget.',
            'Positive'                => 'Present: zone text and left status stripe. The header uses a 13% status tint.',
            'Information'             => 'Absent: zone text and left status stripe.',
            'Warning'                 => 'Unknown: status text, left stripe and connection warnings.',
            'Border'                  => 'Device card border; visible when border width is greater than zero.',
            'Accent'                  => 'Outline of the selected device card.',
            'AccentSoft'              => 'Selected card background, derived from Accent with 18% opacity.',
            'ControlActiveBackground' => 'Avatar background behind initials or transparent pictures.',
            'ShadowColor'             => 'Card shadow, depending on the configured shadow settings.'
        ];
        $items = [
            ['type' => 'Label', 'caption' => $this->Translate('Schematic example colors. The numbered assignments apply to Widget, WidgetCompact and all light/dark styles; this image does not preview unsaved color changes.')],
            ['type'     => 'Image', 'name' => 'PresenceIPSViewColorGuide', 'width' => '100%',
                'image' => trim($this->VisualizationAsset('color-guide.image'))]
        ];
        $number = 1;
        foreach ($roles as $role => $description) {
            $items[] = ['type' => 'Label', 'caption' => $number++ . '. ' . $role . ': ' . $this->Translate($description)];
        }
        $items[] = ['type' => 'Label', 'caption' => $this->Translate('Text is the base fallback; device names use LabelText and details use TextInactive. Popup, chart and Critical colors are not used by these widgets.')];
        return ['type' => 'ExpansionPanel', 'caption' => $this->Translate('Color guide: Widget and WidgetCompact'), 'items' => $items];
    }

    private function InspectStatusWidgetUpload(string $content): array
    {
        $key = hash('sha256', $content);
        $cache = json_decode($this->ReadAttributeString('StatusWidgetPictures'), true);
        $cache = is_array($cache) ? $cache : [];
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $result = $this->PrepareRasterImage($content);
        $issues = ['input_size' => 'imageSizeError', 'format' => 'imageFormatError', 'pixels' => 'imageDimensionsError',
            'unavailable'       => 'imageUnavailable', 'conversion' => 'imageConversionError'];
        $value = ['image' => $result['content'] === '' ? '' : 'data:' . $result['mime'] . ';base64,' . $result['content'],
            'issue'       => $result['error'] === '' ? '' : self::PRESENCE_WIDGET_TEXT[$issues[$result['error']]]];
        // Bounded to the maximum number of configured devices. Only compact
        // results and input fingerprints persist, never another original photo.
        if (count($cache) >= 64) {
            array_shift($cache);
        }
        if ($result['error'] !== 'unavailable') {
            $cache[$key] = $value;
            $this->WriteAttributeString('StatusWidgetPictures', json_encode($cache, JSON_THROW_ON_ERROR));
        }
        return $value;
    }

    /** The unauthenticated HTTP shell must never contain device data or its access token. */
    private function RenderStatusWidgetPage(bool $ipsView, bool $publicShell = false, bool $compact = false): string
    {
        $bootstrap = $this->StatusWidgetBootstrap($ipsView, $compact);
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

    private function StatusWidgetBootstrap(bool $ipsView, bool $compact = false): array
    {
        $prefix = $ipsView ? 'IPSView' : 'Tile';
        $design = ['photos' => $this->ReadPropertyBoolean($prefix . 'WidgetPhotos'), 'details' => $this->ReadPropertyBoolean($prefix . 'WidgetDetails')];
        foreach (['PictureSize' => [24, 96], 'Radius' => [0, 32], 'Spacing' => [4, 24]] as $name => [$min, $max]) {
            $design[lcfirst($name)] = max($min, min($max, $this->ReadPropertyInteger($prefix . 'Widget' . $name)));
        }
        $design['fontSize'] = $ipsView ? $this->IPSViewStyleRootFontSize() : max(10, min(28, $this->ReadPropertyInteger('TileWidgetFontSize'))) . 'px';
        if ($compact) {
            $design['pictureSize'] = min(36, $design['pictureSize']);
            $design['spacing'] = min(8, $design['spacing']);
        }
        $state = json_decode($this->ReadAttributeString('StatusWidgetState'), true, 512, JSON_THROW_ON_ERROR);
        if ($compact) {
            $state['summary']['detail'] = '';
            foreach ($state['members'] ?? [] as $index => $member) {
                $state['members'][$index] = array_replace($member, ['status' => $member['zone'] ?? '', 'detail' => '', 'reason' => '']);
            }
        }
        return ['state' => $state,
            'options'   => array_merge($this->ResponsiveVisualizationLayoutOptions(!$ipsView), ['design' => $design, 'variant' => $compact ? 'WidgetCompact' : 'Widget',
                'themeCSS'                                                                               => $ipsView ? $this->VisualizationBaseThemeCSS() . $this->IPSViewStyleCSSVariables(':root') : $this->VisualizationThemeCSS(),
                'connectionError'                                                                        => $this->StatusWidgetText('connection'), 'empty' => $this->StatusWidgetText('empty'),
                'transport'                                                                              => $ipsView ? ['url' => '/hook/' . $this->StatusWidgetHookName() . ($compact ? '/compact' : ''), 'token' => '', 'fragmentToken' => true] : null])];
    }
}
