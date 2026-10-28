<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Instance-wide display order; no property edits, authentication or Apple requests. */
trait WoIstDeviceOrder
{
    /** @return bool Whether a bounded, explicit move or alphabetical reset was saved. */
    public function ChangeDeviceOrder(string $JSON): bool
    {
        return $this->CloudDebugCall(__FUNCTION__, __LINE__, function () use ($JSON): bool
        {
            $lock = 'SymconiCloud.WoIstOrder.' . $this->InstanceID;
            if (!\IPS_SemaphoreEnter($lock, 0)) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Eine andere Sortieraktion läuft bereits. Diese Anfrage wird nicht gespeichert; nach deren Abschluss erneut versuchen.');
                return false;
            }
            try {
                if (strlen($JSON) > 1024 || !$this->configurationValid()) {
                    throw new \InvalidArgumentException();
                }
                $operation = json_decode($JSON, true, 4, JSON_THROW_ON_ERROR);
                $reset = $operation === ['reset' => true];
                $data = $this->request('GetDevices');
                $visible = $this->FilterWoIstDeviceData($data)['devices'] ?? [];
                $available = array_column($visible, 'selectionKey');
                $order = $reset ? [] : $this->WoIstMergedOrder($data);
                if ($reset) {
                    $devices = $data['devices'];
                    if (count($devices) > 4096) {
                        throw new \LengthException();
                    }
                    usort($devices, static fn (array $left, array $right): int => strnatcasecmp($left['name'] ?? '', $right['name'] ?? '') ?: strcmp($left['selectionKey'], $right['selectionKey']));
                    $order = array_column($devices, 'selectionKey');
                } else {
                    if (!is_array($operation) || count($operation) !== 2 || !is_string($operation['device'] ?? null) || !is_string($operation['before'] ?? null)
                        || !in_array($operation['device'], $available, true) || ($operation['before'] !== '' && !in_array($operation['before'], $available, true))
                        || $operation['device'] === $operation['before']) {
                        throw new \InvalidArgumentException();
                    }
                    $order = array_values(array_filter($order, static fn (string $key): bool => $key !== $operation['device']));
                    $index = $operation['before'] === '' ? count($order) : array_search($operation['before'], $order, true);
                    array_splice($order, $index, 0, [$operation['device']]);
                }
                $this->WriteAttributeString('WoIstDeviceOrder', json_encode($order, JSON_THROW_ON_ERROR));
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', $reset ? 'Geräteliste alphabetisch zurückgesetzt.' : 'Gerätereihenfolge für Kachel und IPSView gespeichert; nur Kontocaches gelesen.', ['GespeicherteEinträge' => count($order)]);
                try {
                    $this->publish($data);
                } catch (\Throwable) {
                    $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Reihenfolge ist gespeichert; die Darstellung konnte noch nicht aktualisiert werden und folgt mit dem nächsten Cacheupdate.');
                }
                return true;
            } catch (\Throwable) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Sortierung nicht gespeichert. Anfrage oder Gerätezuordnung ist ungültig oder nicht mehr aktuell; Liste erneut laden.');
                return false;
            } finally {
                \IPS_SemaphoreLeave($lock);
            }
        });
    }

    /** Bounded transport body, isolated for offline HTTP fixtures. */
    protected function ReadWoIstOrderBody(): string
    {
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return '';
        }
        try {
            return stream_get_contents($stream, 1025) ?: '';
        } finally {
            fclose($stream);
        }
    }

    private function WoIstMergedOrder(array $data): array
    {
        $raw = $this->ReadAttributeString('WoIstDeviceOrder');
        $order = strlen($raw) <= 524288 ? json_decode($raw, true) : null;
        if (!is_array($order) || !array_is_list($order)) {
            $order = [];
        }
        $keys = [];
        foreach ($order as $key) {
            if (is_string($key) && preg_match('/\Aaccount:[1-9][0-9]{0,9}:[a-f0-9]{64}\z/D', $key) === 1) {
                $keys[$key] = true;
            }
        }
        foreach ($data['devices'] ?? [] as $device) {
            if (is_array($device) && is_string($device['selectionKey'] ?? null)) {
                $keys[$device['selectionKey']] = true;
            }
        }
        if (count($keys) > 4096) {
            throw new \LengthException('Device order limit reached.');
        }
        return array_keys($keys);
    }

    private function RememberWoIstDeviceOrder(array $data): void
    {
        $lock = 'SymconiCloud.WoIstOrder.' . $this->InstanceID;
        if (!\IPS_SemaphoreEnter($lock, 0)) {
            return;
        }
        try {
            $raw = json_encode($this->WoIstMergedOrder($data), JSON_THROW_ON_ERROR);
            if ($raw !== $this->ReadAttributeString('WoIstDeviceOrder')) {
                $this->WriteAttributeString('WoIstDeviceOrder', $raw);
            }
        } catch (\Throwable) {
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Gerätereihenfolge konnte nicht ergänzt werden; gespeicherter Stand bleibt erhalten.');
        } finally {
            \IPS_SemaphoreLeave($lock);
        }
    }

    private function OrderedWoIstData(array $data): array
    {
        try {
            $ranks = array_flip($this->WoIstMergedOrder($data));
            usort($data['devices'], static fn (array $left, array $right): int => $ranks[$left['selectionKey']] <=> $ranks[$right['selectionKey']]);
        } catch (\Throwable) {
            // A damaged display order never prevents showing the cached devices.
        }
        return $data;
    }
}
