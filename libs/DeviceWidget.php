<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/helper/IPSViewHTMLPageHelper.php';
require_once __DIR__ . '/helper/VisualizationThemeHelper.php';
require_once __DIR__ . '/helper/ResponsiveVisualizationHelper.php';

/** Device-specific location card; shared rendering, theme and input remain helper-owned. */
trait DeviceWidget
{
    use \Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
    use \Burki24\SymconModuleHelper\VisualizationThemeHelper;
    use \Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;

    private const DEVICE_WIDGET_TEXT = [
        'title'       => 'Find phone', 'location' => 'Last known location', 'none' => 'No known location available.',
        'old'         => 'Location is old or currently unavailable.', 'time' => 'LocationTimestamp',
        'unknownTime' => 'Location time unknown.', 'accuracy' => 'Accuracy', 'battery' => 'BatteryLevel',
        'online'      => 'Online', 'offline' => 'Offline', 'play' => 'Play sound', 'busy' => 'Sending sound request...',
        'uncertain'   => 'Sound request could not be confirmed. Check the device before trying again.'
    ];

    /** @return string Native instance card, separate from the existing sound variable tile. */
    public function GetVisualizationTile(): string
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function (): string
        {
            $payload = $this->DeviceWidgetPayload();
            return $this->RenderVisualizationHTMLPage(false, $payload + [
                'title'        => $this->Translate('Find phone'), 'visualizationTheme' => $this->VisualizationThemeCSS(),
                'language'     => $this->NormalizeHelperTranslationLanguage($this->ResolveHelperTranslationLanguage()),
                'rootFontSize' => '14px',
                'replacements' => ['{{RESPONSIVE_CSS}}' => $this->ResponsiveVisualizationCSS('#device-widget', 'device-widget', true)]
            ]);
        });
    }

    /** Retains one location with its matching address, never a fabricated default position. */
    protected function RememberDeviceLocation(): void
    {
        // Account pushes and explicit cache reads may arrive before ApplyChanges after an update.
        $this->registerAccuracyAvailability();
        // Preserve a migrated historical measurement before an incoming failure clears availability.
        $previous = $this->deviceLocationSnapshot();
        if (!$this->GetValue('AccuracyAvailable') && $previous !== null
            && $this->deviceSnapshotMatchesPosition($previous)) {
            return;
        }
        $this->captureDeviceLocation();
    }

    /** Uses local state and migrates legacy snapshots; no account request or geocoding. */
    protected function DeviceWidgetPayload(): array
    {
        return [
            'state' => [
                'name'              => $this->GetValue('DeviceName'), 'model' => $this->GetValue('Model'),
                'online'            => $this->GetValue('Online'), 'available' => $this->GetValue('DataAvailable'),
                'battery'           => $this->GetValue('BatteryAvailable') ? $this->GetValue('BatteryLevel') : null,
                'location'          => $this->deviceLocationSnapshot(),
                'locationAvailable' => $this->GetValue('LocationAvailable'), 'locationOld' => $this->GetValue('LocationIsOld'),
                'canPlaySound'      => $this->GetValue('DataAvailable'), 'result' => $this->ReadAttributeString('SoundResult'),
                'soundRevision'     => $this->ReadAttributeInteger('WidgetSoundRevision'),
                'error'             => $this->ReadAttributeString('LastError')
            ],
            'options'      => $this->ResponsiveVisualizationLayoutOptions(),
            'translations' => $this->IPSViewTranslationsFor(array_values(self::DEVICE_WIDGET_TEXT))
        ];
    }

    protected function PublishDeviceWidget(): void
    {
        $this->UpdateVisualizationValue($this->EncodeVisualizationHTMLJSON($this->DeviceWidgetPayload()));
    }

    /** Concrete path also works when Symcon evaluates a class under a synthetic filename. */
    protected function VisualizationAssetDirectory(): string
    {
        return __DIR__ . '/../iCloud Device/visualization';
    }

    /** Called after a successful cache application to capture its own quality, including unknown. */
    private function captureDeviceLocation(): void
    {
        if (!$this->GetValue('LocationAvailable') && !($this->ReadAttributeString('WidgetLocation') === 'null' && $this->GetValue('LocationTimestamp') > 0)) {
            return;
        }
        $this->WriteAttributeString('WidgetLocation', $this->EncodeVisualizationHTMLJSON([
            'latitude' => $this->GetValue('Latitude'), 'longitude' => $this->GetValue('Longitude'),
            'accuracy' => $this->GetValue('AccuracyAvailable') ? $this->GetValue('Accuracy') : null, 'timestamp' => $this->GetValue('LocationTimestamp'),
            'address'  => $this->GetValue('Address'), 'accuracyKnown' => $this->GetValue('AccuracyAvailable')
        ]));
    }

    /** Migrates only ambiguous historical accuracy; provenance stays inside the private snapshot. */
    private function deviceLocationSnapshot(): ?array
    {
        try {
            $location = json_decode($this->ReadAttributeString('WidgetLocation'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Unreadable history is unavailable; a valid incoming measurement must still recover.
            return null;
        }
        if (!is_array($location)) {
            return null;
        }
        $this->registerAccuracyAvailability();
        if (!is_bool($location['accuracyKnown'] ?? null)) {
            $accuracy = $location['accuracy'] ?? null;
            $known = (is_int($accuracy) || is_float($accuracy)) && is_finite((float) $accuracy) && $accuracy > 0;
            // An old zero is trustworthy only when it matches a still-available measured zero.
            if ((is_int($accuracy) || is_float($accuracy)) && $accuracy == 0
                && $this->GetValue('LocationAvailable') && $this->GetValue('AccuracyAvailable') && $this->GetValue('Accuracy') == 0
                && $this->deviceSnapshotMatchesPosition($location)) {
                $known = true;
            }
            $location['accuracy'] = $known ? $accuracy : null;
            $location['accuracyKnown'] = $known;
            $this->WriteAttributeString('WidgetLocation', $this->EncodeVisualizationHTMLJSON($location));
        }
        unset($location['accuracyKnown']);
        return $location;
    }

    private function deviceSnapshotMatchesPosition(array $location): bool
    {
        return is_numeric($location['latitude'] ?? null) && (float) $location['latitude'] === $this->GetValue('Latitude')
            && is_numeric($location['longitude'] ?? null) && (float) $location['longitude'] === $this->GetValue('Longitude')
            && ($location['timestamp'] ?? null) === $this->GetValue('LocationTimestamp');
    }
}
