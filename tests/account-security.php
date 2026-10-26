<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account-login-feedback.php';
ob_end_clean();

$check(method_exists($account, 'GetAccountSecurityDiagnostics'), 'Security events need a separate persistent history.');
$security = static fn ($a) => json_decode($a->GetAccountSecurityDiagnostics(), true, 512, JSON_THROW_ON_ERROR);

// Provider lock codes take precedence over HTTP and service-specific permission errors.
foreach ([200, 401, 403, 423, 429] as $status) {
    foreach (['-20209', -20209] as $code) {
        $a = $newAccount('signedOut');
        $a->transport->enqueue('POST', '/signin/init', $status, ['serviceErrors' => [null, ['code' => 'PRIVATE-UNKNOWN'], ['code' => $code, 'message' => 'PRIVATE-PROVIDER-MESSAGE']]]);
        $check(!$a->Login() && $attrs($a)['AccountBlock'] === 'accountLocked', 'A structured Apple lock must stop authentication regardless of HTTP status.');
        $events = $security($a);
        $check(end($events)['appleCode'] === '-20209' && end($events)['reason'] === 'accountLocked', 'The allowlisted lock code and meaning must survive without the provider message.');
        $check($status !== 429 || $attrs($a)['RetryAt'] > $a->clock, 'A structured security error must also preserve an HTTP 429 cooldown.');
        $a->RefreshDevices();
        $check(count($a->transport->requests) === 1, 'An Apple lock must not start a renewal or another request.');
    }
}
foreach (['devices', 'photos', 'contacts'] as $service) {
    $a = $newAccount();
    IPS_SetProperty($idOf($a), 'EnableContacts', true);
    $a->ApplyChanges();
    $saved = $a->GetPersistence();
    $session = json_decode($saved['attributes']['Session'], true);
    $session['contacts'] = 'https://p01-contactsws.icloud.com';
    $saved['attributes']['Session'] = json_encode($session, JSON_THROW_ON_ERROR);
    $a->ApplyPersistence($saved);
    $path = match ($service) {
        'devices' => '/refreshClient', 'photos' => '/records/query', default => '/co/startup'
    };
    $a->transport->enqueue($service === 'contacts' ? 'GET' : 'POST', $path, 403, ['serviceErrors' => [['code' => '-20209']]]);
    match ($service) {
        'devices' => $a->RefreshDevices(), 'photos' => $a->RefreshPhotoAlbums(), default => $a->RefreshContacts()
    };
    $check($attrs($a)['AccountBlock'] === 'accountLocked', 'A shared account lock must not be mistaken for a service-only refusal: ' . $service);
    $check(!$a->RefreshDevices() && count($a->transport->requests) === 1, 'A service lock must stop the other services too.');
}
foreach (['errorCode', 'serverErrorCode'] as $field) {
    $a = $newAccount('signedOut');
    $a->transport->enqueue('POST', '/signin/init', 403, [$field => '-20209']);
    $check(!$a->Login() && $attrs($a)['AccountBlock'] === 'accountLocked', 'Known scalar Apple error fields must use the same account-wide security handling.');
}

foreach ([[412, [], 'accountReviewRequired'], [200, ['termsUpdateNeeded' => true], 'termsRequired']] as [$status, $body, $reason]) {
    $a = $newAccount('signedOut');
    $a->transport->enqueue('POST', '/signin/init', $status, $body);
    $check(!$a->Login() && $attrs($a)['AccountBlock'] === $reason, 'An account repair or terms requirement must stop requests for manual review.');
    $check(!$a->RefreshDevices() && count($a->transport->requests) === 1, 'Required browser review must not cause retries.');
}
$a = $newAccount('signedOut');
$a->transport->enqueue('POST', '/signin/init', 401, ['serviceErrors' => [['code' => '-20101', 'message' => 'PRIVATE-PROVIDER-MESSAGE']]]);
$check(!$a->Login() && $attrs($a)['AccountBlock'] === 'credentials', 'A credential rejection must remain distinct from an explicit lock.');
$events = $security($a);
$check(end($events)['appleCode'] === '-20101', 'Known credential rejection codes must be retained safely.');
$check(str_contains($feedback($a), 'does not prove'), 'A rejected credential exchange must not claim that the password was definitely wrong.');

