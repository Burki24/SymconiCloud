<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/CloudDebug.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';
require_once __DIR__ . '/../libs/PhotosWidget.php';

/** Read-only album widget; all Apple access and image storage belong to the account. */
class iCloudPhotos extends IPSModuleStrict
{
    use \SymconiCloud\CloudDebug;
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\ParentConnectionHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;
    use \SymconiCloud\PhotosWidget;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    public function Create(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterPropertyBoolean('Enabled', false);
            $this->RegisterPropertyString('AlbumID', '');
            $this->RegisterPropertyInteger('RefreshInterval', 900);
            $this->RegisterAttributeString('PhotoState', '{"items":[],"next":null,"total":0}');
            $this->RegisterAttributeString('PhotoSource', '');
            $this->RegisterAttributeString('LastError', '');
            $this->RegisterTimer('Refresh', 0, 'ICPH_Refresh($_IPS["TARGET"]);');
            $this->RegisterPhotoWidget();
        }, true);
    }

    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [self::ACCOUNT_MODULE]], JSON_THROW_ON_ERROR);
    }

    public function ApplyChanges(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $source = hash('sha256', json_encode([$this->GetParentID(), $this->ReadPropertyString('AlbumID'), $this->ReadPropertyBoolean('Enabled')], JSON_THROW_ON_ERROR));
            if ($source !== $this->ReadAttributeString('PhotoSource')) {
                $this->WriteAttributeString('PhotoState', '{"items":[],"next":null,"total":0}');
                $this->WriteAttributeString('PhotoSource', $source);
                $this->WriteAttributeString('PhotoToken', '');
            }
            $this->ConfigurePhotoWidget();
            $active = $this->ReadPropertyBoolean('Enabled') && $this->HasParent() && $this->ReadPropertyString('AlbumID') !== '';
            $this->CloudDebugTimer('Refresh', $active ? max(300, min(86400, $this->ReadPropertyInteger('RefreshInterval'))) * 1000 : 0, __FUNCTION__, __LINE__);
            $this->CloudDebugStatus($active ? IS_ACTIVE : IS_INACTIVE, __FUNCTION__, __LINE__);
            $this->PublishPhotos();
            $this->RegenerateIPSViewHTMLPages();
        }, true);
    }

    /** Opening a form only reads the existing catalog; loading albums is explicit. */
    public function GetConfigurationForm(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            $form = $this->LoadConfigurationForm();
            $options = [['caption' => $this->Translate('Select album'), 'value' => '']];
            $known = [];
            try {
                foreach ($this->PhotoAccountRequest('GetPhotoAlbums')['albums'] ?? [] as $album) {
                    $options[] = ['caption' => $album['kind'] === 'album' ? $album['name'] : $this->Translate($album['name']), 'value' => $album['id']];
                    $known[] = $album['id'];
                }
            } catch (Throwable) {
                // No login, discovery or remote request while editing configuration.
            }
            $selected = $this->ReadPropertyString('AlbumID');
            if ($selected !== '' && !in_array($selected, $known, true)) {
                $options[] = ['caption' => $this->Translate('Selected album (not available)'), 'value' => $selected];
            }
            $form['elements'][1]['options'] = $options;
            $form['actions'][] = ['type' => 'Label', 'caption' => $this->ReadAttributeString('LastError')];
            return $this->EncodeConfigurationForm($this->PhotoWidgetForm($form));
        });
    }

    public function RefreshAlbums(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            $result = $this->PhotoAccountRequest('RefreshPhotoAlbums');
            $this->WriteAttributeString('LastError', $result['error'] ?? '');
            $this->ReloadForm();
            return ($result['success'] ?? false) === true;
        });
    }

    public function Refresh(): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): bool
        {
            if (!$this->ReadPropertyBoolean('Enabled') || $this->ReadPropertyString('AlbumID') === '') {
                $this->CloudDebugProblem('Photo instance disabled or album missing.', __FUNCTION__, __LINE__);
                return false;
            }
            if (!IPS_SemaphoreEnter('SymconiCloud.Photos.' . $this->InstanceID, 1000)) {
                $this->CloudDebugProblem('busy', __FUNCTION__, __LINE__);
                return false;
            }
            try {
                $catalog = $this->PhotoAccountRequest('GetPhotoAlbums');
                if (($catalog['success'] ?? false) && time() - ($catalog['updatedAt'] ?? 0) >= 3600) {
                    $catalog = $this->PhotoAccountRequest('RefreshPhotoAlbums');
                }
                $page = ($catalog['success'] ?? false) ? $this->PhotoAccountRequest('GetPhotoPage', ['Offset' => 0]) : $catalog;
                if (($page['success'] ?? false) === true) {
                    $this->WriteAttributeString('PhotoState', json_encode($page, JSON_THROW_ON_ERROR));
                    $this->WriteAttributeString('LastError', '');
                    $this->CloudDebugStatus(IS_ACTIVE, __FUNCTION__, __LINE__);
                    return true;
                }
                $this->WriteAttributeString('LastError', $page['error'] ?? $this->Translate('Photos unavailable.'));
                $this->CloudDebugStatus(202, __FUNCTION__, __LINE__);
                return false;
            } finally {
                $this->PublishPhotos();
                IPS_SemaphoreLeave('SymconiCloud.Photos.' . $this->InstanceID);
            }
        });
    }

    public function ReceiveData(string $JSONString): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSONString): string
        {
            try {
                $this->DecodeDataFlowMessage($JSONString, self::NOTIFICATION_DATA);
                // Inventory pushes never trigger photo HTTP traffic.
                $this->PublishPhotos();
            } catch (Throwable) {
                return '';
            }
            return '';
        });
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($TimeStamp, $SenderID, $Message, $Data): void
        {
            parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
            if ($this->IsIPSViewStyleMediaUpdate($SenderID, $Message)) {
                $this->PublishPhotos();
            }
        }, true);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            if ($this->HandleIPSViewHTMLPageAction($Ident, $Value)) {
                return;
            }
            if ($Ident !== 'Refresh') {
                throw new InvalidArgumentException('Unsupported photo action.');
            }
            $this->Refresh();
        }, true);
    }

    public function Destroy(): void
    {
        $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): void
        { parent::Destroy(); }, true);
    }

    /** WebHook evaluation has a synthetic reflection filename; keep the central asset loader. */
    protected function VisualizationAssetDirectory(): string
    {
        return __DIR__ . '/visualization';
    }

    /** Fixed account commands; browser input cannot select a different album or URL. */
    protected function PhotoAccountRequest(string $command, array $arguments = []): array
    {
        if (!$this->HasParent()) {
            $this->CloudDebugProblem('Connect an account first.', __FUNCTION__, __LINE__);
            return ['success' => false, 'albums' => [], 'error' => $this->Translate('Connect an account first.')];
        }
        if (in_array($command, ['GetPhotoPage', 'GetPhotoImage', 'GetPhotoImagePage', 'ClearPhotoImageTransfer'], true)) {
            $arguments['AlbumID'] = $this->ReadPropertyString('AlbumID');
        }
        try {
            return json_decode($this->CloudDebugParent(json_encode(['DataID' => self::REQUEST_DATA, 'Command' => $command] + $arguments, JSON_THROW_ON_ERROR), __FUNCTION__, __LINE__), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['success' => false, 'albums' => [], 'error' => $this->Translate('Photos unavailable.')];
        }
    }
}
