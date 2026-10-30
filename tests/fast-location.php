<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account-protection.php';
ob_end_clean();

$a = $newAccount();
$accountID = $idOf($a);
$timerInterval = static function () use ($a): int
{
    $delegate = (new ReflectionProperty(IPSModuleStrict::class, 'module'))->getValue($a);
    $timers = (new ReflectionProperty(IPSModule::class, 'timers'))->getValue($delegate);
    return $timers['Refresh']['millis'];
};
$check($timerInterval() === 300000, 'Existing accounts must retain their configured passive interval.');
$refreshOK($a);
$check($a->RefreshDevices() && $a->transport->requests[0]['body']['clientContext']['shouldLocate'] === false, 'Fast mode must be opt-in.');
IPS_SetProperty($accountID, 'FastLocationUpdates', true);
IPS_ApplyChanges($accountID);
$check($timerInterval() === 60000 && IPS_GetProperty($accountID, 'RefreshInterval') === 300, 'Fast mode must use one minute without overwriting the saved interval.');
$refreshOK($a);
$check($a->RefreshDevices() && $a->transport->requests[1]['body']['clientContext']['shouldLocate'] === true, 'Fast refresh must explicitly request a fresh Apple position.');
$reservedAt = $attrs($a)['LastLocationRequest'];
$refreshOK($a);
$check($a->RefreshDevices() && $a->transport->requests[2]['body']['clientContext']['shouldLocate'] === false, 'Repeated manual refreshes must not repeat active location requests.');
IPS_ApplyChanges($accountID);
$a->clock += 59;
$refreshOK($a);
$check($a->RefreshDevices() && $a->transport->requests[3]['body']['clientContext']['shouldLocate'] === false, 'ApplyChanges must not reset the one-minute reservation.');
$a->clock++;
$refreshOK($a);
$check($a->RefreshDevices() && $a->transport->requests[4]['body']['clientContext']['shouldLocate'] === true, 'A new position may be requested after one minute.');
$check($attrs($a)['LastLocationRequest'] === $reservedAt + 60, 'Location request reservations must be persisted.');

$restartedID = IPS_CreateInstance($accountModule);
$restarted = IPS\InstanceManager::getInstanceInterface($restartedID);
$restarted->ApplyPersistence($a->GetPersistence());
$restarted->clock = $a->clock + 1;
$restarted->ApplyChanges();
$restarted->transport = new AccountFixtureTransport();
$refreshOK($restarted);
$check($restarted->RefreshDevices() && $restarted->transport->requests[0]['body']['clientContext']['shouldLocate'] === false, 'A genuinely restored account must retain the active-location reservation.');

// Session renewal must not resend an uncertain active location request.
$a->clock += 60;
$a->transport->enqueue('POST', '/refreshClient', 421);
$a->transport->enqueue('POST', '/accountLogin', 200, ['dsInfo' => ['dsid' => '123'], 'webservices' => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com']]]);
$refreshOK($a);
$check($a->RefreshDevices(), 'An existing session may renew without another password login.');
$requests = array_slice($a->transport->requests, -3);
$check($requests[0]['body']['clientContext']['shouldLocate'] === true && $requests[2]['body']['clientContext']['shouldLocate'] === false, 'Session renewal must retry inventory passively.');

$before = count($a->transport->requests);
IPS_SetProperty($accountID, 'PauseRequests', true);
IPS_ApplyChanges($accountID);
$check(!$a->RefreshDevices() && count($a->transport->requests) === $before && $timerInterval() === 0, 'Fast mode must respect account pause.');
IPS_SetProperty($accountID, 'PauseRequests', false);
IPS_ApplyChanges($accountID);
$a->clock += 60;
$a->transport->enqueue('POST', '/refreshClient', 429, [], ['retry-after' => '600']);
$check(!$a->RefreshDevices() && $timerInterval() >= 600000, 'Apple cooldown must take priority over fast polling.');
$before = count($a->transport->requests);
$check(!$a->RefreshDevices() && count($a->transport->requests) === $before, 'Fast mode must not bypass a cooldown.');
IPS_SetProperty($accountID, 'FastLocationUpdates', false);
IPS_ApplyChanges($accountID);
$check(IPS_GetProperty($accountID, 'RefreshInterval') === 300, 'Disabling fast mode must preserve the original interval.');
$a->clock = $attrs($a)['RetryAt'] + 1;
IPS_ApplyChanges($accountID);
$check($timerInterval() === 300000, 'After cooldown, disabling fast mode must restore the original polling interval.');

$blocked = $newAccount();
IPS_SetProperty($idOf($blocked), 'FastLocationUpdates', true);
$setAttribute($blocked, 'AccountBlock', 'accessDenied');
$blocked->ApplyChanges();
$check(!$blocked->RefreshDevices() && count($blocked->transport->requests) === 0, 'Fast mode must respect persistent account blocks.');

echo "Optional active location requests, one-minute reservation, passive renewal and account protection passed.\n";
