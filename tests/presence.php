<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

class PresenceFixture extends iCloudPresence
{
    public int $clock = 1700000100;
    public array $fieldUpdates = [];

    public function timerInterval(): int
    {
        return $this->GetTimerInterval('Evaluate');
    }

    public function seedLegacy(array $state, string $identity): void
    {
        $this->WriteAttributeInteger('LayoutVersion', 0);
        $this->WriteAttributeString('EvaluationState', json_encode($state, JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('SourceIdentity', $identity);
    }

    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        if ($Parameter === 'columns.4.edit') {
            if (!is_string($Value) || !is_object(json_decode($Value, false, 512, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('A list editor update must be a JSON-encoded object.');
            }
        }
        $this->fieldUpdates[$Field][$Parameter] = $Value;
        return true;
    }

    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Presence/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function PresenceNow(): int
    {
        return $this->clock;
    }
}

$presenceModule = '{00438FA2-CF5C-45B0-AFF0-A5A5CE66595E}';
$metadata = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$loaded = $metadata->getValue();
$loaded[$presenceModule]['Class'] = PresenceFixture::class;
$metadata->setValue(null, $loaded);
$zones = [
    ['id' => 'home', 'name' => 'Synthetic home', 'latitude' => 0, 'longitude' => 0, 'radius' => 100, 'reference' => true],
    ['id' => 'work', 'name' => 'Synthetic work', 'latitude' => 0, 'longitude' => 0.01, 'radius' => 150, 'reference' => false]
];
$device = $syntheticDevice;
$device['location'] = ['latitude' => 0.0, 'longitude' => 0.0, 'horizontalAccuracy' => 5, 'timestamp' => 1700000000, 'isOld' => false];
$firstBoundary->result = $envelope;
$firstBoundary->result['devices'] = [$device];
$firstBoundary->commands = [];
$id = IPS_CreateInstance($presenceModule);
$presence = IPS\InstanceManager::getInstanceInterface($id);
IPS_ConnectInstance($id, $firstAccount);
IPS_SetProperty($id, 'Devices', json_encode([['deviceID' => 'synthetic-device', 'name' => '']], JSON_THROW_ON_ERROR));
IPS_SetProperty($id, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
$memberValue = static fn (string $ident): mixed => $value($id, $presence->GetDeviceVariableIdent('synthetic-device', $ident));
IPS_ApplyChanges($id);
$check($memberValue('Presence') === 1 && $memberValue('ZoneID') === 'home', 'Initial confident reference position must be present.');
$check($memberValue('Distance') === 0.0 && $memberValue('DistanceAvailable') === true, 'Reference-centre distance is valid zero.');
$check($memberValue('StayDuration') === 100 && $memberValue('StayAvailable') === true, 'Observed stay starts with the accepted fix.');
$check($presence->timerInterval() === 15000, 'A local age/duration timer must be enabled.');
$parents = json_decode($presence->GetCompatibleParents(), true, 512, JSON_THROW_ON_ERROR);
$check($parents === ['type' => 'connect', 'moduleIDs' => [$accountModule]], 'Presence must share the official account splitter.');
$beforeIDs = IPS_GetChildrenIDs($id);
IPS_ApplyChanges($id);
$check(IPS_GetChildrenIDs($id) === $beforeIDs && $memberValue('StayDuration') === 100, 'Repeated ApplyChanges retains variable IDs and stay start.');
$presentation = IPS_GetVariablePresentation(IPS_GetObjectIDByIdent('Presence', $id));
$check(($presentation['INTERVALS_ACTIVE'] ?? false) && !isset($presentation['OPTIONS']), 'Presence must use native numeric state labels.');
$check(array_column(json_decode($presentation['INTERVALS'], true), 'ConstantValue') === ['Unknown', 'Absent', 'Present'], 'Presence needs explicit readable labels.');
foreach ($beforeIDs as $variableID) {
    $check(!HasAction($variableID), 'Presence variables must remain read only.');
}
$form = json_decode(IPS_GetConfigurationForm($id), true, 512, JSON_THROW_ON_ERROR);
$check($form['elements'][1]['columns'][0]['edit']['options'][1]['value'] === 'synthetic-device', 'Device selector must use stable cached identity.');
$presence->ReceiveData('{invalid');
$check($memberValue('Presence') === 1, 'Malformed notifications cannot alter accepted state.');

$presence->clock = 1700000110;
$firstBoundary->result['devices'][0]['location']['longitude'] = 0.002;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$firstBoundary->broadcast();
$check($memberValue('Presence') === 1 && $memberValue('TransitionPending') === true, 'An exit starts confirmation, without immediate false absence.');
$presence->clock = 1700000150;
ICPR_Update($id);
$check($memberValue('TransitionPending') === true, 'Repeated timer cache reads must not confirm exits.');
$presence->clock++;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$firstBoundary->broadcast();
$check($memberValue('Presence') === 0 && $memberValue('ZoneID') === '' && $memberValue('StayDuration') === 0, 'A later exit fix must become absent and outside.');

$presence->clock = 1700000210;
$firstBoundary->result['devices'][0]['location']['longitude'] = 0.01;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$firstBoundary->broadcast();
$presence->clock = 1700000241;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$firstBoundary->broadcast();
$check($memberValue('Presence') === 0 && $memberValue('ZoneID') === 'work', 'A confirmed other zone remains absent from home.');
$presence->clock += 30;
ICPR_Update($id);
$check($memberValue('StayDuration') === 30, 'Local timer must advance duration in another zone.');
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 1000;
$firstBoundary->broadcast();
$check($memberValue('Presence') === -1 && !$memberValue('DistanceAvailable') && !$memberValue('StayAvailable'), 'Poor GPS must be unknown rather than absent.');
$presence->clock++;
$firstBoundary->result['devices'][0]['location']['horizontalAccuracy'] = 5;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$firstBoundary->broadcast();
$check($memberValue('ZoneID') === 'work' && $memberValue('StayDuration') === 0, 'Recovery starts a newly observed stay.');
$firstBoundary->result['state'] = 'expired';
$firstBoundary->broadcast();
$check($memberValue('Presence') === -1 && !$memberValue('DataAvailable'), 'Expired account must invalidate presence.');
$firstBoundary->result['state'] = 'authenticated';
$firstBoundary->broadcast();
$check($memberValue('Presence') === -1, 'Old cached fix must not bridge an account outage.');
$presence->clock += 20;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$firstBoundary->broadcast();
$check($memberValue('ZoneID') === 'work', 'A new fix must recover after account interruption.');
$presence->clock += 901;
ICPR_Update($id);
$check($memberValue('Presence') === -1 && $memberValue('StayDuration') === 0, 'Local timer must expire positions without an Apple refresh.');

$firstBoundary->result['devices'] = [];
ICPR_Update($id);
$check(IPS_GetInstance($id)['InstanceStatus'] === 203 && !$memberValue('DataAvailable'), 'Removed devices must not keep a presence assertion.');
$firstBoundary->result['devices'] = [$device];

// Zone and account identity changes reset evaluation, without changing variables.
$presence->clock++;
$firstBoundary->result['devices'][0]['location']['timestamp'] = $presence->clock;
$zones[0]['radius'] = 5000;
IPS_SetProperty($id, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($id);
$check($memberValue('Presence') === 1 && $memberValue('StayDuration') === 0, 'Changed geometry must start a new evaluation.');
$secondBoundary->result = $firstBoundary->result;
$secondBoundary->result['devices'][0]['location']['longitude'] = 1;
IPS_DisconnectInstance($id);
IPS_ConnectInstance($id, $secondAccount);
IPS_ApplyChanges($id);
$check($memberValue('Presence') === 0 && IPS_GetChildrenIDs($id) === $beforeIDs, 'Another account with the same device ID must not inherit previous presence.');
IPS_SetProperty($id, 'Enabled', false);
$commands = count($secondBoundary->commands);
IPS_ApplyChanges($id);
$check($memberValue('Presence') === -1 && $presence->timerInterval() === 0 && count($secondBoundary->commands) === $commands, 'Disabled module must stop timer and cache requests.');
IPS_SetProperty($id, 'Enabled', true);
$zones[1]['reference'] = true;
IPS_SetProperty($id, 'Zones', json_encode($zones, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($id);
$check(IPS_GetInstance($id)['InstanceStatus'] === 201 && $presence->timerInterval() === 0, 'Multiple reference zones must be rejected.');
IPS_SetProperty($id, 'Zones', '{invalid');
IPS_ApplyChanges($id);
$check(IPS_GetInstance($id)['InstanceStatus'] === 201 && $memberValue('Presence') === -1, 'Malformed configuration must fail safely.');

// Existing configurator creates presence instances independently of device instances.
$firstBoundary->result = $envelope;
$form = json_decode(IPS_GetConfigurationForm($configurator), true, 512, JSON_THROW_ON_ERROR);
$recipe = $form['elements'][1]['values'][0]['create'];
$check($recipe === ['moduleID' => $presenceModule, 'configuration' => ['Devices' => '[]']], 'Presence recipe must create an empty multi-device group.');
$created = IPS_CreateInstance($recipe['moduleID']);
IPS_ConnectInstance($created, $firstAccount);
IPS_SetConfiguration($created, json_encode($recipe['configuration'], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($created);
$check(IPS_GetInstance($created)['InstanceStatus'] === 201, 'Created presence group must request devices and zones without throwing.');
$form = json_decode(IPS_GetConfigurationForm($configurator), true, 512, JSON_THROW_ON_ERROR);
$check($form['elements'][1]['values'][0]['instanceID'] === $created, 'Presence instance matching must be account scoped.');
$check(array_diff($firstBoundary->commands, ['GetDevices']) === [], 'Presence and its forms must never refresh Apple.');
$check(array_diff($secondBoundary->commands, ['GetDevices']) === [], 'Evaluation must only read account caches.');
echo "Presence zones, quality, timers, source identity and configurator contracts passed.\n";