// A secondary system can retain its session without submitting new authentication requests.
$a = $newAccount();
$before = $attrs($a)['Session'];
IPS_SetProperty($idOf($a), 'AllowAuthentication', false);
$a->ApplyChanges();
$check($attrs($a)['Session'] === $before && $a->Login() && $a->transport->requests === [], 'Disabling new authentication must retain and reuse an existing session.');
$refreshOK($a);
$check($a->RefreshDevices() && count($a->transport->requests) === 1, 'Existing authenticated reads must remain usable on a secondary system.');
$check(!$a->VerifyCode('123456') && IPS_GetInstance($idOf($a))['InstanceStatus'] === 102 && $attrs($a)['LastError'] === '' && count($a->transport->requests) === 1, 'A local verification refusal must not turn a usable secondary session into an Apple error.');
$a = $newAccount('awaiting2FA');
IPS_SetProperty($idOf($a), 'AllowAuthentication', false);
$a->ApplyChanges();
$attempts = $attrs($a)['AuthenticationAttempts'];
$check(!$a->Login() && !$a->VerifyCode('123456') && !$a->ResendCode(), 'Secondary-system policy must block password, code verification and code delivery.');
$check($a->transport->requests === [] && $attrs($a)['AuthenticationAttempts'] === $attempts, 'A local authentication policy refusal must never consume an attempt or contact Apple.');
$check($attrs($a)['AccountBlock'] === '', 'Disabling authentication locally is not an Apple account lock.');
$check(IPS_GetInstance($idOf($a))['InstanceStatus'] === 211, 'A secondary system without a usable session needs a local policy status, not an Apple error.');
$setAttribute($a, 'AccountBlock', 'accountLocked');
$check(!$a->LoginFromForm(true) && $attrs($a)['AccountBlock'] === 'accountLocked' && $a->transport->requests === [], 'The secondary-system policy must not implicitly release a hold through the Sign in button.');

// Ambiguous refusal stays ambiguous; unrelated service permissions keep their old scope.
$a = $newAccount('signedOut');
$a->transport->enqueue('POST', '/signin/init', 403, ['serviceErrors' => [['code' => 'PRIVATE-UNKNOWN']]]);
$check(!$a->Login() && $attrs($a)['AccountBlock'] === 'accessDenied', 'An unknown 403 must not be presented as a confirmed Apple lock.');
$a = $newAccount('signedOut');
$a->transport->enqueue('POST', '/signin/init', 409, ['serviceErrors' => [['code' => '-20209'], ['code' => '-20101']]]);
$check(!$a->Login() && $attrs($a)['AccountBlock'] === 'accountLocked' && count($a->transport->requests) === 1, 'A lock must take priority over both a code challenge and a credential rejection.');

// Expiry on a secondary system allows at most one token renewal, never a password fallback.
$a = $newAccount();
IPS_SetProperty($idOf($a), 'AllowAuthentication', false);
$a->ApplyChanges();
$a->transport->enqueue('POST', '/refreshClient', 450);
$a->transport->enqueue('POST', '/accountLogin', 421);
$check(!$a->RefreshDevices() && count($a->transport->requests) === 2, 'Secondary-session expiry must stop after the rejected token renewal.');
$check(!$a->LoginFromForm(true) && count($a->transport->requests) === 2 && $attrs($a)['AccountBlock'] === 'authentication', 'Secondary-session recovery must not submit a new login or release its hold.');

