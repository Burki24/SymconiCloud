<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

require_once __DIR__ . '/HelperTranslationHelper.php';
require_once __DIR__ . '/VisualizationAssetHelper.php';

/**
 * Shared OpenFreeMap vector-map design, assets and per-output configuration.
 *
 * @version 1.0.0
 */
trait OpenFreeMapHelper
{
    use HelperTranslationHelper;
    use VisualizationAssetHelper;

    private const OPEN_FREE_MAP_TRANSLATION_SOURCES = [
        'palette_note'  => 'Enable custom map colors to apply the light and dark palettes below.',
        'panel'         => 'OpenFreeMap',
        'theme'         => 'Map appearance',
        'auto'          => 'Use designer theme',
        'light'         => 'Light',
        'dark'          => 'Dark',
        'light_style'   => 'Light map style',
        'dark_style'    => 'Dark map style',
        'light_url'     => 'Custom light style URL (optional)',
        'dark_url'      => 'Custom dark style URL (optional)',
        'visibility'    => 'Map details',
        'labels'        => 'Show labels',
        'poi'           => 'Show points of interest',
        'buildings'     => 'Show buildings',
        'boundaries'    => 'Show administrative boundaries',
        'language'      => 'Label language',
        'local'         => 'Local names',
        'german'        => 'German',
        'english'       => 'English',
        'custom_colors' => 'Use custom map colors',
        'light_colors'  => 'Light map colors',
        'dark_colors'   => 'Dark map colors',
        'land'          => 'Land color',
        'water'         => 'Water color',
        'park'          => 'Green areas color',
        'road'          => 'Road color',
        'building'      => 'Building color',
        'text'          => 'Label color',
        'description'   => 'Custom HTTPS MapLibre style JSON URLs override the selected styles. Attribution remains visible. Colors and visibility apply to compatible vector layers.',
    ];

    private const OPEN_FREE_MAP_SETTINGS = [
        'MapTheme'              => ['auto', 0, 0, 'theme'],
        'MapLightStyle'         => ['liberty', 0, 0, 'light_style'],
        'MapDarkStyle'          => ['dark', 0, 0, 'dark_style'],
        'MapLightStyleURL'      => ['', 0, 0, 'light_url'],
        'MapDarkStyleURL'       => ['', 0, 0, 'dark_url'],
        'MapLanguage'           => ['local', 0, 0, 'language'],
        'MapShowLabels'         => [true, 0, 0, 'labels'],
        'MapShowPOI'            => [true, 0, 0, 'poi'],
        'MapShowBuildings'      => [true, 0, 0, 'buildings'],
        'MapShowBoundaries'     => [true, 0, 0, 'boundaries'],
        'MapCustomColors'       => [false, 0, 0, 'custom_colors'],
        'MapLightLandColor'     => [15921129, 0, 16777215, 'land'],
        'MapLightWaterColor'    => [10406370, 0, 16777215, 'water'],
        'MapLightParkColor'     => [13164464, 0, 16777215, 'park'],
        'MapLightRoadColor'     => [16777215, 0, 16777215, 'road'],
        'MapLightBuildingColor' => [14209224, 0, 16777215, 'building'],
        'MapLightTextColor'     => [3159099, 0, 16777215, 'text'],
        'MapDarkLandColor'      => [2435118, 0, 16777215, 'land'],
        'MapDarkWaterColor'     => [1325904, 0, 16777215, 'water'],
        'MapDarkParkColor'      => [2571570, 0, 16777215, 'park'],
        'MapDarkRoadColor'      => [6449527, 0, 16777215, 'road'],
        'MapDarkBuildingColor'  => [4278097, 0, 16777215, 'building'],
        'MapDarkTextColor'      => [15067115, 0, 16777215, 'text'],
    ];

    /** Registers one independent map-design profile under a consumer prefix. */
    protected function RegisterOpenFreeMapProperties(string $prefix): void
    {
        foreach (self::OPEN_FREE_MAP_SETTINGS as $name => [$default]) {
            if (is_bool($default)) {
                $this->RegisterPropertyBoolean($prefix . $name, $default);
            } elseif (is_int($default)) {
                $this->RegisterPropertyInteger($prefix . $name, $default);
            } else {
                $this->RegisterPropertyString($prefix . $name, $default);
            }
        }
    }

    /** @return list<string> Names without the consumer prefix. */
    protected function OpenFreeMapPropertyNames(): array
    {
        return array_keys(self::OPEN_FREE_MAP_SETTINGS);
    }

    /** Copies saved profile values; applying the instance remains the consumer's job. */
    protected function CopyOpenFreeMapProperties(string $source, string $target): void
    {
        foreach (self::OPEN_FREE_MAP_SETTINGS as $name => [$default]) {
            IPS_SetProperty($this->InstanceID, $target . $name, $this->ReadOpenFreeMapProperty($source . $name, $default));
        }
    }

    /** @return array<string,mixed> Sanitized browser settings, independent of account data. */
    protected function ReadOpenFreeMapDesign(string $prefix): array
    {
        $result = [];
        foreach (self::OPEN_FREE_MAP_SETTINGS as $name => [$default, $minimum, $maximum]) {
            $value = $this->ReadOpenFreeMapProperty($prefix . $name, $default);
            $options = $this->OpenFreeMapOptions($name);
            if ($options !== [] && !array_key_exists($value, $options)) {
                $value = $default;
            }
            if (is_int($default)) {
                $value = sprintf('#%06x', max($minimum, min($maximum, $value)));
            }
            $result[lcfirst(substr($name, 3))] = $value;
        }
        return $result;
    }

