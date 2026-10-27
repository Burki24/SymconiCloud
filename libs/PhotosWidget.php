<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/helper/IPSViewHTMLPageHelper.php';
require_once __DIR__ . '/helper/VisualizationAssetHelper.php';
require_once __DIR__ . '/helper/VisualizationThemeConfigurationHelper.php';
require_once __DIR__ . '/helper/IPSViewStyleConfigurationHelper.php';
require_once __DIR__ . '/helper/ResponsiveVisualizationHelper.php';
require_once __DIR__ . '/helper/HttpResponseHelper.php';
require_once __DIR__ . '/IPSViewFrame.php';
require_once __DIR__ . '/DesignerColorGuide.php';
require_once __DIR__ . '/ApplePhotoDownload.php';

/** Library-specific photo widget, composed from existing central visualization helpers. */
trait PhotosWidget
{
    use \Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
    use \Burki24\SymconModuleHelper\VisualizationAssetHelper;
    use \Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;
    use \Burki24\SymconModuleHelper\IPSViewStyleConfigurationHelper;
    use \Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;
    use \Burki24\SymconModuleHelper\HttpResponseHelper;
    use IPSViewFrame;
    use DesignerColorGuide;

    private const PHOTO_GUIDE_ROLES = [
        'ViewBackground' => 'Widget background around the picture.', 'ControlBackground' => 'Button backgrounds.',
        'PageBackground' => 'Empty picture frame and letterboxing.', 'Text' => 'Filename and date.',
        'TextActive'     => 'Active pause button text.', 'ControlActiveBackground' => 'Active pause button background.',
        'Border'         => 'Picture and button borders.', 'Warning' => 'Connection or image errors.'
    ];

    private const PHOTO_GUIDE_LABELS = [
        'ViewBackground' => ['color.view_background', 'View background'], 'ControlBackground' => ['color.control_background', 'Control background'],
        'PageBackground' => ['color.page_background', 'Page background'], 'Text' => ['color.text_primary', 'Primary text'],
        'TextActive'     => ['color.text_active', 'Active text'], 'ControlActiveBackground' => ['color.control_active_background', 'Active control background'],
        'Border'         => ['color.border', 'Border color'], 'Warning' => ['color.warning', 'Warning status']
    ];

    private const PHOTO_TILE_GUIDE_LABELS = [
        'Background' => ['color.background', 'Background', 'VisualizationThemeConfigurationHelper'],
        'Text'       => ['color.text', 'Primary text', 'VisualizationThemeConfigurationHelper'],
        'Heading'    => ['color.heading', 'Headings', 'VisualizationThemeConfigurationHelper'],
        'Warning'    => ['color.warning', 'Warning', 'VisualizationThemeConfigurationHelper'],
        'Subheading' => ['color.subheading', 'Subheadings and secondary text', 'VisualizationThemeConfigurationHelper'],
        'Accent'     => ['color.accent', 'Accent', 'VisualizationThemeConfigurationHelper']
    ];