// Older diagnostic data is backfilled once; routine traffic cannot evict security events.
$a = $newAccount();
$setAttribute($a, 'AccountSecurityDiagnostics', '');
$setAttribute($a, 'AccountDiagnostics', json_encode([['time' => $a->clock, 'operation' => 'login', 'httpStatus' => 403, 'reason' => 'accessDenied', 'waitSeconds' => 0]], JSON_THROW_ON_ERROR));
for ($i = 0; $i < 65; $i++) {
    $refreshOK($a);
    $check($a->RefreshDevices(), 'Synthetic routine reads must succeed.');
}
$events = $security($a);
$check(count($events) === 1 && $events[0]['reason'] === 'accessDenied', 'Routine success must preserve an imported authentication failure.');
$check(count(json_decode($a->GetAccountDiagnostics(), true)) === 50, 'The existing diagnostic API keeps its last-50 contract.');
$a->ClearAccountDiagnostics();
$check($security($a) === $events, 'Clearing routine diagnostics must preserve security evidence.');
$a->ApplyChanges();
$a->ApplyChanges();
$check($security($a) === $events, 'Applying settings repeatedly must not duplicate migrated security events.');
$copy = $newAccount();
$copy->ApplyPersistence($a->GetPersistence());
$check($security($copy) === $events, 'Security history must survive restoration.');
$legacy = $newAccount();
$setAttribute($legacy, 'AccountSecurityDiagnostics', '');
$setAttribute($legacy, 'AccountDiagnostics', json_encode([['time' => $legacy->clock, 'operation' => 'login', 'httpStatus' => 403, 'reason' => 'accessDenied', 'waitSeconds' => 0]], JSON_THROW_ON_ERROR));
$legacy->ClearAccountDiagnostics();
$legacyEvents = $security($legacy);
$check(count($legacyEvents) === 1 && $legacyEvents[0]['reason'] === 'accessDenied', 'Clearing recent diagnostics immediately after upgrade must preserve legacy security evidence.');
$setAttribute($a, 'AccountBlock', 'accountLocked');
$limits = $attrs($a)['AuthenticationAttempts'];
$a->ClearAccountSecurityDiagnostics();
$check($security($a) === [] && $attrs($a)['AccountBlock'] === 'accountLocked' && $attrs($a)['AuthenticationAttempts'] === $limits, 'Clearing security history must not release a hold or reset attempt limits.');

$a = $newAccount('signedOut');
for ($i = 0; $i < 110; $i++) {
    $a->clock += 1801;
    $a->ResumeAccountRequests();
    $a->transport->enqueue('POST', '/signin/init', 403, ['serviceErrors' => [['code' => 'PRIVATE-UNKNOWN', 'message' => 'PRIVATE-PROVIDER-MESSAGE']]]);
    $a->Login();
}
$events = $security($a);
$check(count($events) === 200, 'Security history must remain bounded independently of routine history.');
foreach ($events as $event) {
    $check(array_keys($event) === ['time', 'operation', 'httpStatus', 'reason', 'waitSeconds', 'appleCode'], 'Security history must export only fixed allowlisted fields.');
}
$check(!str_contains(json_encode($events), 'PRIVATE-'), 'Unknown provider codes and messages must never enter security diagnostics.');
$form = json_decode($a->GetConfigurationForm(), true);
$securityLists = array_filter($form['actions'], static fn ($item) => ($item['caption'] ?? '') === 'Account security (last 200 events, 30 days)');
$check(count($securityLists) === 1 && isset(array_values($securityLists)[0]['items'][2]['confirm']), 'The form must expose a separate security list and confirm its destructive cleanup.');
$setAttribute($a, 'AccountSecurityDiagnostics', json_encode([['time' => $a->clock, 'operation' => 'login', 'httpStatus' => 403, 'reason' => 'accountLocked', 'waitSeconds' => 0, 'appleCode' => 'PRIVATE-CODE', 'message' => 'PRIVATE-MESSAGE']], JSON_THROW_ON_ERROR));
$check(!str_contains($a->GetAccountSecurityDiagnostics(), 'PRIVATE-'), 'Imported security records must sanitize both unknown codes and extra fields.');
$a->clock += 30 * 86400 + 1;
$check($security($a) === [], 'Security history must expire after 30 days.');

echo "Apple lock classification, account review, secondary-system policy and persistent private security history passed.\n";
