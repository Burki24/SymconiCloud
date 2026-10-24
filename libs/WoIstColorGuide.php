<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/DesignerColorGuide.php';

/** Color documentation for the actual WoIst map, controls, list and popup. */
trait WoIstColorGuide
{
    use DesignerColorGuide;

    private const WOIST_GUIDE_ROLES = [
        'ViewBackground'            => 'Background of the map widget around the toolbar and workspace.',
        'ControlBackground'         => 'Toolbar buttons, device-list panel, zoom buttons and the empty map surface.',
        'PageBackground'            => 'Background of a device card that is neither selected nor unlocated.',
        'ControlActiveBackground'   => 'Selected located device card and hovered toolbar buttons.',
        'ControlInactiveBackground' => 'Background of a device without a valid position.',
        'LabelText'                 => 'WoIst heading and device-list heading.',
        'Text'                      => 'Toolbar text, regular device names and popup titles.',
        'TextActive'                => 'Name of the selected located device; metadata keeps the inactive text color.',
        'TextInactive'              => 'Update time, device metadata, popup details and the stripe of unlocated devices.',
        'Border'                    => 'Map frame, toolbar-button borders and device-card borders.',
        'Accent'                    => 'Outline of the selected device card; not the position marker.',
        'Warning'                   => 'Border of connection and address warnings, with an 18% soft background; the text keeps the primary text color.',
        'PopupBackground'           => 'Background of the map popup and its pointer.',
        'PopupBorder'               => 'Border of the map popup.',
        'Icon'                      => 'Plus and minus signs of the map zoom buttons.',
        'Line'                      => 'Separator lines between map zoom buttons.',
        'ShadowColor'               => 'Map-frame shadow. Popups additionally use their central popup shadow and opacity settings.',
        'CurrentColor'              => 'Current position markers, current-device stripes and the accuracy circle (8% fill opacity).',
        'StaleColor'                => 'Last-known-position markers and stale-device stripes.',
        'Land'                      => 'Land and background layers of the vector map.',
        'Water'                     => 'Water layers of the vector map.',
        'Park'                      => 'Parks and green areas of the vector map.',
        'Road'                      => 'Road layers of the vector map.',
        'Building'                  => 'Building layers of the vector map.',
        'MapText'                   => 'Place and street labels on the map, independently of the IPSView text colors.'
    ];
    private const WOIST_GUIDE_LABELS = [
        'ViewBackground'            => ['color.view_background', 'View background'],
        'ControlBackground'         => ['color.control_background', 'Control background'],
        'PageBackground'            => ['color.page_background', 'Page background'],
        'ControlActiveBackground'   => ['color.control_active_background', 'Active control background'],
        'ControlInactiveBackground' => ['color.control_inactive_background', 'Inactive control background'],
        'LabelText'                 => ['color.label_text', 'Label text'],
        'Text'                      => ['color.text_primary', 'Primary text'],
        'TextActive'                => ['color.text_active', 'Active text'],
        'TextInactive'              => ['color.text_inactive', 'Inactive text'],
        'Border'                    => ['color.border', 'Border color'],
        'Accent'                    => ['color.accent', 'Accent color'],
        'Warning'                   => ['color.warning', 'Warning status'],
        'PopupBackground'           => ['color.popup_background', 'Popup background'],
        'PopupBorder'               => ['color.popup_border', 'Popup border'],
        'Icon'                      => ['color.icon', 'Icon color'],
        'Line'                      => ['color.line', 'Line color'],
        'ShadowColor'               => ['color.shadow', 'Shadow color'],
        'CurrentColor'              => ['', 'Current position color'],
        'StaleColor'                => ['', 'Last known position color'],
        'Land'                      => ['land', 'Land color', 'OpenFreeMapHelper'],
        'Water'                     => ['water', 'Water color', 'OpenFreeMapHelper'],
        'Park'                      => ['park', 'Green areas color', 'OpenFreeMapHelper'],
        'Road'                      => ['road', 'Road color', 'OpenFreeMapHelper'],
        'Building'                  => ['building', 'Building color', 'OpenFreeMapHelper'],
        'MapText'                   => ['text', 'Label color', 'OpenFreeMapHelper']
    ];

    private function WoIstColorGuideForm(): array
    {
        return $this->BuildDesignerColorGuide(
            'WoIstIPSViewColorGuide',
            'Color guide: WoIst map',
            'Schematic WoIst example: toolbar, map, popup and device list. These are example colors, not a live preview of unsaved settings.',
            'color-guide',
            self::WOIST_GUIDE_ROLES,
            self::WOIST_GUIDE_LABELS,
            [
                'IPSView style controls the surrounding HTML. Position colors and OpenFreeMap palettes are configured separately; Positive, Information and Critical do not replace position colors.',
                'When Tile map settings are used, position colors and the OpenFreeMap profile come from Tile. IPSView colors and typography remain independent.',
                'Custom land, water, green-area, road, building and label colors apply only when custom map colors are enabled, to compatible visible vector layers in the light or dark palette.',
                'Marker outlines are fixed white. The selected marker is larger with a thicker outline; Accent affects the selected device card only.'
            ]
        );
    }
}
