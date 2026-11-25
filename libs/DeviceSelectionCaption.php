<?php

declare(strict_types=1);

namespace ICloudServices;

final class DeviceSelectionCaption
{
    public static function withModel(string $name, array $device): string
    {
        $model = is_string($device['model'] ?? null) ? trim($device['model']) : '';
        $identifier = is_string($device['modelIdentifier'] ?? null) ? trim($device['modelIdentifier']) : '';
        // Apple's display model can be generic, while the identifier distinguishes hardware generations.
        if ($identifier !== '' && $identifier !== $model) {
            $model = $model === '' ? $identifier : $model . ' · ' . $identifier;
        }
        return $model === '' ? $name : $name . ' (' . $model . ')';
    }
}