    /** @return array<string,mixed> Own menu section, nestable in Tile and IPSView designers. */
    protected function OpenFreeMapFormSection(string $prefix, bool $enabled = true): array
    {
        $items = [];
        $details = [];
        $light = [];
        $dark = [];
        foreach (self::OPEN_FREE_MAP_SETTINGS as $name => [$default, $minimum, $maximum, $key]) {
            $options = $this->OpenFreeMapOptions($name);
            $item = ['type' => $options !== [] ? 'Select' : (is_bool($default) ? 'CheckBox' : (is_int($default) ? 'SelectColor' : 'ValidationTextBox')),
                'name'      => $prefix . $name, 'caption' => $this->OpenFreeMapText($key), 'enabled' => $enabled];
            if ($options !== []) {
                $item['options'] = [];
                foreach ($options as $value => $caption) {
                    $item['options'][] = ['caption' => $caption, 'value' => $value];
                }
            }
            if (str_ends_with($name, 'Color')) {
                if (str_starts_with($name, 'MapLight')) {
                    $light[] = $item;
                } else {
                    $dark[] = $item;
                }
            } elseif (str_starts_with($name, 'MapShow') || $name === 'MapLanguage') {
                $details[] = $item;
            } else {
                $items[] = $item;
            }
        }
        $items[] = ['type' => 'Label', 'caption' => $this->OpenFreeMapText('palette_note')];
        $items[] = ['type' => 'ExpansionPanel', 'caption' => $this->OpenFreeMapText('visibility'), 'items' => $details];
        $items[] = ['type' => 'ExpansionPanel', 'caption' => $this->OpenFreeMapText('light_colors'), 'items' => $light];
        $items[] = ['type' => 'ExpansionPanel', 'caption' => $this->OpenFreeMapText('dark_colors'), 'items' => $dark];
        $items[] = ['type' => 'Label', 'caption' => $this->OpenFreeMapText('description')];
        return ['type' => 'ExpansionPanel', 'caption' => 'OpenFreeMap', 'items' => $items];
    }

    /** @return array{css:string,script:string} Bundled, pinned renderer and shared controller. */
    protected function OpenFreeMapAssets(string $rendererPath, string $serverURL = ''): array
    {
        $load = static function (string $name): string
        {
            $contents = file_get_contents(__DIR__ . '/maps/' . $name);
            if ($contents === false || $contents === '') {
                throw new \RuntimeException('Required OpenFreeMap asset unavailable.');
            }
            return $contents;
        };
        $configuration = json_encode(['path' => $rendererPath, 'serverURL' => $serverURL, 'bridge' => $load('vendor/leaflet-maplibre-gl.js')], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return ['css' => $load('vendor/maplibre-gl.css'),
            'script'  => $this->VisualizationHTTPOriginScript() . "\n" . $load('openfreemap.js')
                . "\nwindow.SymconVectorAssetsReady = window.SymconOpenFreeMap.loadRenderer(" . $configuration . ", window);\nwindow.SymconVectorAssetsReady.catch(function () {});"];
    }

    /** Compressed response stays below Symcon's one-megabyte output buffer. */
    protected function OpenFreeMapRendererAsset(): string
    {
        $source = file_get_contents(__DIR__ . '/maps/vendor/maplibre-gl.js');
        if ($source === false) {
            throw new \RuntimeException('Vector renderer unavailable.');
        }
        $compressed = gzencode($source, 6);
        if ($compressed === false) {
            throw new \RuntimeException('Vector renderer compression failed.');
        }
        return $compressed;
    }

    private function ReadOpenFreeMapProperty(string $name, mixed $default): mixed
    {
        return is_bool($default) ? $this->ReadPropertyBoolean($name)
            : (is_int($default) ? $this->ReadPropertyInteger($name) : $this->ReadPropertyString($name));
    }

    private function OpenFreeMapText(string $key): string
    {
        return $this->TranslateHelperText('OpenFreeMapHelper', $key, self::OPEN_FREE_MAP_TRANSLATION_SOURCES[$key]);
    }

    /** @return array<string,string> Stable persisted values and translated display names. */
    private function OpenFreeMapOptions(string $name): array
    {
        if ($name === 'MapTheme') {
            return ['auto' => $this->OpenFreeMapText('auto'), 'light' => $this->OpenFreeMapText('light'), 'dark' => $this->OpenFreeMapText('dark')];
        }
        if (in_array($name, ['MapLightStyle', 'MapDarkStyle'], true)) {
            return ['liberty' => 'Liberty', 'bright' => 'Bright', 'positron' => 'Positron', 'fiord' => 'Fiord', 'dark' => 'Dark'];
        }
        if ($name === 'MapLanguage') {
            return ['local' => $this->OpenFreeMapText('local'), 'de' => $this->OpenFreeMapText('german'), 'en' => $this->OpenFreeMapText('english')];
        }
        return [];
    }
}
