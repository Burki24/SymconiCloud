<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

use InvalidArgumentException;

require_once __DIR__ . '/HelperTranslationHelper.php';
require_once __DIR__ . '/VisualizationThemeHelper.php';

/**
 * Adds optional, reusable color settings to the Symcon HTML-SDK theme.
 *
 * Native Symcon colors remain active by default. Consumers explicitly register
 * the properties and insert the form items only when their tile should expose
 * the shared customization UI.
 *
 * @version 1.0.1
 */
trait VisualizationThemeConfigurationHelper
{
    use HelperTranslationHelper;
    use VisualizationThemeHelper {
        VisualizationThemeCSS as private VisualizationBaseThemeCSS;
    }

    private const VISUALIZATION_THEME_FORM_MARKER =
        'Configure the shared Symcon tile theme used by the HTML-SDK visualization.';

    /** @var array<string,array{property:string,token:string,default:int,label:string}> */
    private const VISUALIZATION_THEME_COLORS = [
        'Text' => [
            'property' => 'VisualizationThemeTextColor',
            'token'    => '--symc-text',
            'default'  => 0x202124,
            'label'    => 'Primary text'
        ],
        'Heading' => [
            'property' => 'VisualizationThemeHeadingColor',
            'token'    => '--symc-heading',
            'default'  => 0x202124,
            'label'    => 'Headings'
        ],
        'Subheading' => [
            'property' => 'VisualizationThemeSubheadingColor',
            'token'    => '--symc-subheading',
            'default'  => 0x6F7378,
            'label'    => 'Subheadings and secondary text'
        ],
        'Background' => [
            'property' => 'VisualizationThemeBackgroundColor',
            'token'    => '--symc-background',
            'default'  => 0xFFFFFF,
            'label'    => 'Background'
        ],
        'Accent' => [
            'property' => 'VisualizationThemeAccentColor',
            'token'    => '--symc-accent',
            'default'  => 0x55CBB5,
            'label'    => 'Accent'
        ],
        'Information' => [
            'property' => 'VisualizationThemeInformationColor',
            'token'    => '--symc-info',
            'default'  => 0x62AEE8,
            'label'    => 'Information'
        ],
        'Success' => [
            'property' => 'VisualizationThemeSuccessColor',
            'token'    => '--symc-success',
            'default'  => 0x56C881,
            'label'    => 'Success'
        ],
        'Warning' => [
            'property' => 'VisualizationThemeWarningColor',
            'token'    => '--symc-warning',
            'default'  => 0xE6A93F,
            'label'    => 'Warning'
        ],
        'Danger' => [
            'property' => 'VisualizationThemeDangerColor',
            'token'    => '--symc-danger',
            'default'  => 0xE36D6D,
            'label'    => 'Error and danger'
        ]
    ];

    /** Registers the opt-in switch and all shared semantic color properties. */
    protected function RegisterVisualizationThemeProperties(): void
    {
        $this->RegisterPropertyBoolean('VisualizationThemeUseCustomColors', false);
        foreach (self::VISUALIZATION_THEME_COLORS as $definition) {
            $this->RegisterPropertyInteger($definition['property'], $definition['default']);
        }
    }

    /** @return array<int,array<string,mixed>> Symcon configuration-form items. */
    protected function VisualizationThemeFormItems(string $colorWidth = '260px'): array
    {
        $colorWidth = trim($colorWidth);
        if ($colorWidth === '') {
            throw new InvalidArgumentException('Visualization theme color-control width must not be empty.');
        }

        $custom = $this->ReadPropertyBoolean('VisualizationThemeUseCustomColors');
        $items = [
            [
                'type'    => 'Label',
                'caption' => $this->VisualizationThemeText(
                    'description.native',
                    'The tile follows the native Symcon content, card and accent colors. When custom colors are enabled, only values changed from their defaults are fixed; all unchanged roles continue to follow Symcon.'
                )
            ],
            [
                'type'    => 'CheckBox',
                'name'    => 'VisualizationThemeUseCustomColors',
                'caption' => $this->VisualizationThemeText('field.custom_colors', 'Use custom tile colors')
            ]
        ];

        foreach (array_chunk(self::VISUALIZATION_THEME_COLORS, 3, true) as $definitions) {
            $row = [];
            foreach ($definitions as $key => $definition) {
                $row[] = [
                    'type'             => 'SelectColor',
                    'name'             => $definition['property'],
                    'caption'          => $this->VisualizationThemeText('color.' . strtolower($key), $definition['label']),
                    'allowTransparent' => false,
                    'enabled'          => $custom,
                    'width'            => $colorWidth
                ];
            }
            $items[] = ['type' => 'RowLayout', 'items' => $row];
        }

        return $items;
    }

    /** Replaces a nested static form marker with the shared tile-theme controls. */
    protected function InsertVisualizationThemeFormItems(
        array &$elements,
        string $markerCaption = self::VISUALIZATION_THEME_FORM_MARKER,
        string $colorWidth = '260px'
    ): bool {
        $markerCaption = trim($markerCaption);
        if ($markerCaption === '') {
            throw new InvalidArgumentException('Visualization theme form marker caption must not be empty.');
        }

        foreach ($elements as $index => &$element) {
            if (($element['type'] ?? null) === 'Label' && ($element['caption'] ?? null) === $markerCaption) {
                array_splice($elements, $index, 1, $this->VisualizationThemeFormItems($colorWidth));
                unset($element);

                return true;
            }
            if (isset($element['items']) && is_array($element['items'])
                && $this->InsertVisualizationThemeFormItems($element['items'], $markerCaption, $colorWidth)) {
                unset($element);

                return true;
            }
        }
        unset($element);

        return false;
    }

    /** Returns the base theme plus active configured or explicitly supplied overrides. */
    protected function VisualizationThemeCSS(array $overrides = []): string
    {
        return $this->VisualizationBaseThemeCSS(array_replace($this->VisualizationThemeColorOverrides(), $overrides));
    }

    /** @return array<string,string> CSS token => #RRGGBB. */
    protected function VisualizationThemeColorOverrides(): array
    {
        if (!$this->ReadPropertyBoolean('VisualizationThemeUseCustomColors')) {
            return [];
        }

        $overrides = [];
        foreach (self::VISUALIZATION_THEME_COLORS as $definition) {
            $value = max(0, min(0xFFFFFF, $this->ReadPropertyInteger($definition['property'])));
            if ($value === $definition['default']) {
                continue;
            }

            $overrides[$definition['token']] = sprintf('#%06X', $value);
        }

        return $overrides;
    }

    private function VisualizationThemeText(string $key, string $fallback): string
    {
        return $this->TranslateHelperText('VisualizationThemeConfigurationHelper', $key, $fallback);
    }
}
