<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account.php';
ob_end_clean();

$check(method_exists($account, 'GetAccountDiagnostics') && method_exists($account, 'ResumeAccountRequests'), 'Account protection must expose safe diagnostics and explicit local recovery.');
$check(count(json_decode($account->GetPersistence()['attributes']['AuthenticationAttempts'], true)['resend']) === 1, 'The automatic code delivery during initial sign-in must count toward the same delivery limit.');

$newAccount = static function (string $state = 'authenticated') use ($accountModule, $sessionJSON, $result): AccountFixtureModule
{
    $id = IPS_CreateInstance($accountModule);
    $a = IPS\InstanceManager::getInstanceInterface($id);
    IPS_SetProperty($id, 'Enabled', true);
    IPS_SetProperty($id, 'Username', 'private-user@example.invalid');
    IPS_SetProperty($id, 'Password', 'PRIVATE-PASSWORD');
    IPS_SetProperty($id, 'EnablePhotos', true);
    IPS_ApplyChanges($id);
    $a->clock = time();
    $a->transport = new AccountFixtureTransport();
    $saved = $a->GetPersistence();
    $session = json_decode($sessionJSON, true, 512, JSON_THROW_ON_ERROR);
    $session['state'] = $state;
    $session['photos'] = 'https://p01-ckdatabasews.icloud.com';
    $session['challengeAt'] = time();
    $saved['attributes']['Session'] = json_encode($session, JSON_THROW_ON_ERROR);
    $saved['attributes']['DeviceCache'] = json_encode(['devices' => $result['devices'], 'updatedAt' => time()], JSON_THROW_ON_ERROR);
    $saved['attributes']['PhotoAccess'] = true;
    $saved['attributes']['PhotoAlbums'] = json_encode(['albums' => [['id' => 'private-album', 'name' => 'Private album', 'kind' => 'album']], 'updatedAt' => time()], JSON_THROW_ON_ERROR);
    $a->ApplyPersistence($saved);
    $a->ApplyChanges();
    return $a;
};
$attrs = static fn ($a) => $a->GetPersistence()['attributes'];
// The stub's persistence does not contain the ID; resolve the registered object.
$idOf = static function ($a): int
{
    return (new ReflectionProperty($a, 'InstanceID'))->getValue($a);
};
$setAttribute = static function ($a, string $name, mixed $value): void
{
    $saved = $a->GetPersistence();
    $saved['attributes'][$name] = $value;
    $a->ApplyPersistence($saved);
};
$refreshOK = static function ($a) use ($rawDevice): void
{
    $a->transport->enqueue('POST', '/refreshClient', 200, ['content' => [$rawDevice]]);
};
$forward = static fn ($a, $command, $arguments = []) => json_decode($a->ForwardData(json_encode(['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => $command] + $arguments, JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);

// Pause is not logout. It suppresses every Apple path, including explicit side effects.
$a = $newAccount();
$beforeSession = $attrs($a)['Session'];
$beforeCache = $attrs($a)['DeviceCache'];
IPS_SetProperty($idOf($a), 'PauseRequests', true);
$a->ApplyChanges();
$check(!$a->Login() && !$a->RefreshDevices() && !$a->VerifyCode('123456') && !$a->ResendCode(), 'Paused authentication and refresh paths must return without HTTP.');
$check(!$forward($a, 'PlaySound', ['DeviceID' => 'synthetic-device'])['success'], 'Pause must also suppress a sound command.');
$check(!$a->RefreshPhotoAlbums() && !$forward($a, 'GetPhotoPage', ['AlbumID' => 'private-album', 'Offset' => 0])['success'], 'Pause must suppress Photos catalog and page requests.');
$check($a->transport->requests === [] && $attrs($a)['Session'] === $beforeSession && $attrs($a)['DeviceCache'] === $beforeCache, 'Pause must retain session, trusted-client identity and cached inventory byte for byte.');
$check(IPS_GetInstance($idOf($a))['InstanceStatus'] === 207, 'Pause requires its own visible status.');
$check(json_decode($a->GetDevices(), true)['success'] && json_decode($a->GetPhotoAlbums(), true)['success'], 'Local cached data must remain readable during a pause.');
IPS_SetProperty($idOf($a), 'PauseRequests', false);
$a->ApplyChanges();
$refreshOK($a);
$check($a->RefreshDevices() && count($a->transport->requests) === 1, 'Unpausing must reuse the existing session.');
$check($a->Login() && count($a->transport->requests) === 1, 'Sign in while already authenticated must not submit another password or refresh implicitly.');

// A failed token renewal must not loop at the next timer tick.
$a = $newAccount();
$a->transport->enqueue('POST', '/refreshClient', 401);
$a->transport->enqueue('POST', '/accountLogin', 403, ['error' => 'PRIVATE-RESPONSE']);
$check(!$a->RefreshDevices() && count($a->transport->requests) === 2, 'A rejected renewal must use exactly one token renewal and no password login.');
$check($attrs($a)['AccountBlock'] === 'accessDenied' && json_decode($attrs($a)['Session'], true)['state'] === 'expired', 'Denied renewal must persist an expired session and a manual hold.');
$check(!$a->RefreshDevices() && !$a->Login() && count($a->transport->requests) === 2, 'Manual and timer paths must respect the persistent hold.');
$a->ApplyChanges();
$check(IPS_GetInstance($idOf($a))['InstanceStatus'] === 208, 'Applying settings must not silently release an authentication hold.');
$a->ResumeAccountRequests();
$check(count($a->transport->requests) === 2 && $attrs($a)['AccountBlock'] === '', 'Explicit recovery must be local and must not authenticate automatically.');
$check(!$a->RefreshDevices() && count($a->transport->requests) === 2, 'An expired session must still require explicit sign-in after local recovery.');

// Lock responses and ordinary throttling have different recovery policies.
$a = $newAccount();
$a->transport->enqueue('POST', '/refreshClient', 423);
$check(!$a->RefreshDevices() && $attrs($a)['AccountBlock'] === 'accountLocked' && $attrs($a)['RetryAt'] === 0, 'HTTP 423 must be a manual hold, not an automatic short retry.');
$a->clock += 86400;
$check(!$a->RefreshDevices() && count($a->transport->requests) === 1, 'A lock response must not expire into an automatic retry.');
$a->ResumeAccountRequests();
$refreshOK($a);
$check($a->RefreshDevices(), 'After explicit review, a retained valid session may be used again.');

$a = $newAccount();
$a->transport->enqueue('POST', '/refreshClient', 429, [], ['retry-after' => '720']);
$check(!$a->RefreshDevices() && $attrs($a)['AccountBlock'] === '' && $attrs($a)['RetryAt'] === $a->clock + 720, 'HTTP 429 must honor Retry-After without becoming a permanent security hold.');
$until = $attrs($a)['RetryAt'];
$a->ResumeAccountRequests();
$a->ClearAccountDiagnostics();
$a->Logout();
IPS_SetProperty($idOf($a), 'Password', 'CHANGED-PRIVATE-PASSWORD');
$a->ApplyChanges();
$check($attrs($a)['RetryAt'] === $until && !$a->Login() && count($a->transport->requests) === 1, 'Recovery, log clearing, logout and credential changes must not bypass an Apple cooldown.');

// Password limits survive explicit recovery, logout, credential changes and a new object.
$a = $newAccount('signedOut');
$start = $a->clock;
for ($i = 0; $i < 3; $i++) {
    $a->clock = $start + $i * 60;
    $a->transport->enqueue('POST', '/signin/init', 401, ['error' => 'PRIVATE-RESPONSE']);
    $check(!$a->Login() && count($a->transport->requests) === $i + 1, 'Each permitted login attempt must make one request, without retry.');
    $a->ResumeAccountRequests();
    $check(!$a->Login() && count($a->transport->requests) === $i + 1, 'Immediate repeat clicks must respect the minimum login spacing.');
}
$a->Logout();
IPS_SetProperty($idOf($a), 'Password', 'CHANGED-PRIVATE-PASSWORD');
$a->ApplyChanges();
$a->clock = $start + 180;
$check(!$a->Login() && count($a->transport->requests) === 3, 'A fourth password attempt in the rolling window must be blocked.');
$copy = $newAccount('signedOut');
$copy->ApplyPersistence($a->GetPersistence());
$copy->clock = $start + 181;
$copy->ApplyChanges();
$check(!$copy->Login() && $copy->transport->requests === [], 'Restarting the module must not reset password attempt history.');
$copy->clock = $start + 1800;
$copy->transport->enqueue('POST', '/signin/init', 401);
$check(!$copy->Login() && count($copy->transport->requests) === 1, 'The oldest attempt must expire exactly at the rolling-window boundary.');

// Code checking and code delivery have independent limits; codes are never stored.
$a = $newAccount('awaiting2FA');
$check(!$a->Login() && $a->transport->requests === [], 'An existing challenge must not restart password authentication.');
for ($i = 0; $i < 5; $i++) {
    $a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 400);
    $check(!$a->VerifyCode('999999') && count($a->transport->requests) === $i + 1, 'Five code checks must be allowed per window.');
}
$check(!$a->VerifyCode('999999') && count($a->transport->requests) === 5, 'The sixth code check must not reach Apple.');
$a = $newAccount('awaiting2FA');
$start = $a->clock;
for ($i = 0; $i < 3; $i++) {
    $a->clock = $start + $i * 60;
    $a->transport->enqueue('PUT', '/verify/trusteddevice/securitycode', 204);
    $check($a->ResendCode(), 'A spaced code request within its window must succeed.');
    $check(!$a->ResendCode() && count($a->transport->requests) === $i + 1, 'Repeated code requests must be suppressed.');
}
$a->clock = $start + 180;
$check(!$a->ResendCode() && count($a->transport->requests) === 3, 'A fourth code delivery must not reach Apple.');

// A Photos-only approval failure must not disable Find My; an expired token must.
$a = $newAccount();
$a->transport->enqueue('POST', '/records/query', 403);
$check(!$a->RefreshPhotoAlbums() && $attrs($a)['AccountBlock'] === '', 'A Photos permission refusal must stay scoped to Photos.');
$refreshOK($a);
$check($a->RefreshDevices(), 'Find My must remain usable after a Photos-only denial.');
$a = $newAccount();
$a->transport->enqueue('POST', '/records/query', 401);
$check(!$a->RefreshPhotoAlbums() && $attrs($a)['AccountBlock'] === 'authentication', 'An expired Photos token must stop shared authenticated requests.');
$check(!$a->RefreshDevices() && count($a->transport->requests) === 1, 'Find My must not continue after the shared token expired.');
$a = $newAccount();
$a->transport->enqueue('POST', '/records/query', 429, [], ['retry-after' => '120']);
$check(!$a->RefreshPhotoAlbums() && !$a->RefreshDevices() && count($a->transport->requests) === 1, 'A Photos throttle must stop other Apple requests for the same account too.');

// Transient transport/server failures back off without being called bad credentials.
$a = $newAccount();
foreach ([60, 120] as $delay) {
    $a->transport->enqueue('POST', '/refreshClient', 503);
    $check(!$a->RefreshDevices() && $attrs($a)['RetryAt'] === $a->clock + $delay && $attrs($a)['AccountBlock'] === '', 'Server failures require increasing temporary cooldowns, not an account lock.');
    $check(IPS_GetInstance($idOf($a))['InstanceStatus'] === 210, 'A local connection backoff must not be labelled as an Apple throttle.');
    $count = count($a->transport->requests);
    $check(!$a->RefreshDevices() && count($a->transport->requests) === $count, 'Temporary cooldown must suppress transport.');
    $a->clock += $delay;
}
$refreshOK($a);
$check($a->RefreshDevices() && $attrs($a)['TransientFailures'] === 0, 'A successful request must reset transient backoff.');

// New protection fields must default safely when restoring a legacy installation.
$legacy = $a->GetPersistence();
foreach (['AuthenticationAttempts', 'AccountBlock', 'AccountDiagnostics', 'TransientFailures', 'RetryReason', 'WasRequestsPaused'] as $name) {
    unset($legacy['attributes'][$name]);
}
unset($legacy['configuration']['PauseRequests']);
$migratedID = IPS_CreateInstance($accountModule);
$migrated = IPS\InstanceManager::getInstanceInterface($migratedID);
$migrated->ApplyPersistence($legacy);
$migrated->ApplyChanges();
$migrated->ApplyChanges();
$check($attrs($migrated)['Session'] === $legacy['attributes']['Session'] && $attrs($migrated)['DeviceCache'] === $legacy['attributes']['DeviceCache'], 'Adding protection defaults must preserve a legacy session and cache through repeated ApplyChanges.');

// Bound the stored history and prove it contains only fixed categories and numeric metadata.
for ($i = 0; $i < 30; $i++) {
    $refreshOK($a);
    $check($a->RefreshDevices(), 'Synthetic diagnostic refresh failed.');
}
$diagnostics = $a->GetAccountDiagnostics();
$entries = json_decode($diagnostics, true, 512, JSON_THROW_ON_ERROR);
$check(count($entries) === 50, 'Only the most recent 50 diagnostic records may persist.');
foreach ($entries as $entry) {
    $check(array_keys($entry) === ['time', 'operation', 'httpStatus', 'reason', 'waitSeconds'], 'Diagnostic records must use an exact allowlist of fields.');
}
foreach (['private-user', 'PRIVATE-PASSWORD', 'PRIVATE-RESPONSE', 'SYNTHETIC-TOKEN-SECRET', 'SYNTHETIC-TRUST-SECRET', 'SYNTHETIC-COOKIE-SECRET', 'synthetic-device', 'private-album', 'https://', '999999'] as $secret) {
    $check(!str_contains($diagnostics, $secret), 'Diagnostics must not disclose credentials, codes, URLs or user content.');
}
$setAttribute($a, 'AccountDiagnostics', json_encode([['time' => 1, 'operation' => 'https://secret.invalid', 'reason' => 'PRIVATE-PASSWORD', 'httpStatus' => 200, 'waitSeconds' => 0]], JSON_THROW_ON_ERROR));
$check($a->GetAccountDiagnostics() === '[]', 'Malformed or injected diagnostic fields must be excluded on export.');
$setAttribute($a, 'AuthenticationAttempts', '{invalid');
$a->Logout();
$check(!$a->Login() && $attrs($a)['AccountBlock'] === 'protectionState', 'Corrupt protection persistence must fail closed instead of resetting limits.');
$a->ResumeAccountRequests();
$check($attrs($a)['AccountBlock'] === 'protectionState', 'Explicit recovery must not release corrupt protection persistence.');
$before = count($a->transport->requests);
$form = $a->GetConfigurationForm();
$check(count($a->transport->requests) === $before && str_contains($form, 'Account diagnostics'), 'Opening the diagnostic form must remain read-only and must display the bounded history.');

echo "Persistent account limits, pause, authentication holds, error separation, recovery and private diagnostics passed.\n";