    public function GetVisualizationTile(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->PhotoPage(false);
        });
    }

    public function GetIPSViewHTML(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->RenderIPSViewFrame(['url' => $this->PhotoOriginValid() ? $this->ReadPropertyString('IPSViewServerURL') . '/hook/' . $this->PhotoHook() . '/page' : '',
                'token'                             => $this->ReadAttributeString('PhotoToken'), 'title' => $this->Translate('iCloud Photos'), 'error' => $this->Translate('Unable to open Photos. Check the server URL and apply the configuration.')]);
        });
    }

    /** Cached model reads recheck authorization even when existing previews are on disk. */
    public function ReadPhotoWidget(string $operation, int $offset = 0, string $photoID = ''): array
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($operation, $offset, $photoID): array
        {
            if (!$this->ReadPropertyBoolean('Enabled') || $this->ReadAttributeString('PhotoToken') === ''
                || $this->ReadAttributeString('PhotoSource') !== $this->CurrentPhotoSource()) {
                return ['success' => false, 'revoked' => true];
            }
            $catalog = $this->PhotoAccountRequest('GetPhotoAlbums');
            if (($catalog['success'] ?? false) !== true || !in_array($this->ReadPropertyString('AlbumID'), array_column($catalog['albums'] ?? [], 'id'), true)) {
                return ['success' => false, 'revoked' => true, 'error' => $catalog['error'] ?? $this->Translate('Select an available album and refresh photos.')];
            }
            return match ($operation) {
                'model' => ['success' => true, 'state' => json_decode($this->ReadAttributeString('PhotoState'), true, 512, JSON_THROW_ON_ERROR), 'source' => $this->CurrentPhotoSource(), 'error' => $this->ReadAttributeString('LastError')],
                'page'  => $offset >= 0 && $offset <= 1000000000 ? $this->PhotoAccountRequest('GetPhotoPage', ['Offset' => $offset]) : ['success' => false],
                'image' => $photoID !== '' && strlen($photoID) <= 512 ? $this->PhotoAccountRequest('GetPhotoImage', ['PhotoID' => $photoID]) : ['success' => false],
                default => ['success' => false]
            };
        });
    }

    protected function RegisterPhotoWidget(): void
    {
        $this->RegisterIPSViewHTMLPageProperties();
        $this->RegisterVisualizationThemeProperties();
        $this->RegisterIPSViewStyleProperties();
        $this->RegisterPropertyString('IPSViewServerURL', '');
        foreach (['Tile', 'IPSView'] as $prefix) {
            $this->RegisterPropertyString($prefix . 'PhotoMode', 'single');
            $this->RegisterPropertyString($prefix . 'PhotoFit', 'contain');
            $this->RegisterPropertyInteger($prefix . 'PhotoSeconds', 30);
            $this->RegisterPropertyBoolean($prefix . 'PhotoShuffle', false);
            $this->RegisterPropertyBoolean($prefix . 'PhotoCaption', true);
        }
        $this->RegisterAttributeString('PhotoToken', '');
        $this->SetVisualizationType(1);
        if (!$this->RegisterHook($this->PhotoHook())) {
            throw new \RuntimeException('Could not register the photo WebHook.');
        }
    }

    protected function ConfigurePhotoWidget(): void
    {
        $this->RegisterIPSViewStyleMediaMessages();
        if (!$this->ReadPropertyBoolean('Enabled') || !$this->PhotoOriginValid()) {
            $this->WriteAttributeString('PhotoToken', '');
        } elseif ($this->ReadAttributeString('PhotoToken') === '') {
            $this->WriteAttributeString('PhotoToken', bin2hex(random_bytes(32)));
        }
        $this->MaintainIPSViewHTMLVariable('IPSViewPhotos', $this->Translate('Photos for IPSView'), 2000, '', false);
    }

    protected function PublishPhotos(): void
    {
        $this->UpdateVisualizationValue($this->EncodeVisualizationHTMLJSON($this->PhotoBootstrap(false)));
    }

    protected function ProcessHookData(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: X-Photo-Token, Content-Type');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Referrer-Policy: no-referrer');
            header('X-Content-Type-Options: nosniff');
            $path = '/hook/' . $this->PhotoHook();
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            if (!$this->ReadPropertyBoolean('Enabled') || !$this->PhotoOriginValid() || !in_array($uri, [$path, $path . '/page'], true)) {
                $this->CloudDebugPlainResponse(404, 'Not available.', __FUNCTION__, __LINE__);
                return;
            }
            if ($uri === $path . '/page' && $method === 'GET' && $this->IsIPSViewHTMLPageEnabled()) {
                $this->CloudDebugResponse(200, 'text/html', $this->PhotoPage(true, true), __FUNCTION__, __LINE__);
                return;
            }
            if ($method === 'OPTIONS') {
                $this->CloudDebugPlainResponse(204, '', __FUNCTION__, __LINE__);
                return;
            }
            if ($method !== 'POST' || $uri !== $path) {
                $this->CloudDebugPlainResponse(405, 'Method not allowed.', __FUNCTION__, __LINE__);
                return;
            }
            $token = $this->ReadAttributeString('PhotoToken');
            if ($token === '' || !hash_equals($token, $_SERVER['HTTP_X_PHOTO_TOKEN'] ?? '')) {
                $this->CloudDebugPlainResponse(403, 'Access denied.', __FUNCTION__, __LINE__);
                return;
            }
            try {
                $input = (string) file_get_contents('php://input', false, null, 0, 2049);
                if (strlen($input) > 2048) {
                    throw new \InvalidArgumentException();
                }
                $request = json_decode($input, true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($request) || array_diff(array_keys($request), ['operation', 'offset', 'id', 'view', 'token', 'page']) !== []
                    || !in_array($request['view'] ?? 'tile', ['tile', 'ipsview'], true)
                    || !is_string($request['operation'] ?? null) || !is_int($request['offset'] ?? 0) || !is_string($request['id'] ?? '')
                    || !is_string($request['token'] ?? '') || strlen($request['token'] ?? '') > 32 || !is_int($request['page'] ?? 0)) {
                    throw new \InvalidArgumentException();
                }
                $result = in_array($request['operation'], ['imagePage', 'imageClear'], true)
                    ? $this->ReadPhotoTransfer($request['id'] ?? '', $request['token'] ?? '', $request['page'] ?? 0, $request['operation'] === 'imageClear')
                    : $this->ReadPhotoWidget($request['operation'], $request['offset'] ?? 0, $request['id'] ?? '');
                if (($result['success'] ?? false) && $request['operation'] === 'model') {
                    $result['options'] = $this->PhotoBootstrap(($request['view'] ?? 'tile') === 'ipsview', true)['options'];
                }
                $this->CloudDebugResponse(($result['success'] ?? false) ? 200 : (($result['revoked'] ?? false) ? 403 : 503), 'application/json', json_encode($result, JSON_THROW_ON_ERROR), __FUNCTION__, __LINE__);
            } catch (\Throwable) {
                $this->CloudDebugPlainResponse(400, 'Invalid photo request.', __FUNCTION__, __LINE__);
            }
        }, true);
    }

    /** Transfer pages remain bound to this widget's current account and album. */
    protected function ReadPhotoTransfer(string $photoID, string $token, int $page, bool $clear): array
    {
        $access = $this->ReadPhotoWidget('model');
        if (!($access['success'] ?? false)) {
            return $access;
        }
        if ($photoID === '' || strlen($photoID) > 512 || preg_match('/\A[a-f0-9]{32}\z/D', $token) !== 1 || $page < 0 || $page > 100) {
            return ['success' => false];
        }
        $arguments = ['PhotoID' => $photoID, 'Token' => $token];
        if (!$clear) {
            $arguments['Page'] = $page;
        }
        return $this->PhotoAccountRequest($clear ? 'ClearPhotoImageTransfer' : 'GetPhotoImagePage', $arguments);
    }

    protected function PhotoWidgetForm(array $form): array
    {
        foreach (['Tile', 'IPSView'] as $prefix) {
            $items = $prefix === 'IPSView' ? $this->IPSViewHTMLPageFormItems() : [];
            if ($prefix === 'IPSView') {
                $items[] = ['type' => 'ValidationTextBox', 'name' => 'IPSViewServerURL', 'caption' => $this->Translate('Symcon server URL (optional; normally detected automatically)')];
            }
            $items[] = ['type' => 'Select', 'name' => $prefix . 'PhotoMode', 'caption' => $this->Translate('Display mode'), 'options' => [['caption' => $this->Translate('Single picture'), 'value' => 'single'], ['caption' => $this->Translate('Slideshow'), 'value' => 'slideshow']]];
            $items[] = ['type' => 'Select', 'name' => $prefix . 'PhotoFit', 'caption' => $this->Translate('Picture fit'), 'options' => [['caption' => $this->Translate('Show complete picture'), 'value' => 'contain'], ['caption' => $this->Translate('Fill frame (crop)'), 'value' => 'cover']]];
            $items[] = ['type' => 'NumberSpinner', 'name' => $prefix . 'PhotoSeconds', 'caption' => $this->Translate('Slideshow interval in seconds'), 'minimum' => 5, 'maximum' => 3600];
            $items[] = ['type' => 'CheckBox', 'name' => $prefix . 'PhotoShuffle', 'caption' => $this->Translate('Random order within each loaded page')];
            $items[] = ['type' => 'CheckBox', 'name' => $prefix . 'PhotoCaption', 'caption' => $this->Translate('Show filename and date')];
            $items[] = ['type' => 'ExpansionPanel', 'caption' => $this->Translate('Colors and typography'), 'items' => $prefix === 'Tile' ? $this->VisualizationThemeFormItems() : $this->IPSViewStyleFormItems()];
            $items[] = $this->PhotoColorGuide($prefix === 'IPSView');
            $form['elements'][] = ['type' => 'ExpansionPanel', 'caption' => $this->Translate($prefix === 'IPSView' ? 'IPSView designer' : 'Tile designer'), 'items' => $items];
        }
        return $form;
    }

    private function CurrentPhotoSource(): string
    {
        return hash('sha256', json_encode([$this->GetParentID(), $this->ReadPropertyString('AlbumID'), $this->ReadPropertyBoolean('Enabled')], JSON_THROW_ON_ERROR));
    }

    private function PhotoHook(): string
    {
        return 'SymconiCloudPhotos' . $this->InstanceID;
    }

    private function PhotoOriginValid(): bool
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

    private function PhotoBootstrap(bool $ipsView, bool $publicShell = false): array
    {
        $prefix = $ipsView ? 'IPSView' : 'Tile';
        $result = $publicShell ? ['state' => ['items' => [], 'next' => null, 'total' => 0], 'source' => '', 'success' => true] : $this->ReadPhotoWidget('model');
        return $result + ['options' => array_merge($this->ResponsiveVisualizationLayoutOptions(!$ipsView), [
            'instance' => $this->InstanceID, 'endpoint' => '/hook/' . $this->PhotoHook(), 'origin' => $this->ReadPropertyString('IPSViewServerURL'),
            'token'    => $ipsView ? '' : $this->ReadAttributeString('PhotoToken'), 'fragmentToken' => $ipsView,
            'mode'     => $this->ReadPropertyString($prefix . 'PhotoMode'), 'fit' => $this->ReadPropertyString($prefix . 'PhotoFit'),
            'seconds'  => max(5, min(3600, $this->ReadPropertyInteger($prefix . 'PhotoSeconds'))), 'shuffle' => $this->ReadPropertyBoolean($prefix . 'PhotoShuffle'),
            'caption'  => $this->ReadPropertyBoolean($prefix . 'PhotoCaption'), 'themeCSS' => $ipsView ? $this->VisualizationBaseThemeCSS() . $this->IPSViewStyleCSSVariables(':root') : $this->VisualizationThemeCSS(),
            'text'     => $this->IPSViewTranslationsFor(['Previous', 'Next', 'Pause', 'Play', 'No photos loaded. Select an album and refresh photos.', 'Photos unavailable. Retrying...', 'Preview unavailable. Try the next picture.'])])];
    }

    private function PhotoPage(bool $ipsView, bool $publicShell = false): string
    {
        $bootstrap = $this->PhotoBootstrap($ipsView, $publicShell);
        return $this->RenderVisualizationHTMLPage($ipsView, ['title' => $this->Translate('iCloud Photos'),
            'rootFontSize'                                           => $ipsView ? $this->IPSViewStyleRootFontSize() : '14px', 'state' => $bootstrap['state'] ?? ['items' => [], 'next' => null, 'total' => 0], 'options' => $bootstrap['options'], 'runtime' => ['source' => $bootstrap['source'] ?? '', 'revoked' => $bootstrap['revoked'] ?? false],
            'visualizationTheme'                                     => $this->VisualizationThemeCSS(), 'ipsViewStyle' => $this->VisualizationBaseThemeCSS() . $this->IPSViewStyleCSSVariables(':root'),
            'replacements'                                           => ['{{RESPONSIVE_CSS}}' => $this->ResponsiveVisualizationCSS('#photo-widget', 'photos', true), '{{HTTP_ORIGIN_SCRIPT}}' => $this->VisualizationHTTPOriginScript()]]);
    }

    private function PhotoColorGuide(bool $ipsView): array
    {
        $roles = $ipsView ? self::PHOTO_GUIDE_ROLES : ['Background' => 'Widget background around the picture.', 'Text' => 'Filename and date.',
            'Heading'                                               => 'Active pause button text.', 'Warning' => 'Connection or image errors.', 'Subheading' => 'Empty picture hint.', 'Accent' => 'Keyboard focus outline.'];
        return $this->BuildDesignerColorGuide(
            'PhotoColorGuide' . ($ipsView ? 'IPSView' : 'Tile'),
            'Color mapping: Photos',
            'Schematic photo widget. Example colors are not a live preview.',
            $ipsView ? 'photos-color-guide' : 'photos-tile-color-guide',
            $roles,
            $ipsView ? self::PHOTO_GUIDE_LABELS : self::PHOTO_TILE_GUIDE_LABELS
        );
    }
}
