<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account-security.php';
ob_end_clean();

$trustedAccount = ['dsInfo' => ['dsid' => 'synthetic-dsid', 'hsaVersion' => 2], 'hsaTrustedBrowser' => true,
    'webservices'           => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com']]];
foreach ([204, 409] as $status) {
    $a = $newAccount('awaiting2FA');
    $a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', $status, ['securityCode' => ['valid' => true]], ['scnt' => 'SYNTHETIC-ROTATED', 'x-apple-session-token' => 'SYNTHETIC-VERIFIED-TOKEN']);
    $a->transport->enqueue('GET', '/2sv/trust', 204, [], ['x-apple-twosv-trust-token' => 'SYNTHETIC-TRUST-TOKEN']);
    $a->transport->enqueue('POST', '/accountLogin', 200, $trustedAccount);
    $refreshOK($a);
    $check($a->VerifyCode('654321') && IPS_GetInstance($idOf($a))['InstanceStatus'] === 102, 'A verified and trusted account must reach active status.');
    $check(count($a->transport->requests) === 4 && $a->transport->queue === [], 'Verification must perform trust, token exchange and one device read without another password login.');
    $session = json_decode($attrs($a)['Session'], true, 512, JSON_THROW_ON_ERROR);
    $check($session['state'] === 'authenticated' && $session['headers']['scnt'] === 'SYNTHETIC-ROTATED' && $session['headers']['x-apple-twosv-trust-token'] === 'SYNTHETIC-TRUST-TOKEN', 'The trusted session must persist rotated headers.');
    $check(str_contains($feedback($a), 'Sign-in successful') && $attrs($a)['LastError'] === '', 'The form must report completion only after authenticated inventory succeeds.');
    $attempts = json_decode($attrs($a)['AuthenticationAttempts'], true);
    $check(count($attempts['verify']) === 1 && !isset($attempts['login']) && !isset($attempts['resend']), 'Accepted conflicts must consume exactly one verification attempt.');
    $events = $security($a);
    $check($events[0]['httpStatus'] === $status && $events[0]['reason'] === 'ok', 'An accepted verification must not leave a misleading response error in diagnostics.');
    $check(!str_contains(json_encode($events), 'SYNTHETIC-') && !str_contains($attrs($a)['Session'], '654321'), 'Neither code nor session secrets may enter diagnostics or code persistence.');
}

foreach ([false, null, 'true', 1] as $valid) {
    $a = $newAccount('awaiting2FA');
    $before = $attrs($a)['DeviceCache'];
    $a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 409, ['securityCode' => ['valid' => $valid]]);
    $check(!$a->VerifyCode('654321') && count($a->transport->requests) === 1, 'Unconfirmed conflicts must not enter session trust.');
    $check($attrs($a)['DeviceCache'] === $before && json_decode($attrs($a)['Session'], true)['state'] === 'awaiting2FA', 'Rejected verification must preserve the cache and pending challenge.');
    $check(!str_contains($feedback($a), 'Sign-in successful'), 'A rejected conflict cannot show successful feedback.');
}

foreach (['login', 'verify', 'resend'] as $operation) {
    $a = $newAccount($operation === 'login' ? 'signedOut' : 'awaiting2FA');
    $before = $attrs($a);
    [$method, $path] = match ($operation) {
        'login'  => ['POST', '/signin/init'],
        'verify' => ['POST', '/verify/trusteddevice/securitycode'],
        default  => ['PUT', '/verify/trusteddevice/securitycode']
    };
    $a->transport->enqueue($method, $path, 202, ['securityCode' => ['valid' => true], 'message' => 'PRIVATE-PROVIDER-MESSAGE']);
    $success = match ($operation) {
        'login' => $a->Login(), 'verify' => $a->VerifyCode('654321'), default => $a->ResendCode()
    };
    $check(!$success && count($a->transport->requests) === 1, 'HTTP 202 must stop the requested authentication action without follow-up requests.');
    $check($attrs($a)['LastError'] === 'authenticationIncomplete' && $attrs($a)['DeviceCache'] === $before['DeviceCache'], 'An incomplete authentication must preserve inventory and have a distinct error reason.');
    $check(str_contains($feedback($a), 'HTTP 202') && str_contains($feedback($a), 'not a successful sign-in'), 'The form must explain incomplete HTTP 202 without claiming a wrong code or a successful sign-in.');
    $events = $security($a);
    $check($events[0]['httpStatus'] === 202 && end($events)['httpStatus'] === 202 && end($events)['reason'] === 'authenticationIncomplete', 'Request and action diagnostics must preserve HTTP 202 and its fixed meaning.');
    $check(!str_contains(json_encode($events) . $feedback($a), 'PRIVATE-'), 'Provider text and private data must stay out of the form and diagnostics.');
    $a->ApplyChanges();
    $a->GetConfigurationForm();
    $check(!$a->RefreshDevices() && count($a->transport->requests) === 1, 'Reopening, applying and background refresh cannot retry incomplete authentication.');
}

$a = $newAccount('awaiting2FA');
$a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 409, ['securityCode' => ['valid' => true], 'serviceErrors' => [['code' => '-20209']]]);
$check(!$a->VerifyCode('654321') && $attrs($a)['AccountBlock'] === 'accountLocked' && count($a->transport->requests) === 1, 'An explicit Apple security lock must still stop a valid code.');

$a = $newAccount('awaiting2FA');
for ($attempt = 0; $attempt < 5; $attempt++) {
    $a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 202);
    $check(!$a->VerifyCode('654321'), 'Incomplete verification must count as an attempt.');
}
$check(!$a->VerifyCode('654321') && count($a->transport->requests) === 5 && $attrs($a)['LastError'] === 'codeLimited', 'HTTP 202 must not bypass the five-check limit or send a sixth code.');

$a = $newAccount('awaiting2FA');
$a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 409, ['securityCode' => ['valid' => true]]);
$a->transport->enqueue('GET', '/2sv/trust', 204);
$a->transport->enqueue('POST', '/accountLogin', 200, $trustedAccount);
$a->transport->enqueue('POST', '/refreshClient', 503);
$check(!$a->VerifyCode('654321') && !str_contains($feedback($a), 'Sign-in successful'), 'A failed inventory load after session trust cannot claim full completion.');
$check($attrs($a)['LastError'] === 'server' && count($a->transport->requests) === 4, 'A failed post-verification read must keep the existing server backoff without immediate retry.');

echo "Account 2FA completion, incomplete HTTP-202 feedback, safe diagnostics and unchanged protection passed.\n";
