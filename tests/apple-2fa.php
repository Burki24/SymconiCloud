<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/apple-client.php';
ob_end_clean();

$pending = ['state' => 'awaiting2FA', 'challengeAt' => time(), 'headers' => ['scnt' => 'synthetic-before']];
$verifiedHeaders = ['scnt' => 'synthetic-after', 'x-apple-session-token' => 'synthetic-verified-token'];
foreach ([200, 204, 409] as $status) {
    $transport = new ScriptedAppleTransport([
        ['POST', '/verify/trusteddevice/securitycode', $status, ['securityCode' => ['valid' => true]], $verifiedHeaders],
        ['GET', '/2sv/trust', 204, [], ['x-apple-twosv-trust-token' => 'synthetic-trust'], static function ($headers): void
        {
            check($headers['scnt'] === 'synthetic-after', 'Accepted code must forward rotated challenge headers to trust.');
        }],
        ['POST', '/accountLogin', 200, $account, [], static function ($headers, $body): void
        {
            check($body['dsWebAuthToken'] === 'synthetic-verified-token' && $body['trustToken'] === 'synthetic-trust', 'Trust and account login must use the newly issued tokens.');
        }]
    ]);
    $client = new \SymconiCloud\AppleClient($transport, $pending);
    check($client->verifyCode('654321') === 'authenticated', 'Accepted verification must finish session trust and account login: ' . $status);
    check($client->session()['challengeAt'] === 0, 'Only completed account login clears the pending challenge.');
    check(!str_contains(json_encode($client->session()), '654321'), 'The code must never enter the persisted session.');
    $transport->complete();
}

foreach ([[], ['securityCode' => null], ['securityCode' => true], ['securityCode' => []], ['securityCode' => ['valid' => false]], ['securityCode' => ['valid' => null]], ['securityCode' => ['valid' => 1]], ['securityCode' => ['valid' => 'true']]] as $body) {
    $transport = new ScriptedAppleTransport([['POST', '/verify/trusteddevice/securitycode', 409, $body]]);
    $client = new \SymconiCloud\AppleClient($transport, $pending);
    fails(fn () => $client->verifyCode('654321'), 'response');
    check($client->session()['state'] === 'awaiting2FA' && count($transport->calls) === 1, 'An unconfirmed 409 must not trust, log in, or resend automatically.');
    $transport->complete();
}

foreach ([['serviceErrors' => [['code' => '-20209']], 'accountLocked'], ['termsUpdateNeeded' => true, 'termsRequired']] as $case) {
    $reason = array_pop($case);
    $transport = new ScriptedAppleTransport([['POST', '/verify/trusteddevice/securitycode', 409, $case + ['securityCode' => ['valid' => true]]]]);
    $client = new \SymconiCloud\AppleClient($transport, $pending);
    fails(fn () => $client->verifyCode('654321'), $reason);
    check(count($transport->calls) === 1, 'Account-wide security decisions must take precedence over a valid code.');
    $transport->complete();
}

// Accepted code alone is insufficient: trust and authenticated account exchange must succeed too.
$transport = new ScriptedAppleTransport([
    ['POST', '/verify/trusteddevice/securitycode', 409, ['securityCode' => ['valid' => true]], $verifiedHeaders],
    ['GET', '/2sv/trust', 403]
]);
$client = new \SymconiCloud\AppleClient($transport, $pending);
fails(fn () => $client->verifyCode('654321'), 'accessDenied');
check($client->session()['state'] !== 'authenticated', 'A refused trust step cannot produce a signed-in session.');
$transport->complete();
$transport = new ScriptedAppleTransport([
    ['POST', '/verify/trusteddevice/securitycode', 409, ['securityCode' => ['valid' => true]], $verifiedHeaders],
    ['GET', '/2sv/trust', 204],
    ['POST', '/accountLogin', 200, array_replace($account, ['hsaTrustedBrowser' => false])]
]);
$client = new \SymconiCloud\AppleClient($transport, $pending);
check($client->verifyCode('654321') === 'awaiting2FA', 'A remaining Apple challenge must not be reported as authenticated.');
$transport->complete();

// HTTP 202 does not identify a completed challenge or authorize another automatic request.
$init = ['POST', '/signin/init', 200, $vector['challenge']];
$complete = ['POST', '/signin/complete', 409, [], $verifiedHeaders];
$code = ['POST', '/verify/trusteddevice/securitycode', 204, [], $verifiedHeaders];
foreach ([
    ['login', [['POST', '/signin/init', 202]]],
    ['login', [$init, ['POST', '/signin/complete', 202, ['securityCode' => ['valid' => true]], $verifiedHeaders]]],
    ['login', [$init, $complete, ['PUT', '/verify/trusteddevice/securitycode', 202]]],
    ['sendCode', [['PUT', '/verify/trusteddevice/securitycode', 202]]],
    ['verifyCode', [['POST', '/verify/trusteddevice/securitycode', 202, ['securityCode' => ['valid' => true]]]]],
    ['verifyCode', [$code, ['GET', '/2sv/trust', 202]]],
    ['verifyCode', [$code, ['GET', '/2sv/trust', 204], ['POST', '/accountLogin', 202, $account]]]
] as [$operation, $steps]) {
    $events = [];
    $transport = new ScriptedAppleTransport($steps);
    $client = new \SymconiCloud\AppleClient($transport, $pending, static function ($kind, $status, $reason) use (&$events): void
    {
        $events[] = compact('kind', 'status', 'reason');
    });
    $failure = fails(fn () => match ($operation) {
        'login'    => $client->login($vector['username'], $vector['password']),
        'sendCode' => $client->sendCode(),
        default    => $client->verifyCode('654321')
    }, 'authenticationIncomplete');
    check($failure->httpStatus === 202 && $client->session()['state'] !== 'authenticated', 'HTTP 202 must retain its status and never authenticate.');
    check(count($transport->calls) === count($steps) && end($events)['reason'] === 'authenticationIncomplete', 'HTTP 202 must be diagnosed without retries, trust shortcuts, or extra code delivery.');
    $transport->complete();
}

// The real HTTP parser rejects non-JSON 2xx bodies before returning headers/body.
$transport = new ScriptedAppleTransport([['POST', '/verify/trusteddevice/securitycode', 202, [], [], static function (): void
{
    throw new \SymconiCloud\AppleException('response', 202);
}]]);
$events = [];
$client = new \SymconiCloud\AppleClient($transport, $pending, static function ($kind, $status, $reason) use (&$events): void
{
    $events[] = compact('kind', 'status', 'reason');
});
fails(fn () => $client->verifyCode('654321'), 'authenticationIncomplete');
check(end($events)['status'] === 202 && end($events)['reason'] === 'authenticationIncomplete', 'Non-JSON HTTP 202 must have the same safe diagnosis.');
$transport->complete();

$transport = new ScriptedAppleTransport([['POST', '/verify/trusteddevice/securitycode', 202, ['securityCode' => ['valid' => true], 'serviceErrors' => [['code' => '-20209']]]]]);
$client = new \SymconiCloud\AppleClient($transport, $pending);
fails(fn () => $client->verifyCode('654321'), 'accountLocked');
$transport->complete();
$transport = new ScriptedAppleTransport([['POST', '/refreshClient?', 202, ['securityCode' => ['valid' => true]]]]);
$client = new \SymconiCloud\AppleClient($transport, $authenticated);
fails(fn () => $client->devices(), 'response');
$transport->complete();

echo "2FA accepted-conflict, strict rejection, session trust and incomplete HTTP-202 contracts passed.\n";
