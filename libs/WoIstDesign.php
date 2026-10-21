<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Map-specific design properties; HTML output lifecycle belongs to the central helper. */
trait WoIstDesign
{
    private const MAP_DESIGN = [
        'Height'       => [0, 0, 1200, 'Fallback map height in pixels (0 = automatic)'],
        'FontSize'     => [14, 10, 28, 'Font size in pixels'],
        'MarkerSize'   => [9, 4, 24, 'Marker radius in pixels'],
        'CurrentColor' => [0x168D80, 0, 0xFFFFFF, 'Current position color'],
        'StaleColor'   => [0xB47722, 0, 0xFFFFFF, 'Last known position color'],
        'ShowTitle'    => [true, 0, 0, 'Show heading'],
        'ShowDevices'  => [true, 0, 0, 'Show device list'],
        'ShowControls' => [true, 0, 0, 'Show map controls']
    ];

    /** Copies saved Tile settings and switches IPSView to independent editing. */
    public function CopyTileDesignToIPSView(): void
    {
        foreach (self::MAP_DESIGN as $name => [$default]) {
            IPS_SetProperty($this->InstanceID, 'IPSView' . $name, is_bool($default)
                ? $this->ReadPropertyBoolean('Tile' . $name) : $this->ReadPropertyInteger('Tile' . $name));
        }
        IPS_SetProperty($this->InstanceID, 'IPSViewUseTileDesign', false);
        IPS_ApplyChanges($this->InstanceID);
        $this->ReloadForm();
    }

    /** Updates editor availability without saving settings or querying Apple. */
    public function UpdateIPSViewDesignAvailability(bool $UseTileDesign): void
    {
        foreach (self::MAP_DESIGN as $name => $_) {
            $this->UpdateFormField('IPSView' . $name, 'enabled', !$UseTileDesign);
        }
    }

    private function RegisterWoIstDesignProperties(): void
    {
        $this->RegisterPropertyBoolean('IPSViewUseTileDesign', true);
        foreach (['Tile', 'IPSView'] as $prefix) {
            foreach (self::MAP_DESIGN as $name => [$default]) {
                if (is_bool($default)) {
                    $this->RegisterPropertyBoolean($prefix . $name, $default);
                } else {
                    $this->RegisterPropertyInteger($prefix . $name, $default);
                }
            }
        }
    }

    private function WoIstDesignForm(array $form): array
    {
        foreach (['Tile' => 'Tile designer', 'IPSView' => 'IPSView designer'] as $prefix => $caption) {
            $items = [];
            if ($prefix === 'IPSView') {
                $items = $this->IPSViewHTMLPageFormItems();
                $items[] = ['type' => 'ValidationTextBox', 'name' => 'IPSViewServerURL', 'caption' => 'Symcon server URL for IPSView (optional)'];
                $items[] = ['type' => 'Label', 'caption' => 'Use the reachable HTTP(S) server origin without a path. Required when IPSView does not provide an HTTP(S) page origin.'];
                $items[] = ['type' => 'CheckBox', 'name' => 'IPSViewUseTileDesign', 'caption' => 'Use Tile design',
                    'onChange'     => 'ICWI_UpdateIPSViewDesignAvailability($id, $IPSViewUseTileDesign);'];
                $items[] = ['type' => 'Label', 'caption' => 'Independent settings apply when Use Tile design is disabled.'];
                $items[] = ['type' => 'Button', 'caption' => 'Copy saved Tile design and edit independently', 'onClick' => 'ICWI_CopyTileDesignToIPSView($id);'];
            }
            foreach (self::MAP_DESIGN as $name => [$default, $minimum, $maximum, $label]) {
                $item = ['type' => is_bool($default) ? 'CheckBox' : (str_ends_with($name, 'Color') ? 'SelectColor' : 'NumberSpinner'),
                    'name'      => $prefix . $name, 'caption' => $label];
                if ($prefix === 'IPSView') {
                    $item['enabled'] = !$this->ReadPropertyBoolean('IPSViewUseTileDesign');
                }
                if ($item['type'] === 'NumberSpinner') {
                    $item['minimum'] = $minimum;
                    $item['maximum'] = $maximum;
                }
                $items[] = $item;
            }
            $form['elements'][] = ['type' => 'ExpansionPanel', 'caption' => $caption, 'items' => $items];
        }
        return $form;
    }

    private function WoIstDesign(bool $ipsView): array
    {
        $prefix = $ipsView && !$this->ReadPropertyBoolean('IPSViewUseTileDesign') ? 'IPSView' : 'Tile';
        $design = [];
        foreach (self::MAP_DESIGN as $name => [$default, $minimum, $maximum]) {
            $value = is_bool($default) ? $this->ReadPropertyBoolean($prefix . $name)
                : max($minimum, min($maximum, $this->ReadPropertyInteger($prefix . $name)));
            if (str_ends_with($name, 'Color')) {
                $value = sprintf('#%06x', $value);
            }
            $design[lcfirst($name)] = $value;
        }
        return $design;
    }

    private function WoIstDesignCSS(bool $ipsView): string
    {
        $design = $this->WoIstDesign($ipsView);
        $css = ':root{--woist-height:' . ($design['height'] > 0 ? $design['height'] : 480) . 'px;--woist-font-size:' . $design['fontSize']
            . 'px;--woist-current:' . $design['currentColor'] . ';--woist-stale:' . $design['staleColor'] . ';}';
        return $css;
    }
}
