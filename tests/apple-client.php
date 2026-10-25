<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/AppleClient.php';

use SymconiCloud\AppleClient;
use SymconiCloud\AppleException;
use SymconiCloud\AppleHttpClient;
use SymconiCloud\AppleSrp;
use SymconiCloud\AppleTransport;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fails(callable $operation, string $reason): AppleException
{
    try {
        $operation();
    } catch (AppleException $error) {
        check($error->reason === $reason, 'Expected ' . $reason . ', received ' . $error->reason);
        return $error;
    }
    throw new RuntimeException('Expected failure: ' . $reason);
}

final class ScriptedAppleTransport implements AppleTransport
{
    public array $calls = [];
    public array $jar = [];

    public function __construct(private array $steps)
    {
    }

    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        $step = array_shift($this->steps);
        check(is_array($step), 'Unexpected network operation');
        check($method === $step[0] && str_contains($url, $step[1]), 'Unexpected method or endpoint');
        if (isset($step[5])) {
            $step[5]($headers, $body, $url);
        }
        $this->jar = ['.icloud.com' . "\tTRUE\t/\tTRUE\t0\tsynthetic\t" . count($this->calls)];
        return ['status' => $step[2], 'headers' => $step[4] ?? [], 'body' => $step[3] ?? []];
    }

    public function cookies(): array
    {
        return $this->jar;
    }

    public function complete(): void
    {
        check($this->steps === [], 'Missing expected network operations');
    }
}

$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/apple-srp.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($fixture['vectors'] as $vector) {
    $srp = new AppleSrp($vector['username'], hex2bin($vector['privateHex']));
    check($srp->publicValue() === $vector['expected']['a'], 'RFC group or public ephemeral differs');
    check(strlen(base64_decode($srp->publicValue(), true)) === 255, 'Leading zero A fixture lost');
    check($srp->proofs($vector['password'], $vector['challenge']) === [
        'm1' => $vector['expected']['m1'], 'm2' => $vector['expected']['m2']
    ], 'Proofs differ from independent SRP server');
}
$vector = $fixture['vectors'][0];
$srp = new AppleSrp($vector['username'], hex2bin($vector['privateHex']));
foreach ([['protocol' => 'unknown'], ['iteration' => 0], ['iteration' => 1000001], ['salt' => '%%%'], ['b' => '%%%'], ['b' => base64_encode("\0")], ['b' => base64_encode(hex2bin($fixture['group']['nHex']))]] as $override) {
    fails(fn () => $srp->proofs($vector['password'], array_replace($vector['challenge'], $override)), 'srp');
}

$account = ['dsInfo' => ['dsid' => '123456', 'hsaVersion' => 2], 'hsaTrustedBrowser' => true,
    'webservices'    => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com']]];
$inventory = ['content' => [
    ['id'                        => 'synthetic-phone', 'name' => 'Fixture Phone', 'deviceDisplayName' => 'iPhone',
        'deviceClass'            => 'iPhone', 'deviceStatus' => 200, 'batteryLevel' => 0.5,
        'location'               => ['latitude' => 52.5, 'longitude' => 13.4, 'timeStamp' => 1700000000000, 'horizontalAccuracy' => 10],
        'privateUnexpectedField' => 'must not escape'],
    ['id' => 'synthetic-offline', 'batteryLevel' => 4, 'location' => null]
]];
$transport = new ScriptedAppleTransport([
    ['POST', '/signin/init', 200, $vector['challenge'], ['scnt' => 'synthetic-init']],
    ['POST', '/signin/complete?', 409, [], ['scnt' => 'synthetic-complete', 'x-apple-id-session-id' => 'synthetic-session', 'x-apple-session-token' => 'synthetic-token']],
    ['PUT', '/verify/trusteddevice/securitycode', 204, [], [], static function ($headers, $body): void
    {
        check($body === null, 'Push must have no body');
        check($headers['scnt'] === 'synthetic-complete', 'Latest scnt not forwarded');
    }],
    ['POST', '/verify/trusteddevice/securitycode', 204, [], ['scnt' => 'synthetic-verified'], static function ($headers, $body): void
    {
        check($body === ['securityCode' => ['code' => '123456']], 'Wrong code envelope');
    }],
    ['GET', '/2sv/trust', 200, [], ['x-apple-twosv-trust-token' => 'synthetic-trust'], static function ($headers): void
    {
        check($headers['scnt'] === 'synthetic-verified', 'Verify scnt not rotated');
    }],
    ['POST', '/accountLogin', 200, $account, [], static function ($headers, $body): void
    {
        check($body['dsWebAuthToken'] === 'synthetic-token' && $body['trustToken'] === 'synthetic-trust', 'Tokens not exchanged');
    }],
    ['POST', '/refreshClient?', 200, $inventory, [], static function ($headers, $body, $url): void
    {
        check($body['clientContext']['shouldLocate'] === false, 'Inventory triggers active location');
        check(str_contains($url, 'dsid=123456'), 'Missing dsid');
    }]
]);
$client = new AppleClient($transport);
check($client->login($vector['username'], $vector['password']) === 'awaiting2FA', 'Login state');
check($client->verifyCode('123456') === 'authenticated', 'Verification state');
$devices = $client->devices();
check(count($devices) === 2 && $devices[0]['batteryLevel'] === 0.5, 'Inventory normalization');
check($devices[0]['location']['timestamp'] === 1700000000, 'Timestamp conversion');
check(!isset($devices[0]['privateUnexpectedField']) && $devices[1]['batteryLevel'] === null && $devices[1]['location'] === null, 'Unsafe raw/missing data');
check($client->session()['cookies'] === $transport->jar, 'Cookie snapshot stale');
check(!str_contains(json_encode($client->session()), $vector['password']), 'Password persisted');
$transport->complete();

$pending = ['state' => 'awaiting2FA', 'challengeAt' => time(), 'headers' => []];
$transport = new ScriptedAppleTransport([['POST', '/securitycode', 400]]);
$client = new AppleClient($transport, $pending);
fails(fn () => $client->verifyCode('123456'), 'invalidCode');
check($client->session()['state'] === 'awaiting2FA', 'Wrong code discarded challenge');
fails(fn () => $client->verifyCode('12x456'), 'codeFormat');
$transport->complete();

$transport = new ScriptedAppleTransport([]);
$client = new AppleClient($transport, array_replace($pending, ['challengeAt' => time() - 601]));
fails(fn () => $client->sendCode(), 'challengeExpired');
check($client->session()['state'] === 'expired', 'Expired resend challenge remains awaiting2FA');
$transport->complete();

$authenticated = ['state' => 'authenticated', 'findme' => 'https://p01-fmipweb.icloud.com', 'dsid' => '123456',
    'headers'             => ['x-apple-session-token' => 'synthetic-token']];
$transport = new ScriptedAppleTransport([]);
$client = new AppleClient($transport, $authenticated);
fails(fn () => $client->verifyCode('123456'), 'challengeExpired');
check($client->session()['state'] === 'authenticated', 'Invalid code action revoked a valid session');
$transport->complete();

$transport = new ScriptedAppleTransport([['POST', '/refreshClient?', 429, [], ['retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() + 1800)]]]);
$client = new AppleClient($transport, $authenticated);
$pause = fails(fn () => $client->devices(), 'rateLimited')->retryAfter;
check($pause >= 1795 && $pause <= 1800, 'HTTP-date Retry-After ignored');
$transport->complete();

$transport = new ScriptedAppleTransport([['POST', '/refreshClient?', 429, [], ['retry-after' => '720']]]);
$client = new AppleClient($transport, $authenticated);
check(fails(fn () => $client->devices(), 'rateLimited')->retryAfter === 720, 'Retry-After ignored');
check($client->session()['state'] === 'authenticated', 'Rate limit revoked session');
$transport->complete();

foreach ([[], ['content' => null], ['content' => ['id' => 'not-a-list']], ['content' => [['id' => 'same'], ['id' => 'same']]], ['content' => [['id' => 7]]]] as $bad) {
    $transport = new ScriptedAppleTransport([['POST', '/refreshClient?', 200, $bad]]);
    $client = new AppleClient($transport, $authenticated);
    fails(fn () => $client->devices(), 'response');
    check($client->session()['state'] === 'authenticated', 'Malformed data revoked session');
    $transport->complete();
}
$transport = new ScriptedAppleTransport([['POST', '/refreshClient?', 200, ['content' => []]]]);
check((new AppleClient($transport, $authenticated))->devices() === [], 'Valid empty inventory rejected');
$transport->complete();

$transport = new ScriptedAppleTransport([
    ['POST', '/refreshClient?', 401], ['POST', '/accountLogin', 200, $account], ['POST', '/refreshClient?', 401]
]);
$client = new AppleClient($transport, $authenticated);
fails(fn () => $client->devices(), 'authentication');
check($client->session()['state'] === 'expired', 'Repeated rejection did not expire session');
$transport->complete();

// Explicit challenge always wins; a missing browser trust also needs 2FA.
foreach ([['hsaChallengeRequired' => true, 'hsaTrustedBrowser' => true], ['hsaChallengeRequired' => false, 'hsaTrustedBrowser' => false]] as $trustState) {
    $challengedAccount = array_replace($account, $trustState);
    $transport = new ScriptedAppleTransport([
        ['POST', '/refreshClient?', 401], ['POST', '/accountLogin', 200, $challengedAccount]
    ]);
    $client = new AppleClient($transport, $authenticated);
    fails(fn () => $client->devices(), 'authentication');
    check($client->session()['state'] === 'awaiting2FA', 'Untrusted/challenged account incorrectly authenticated');
    $transport->complete();
}

$badService = $account;
$badService['webservices']['findme']['url'] = 'https://idmsa.apple.com';
$transport = new ScriptedAppleTransport([
    ['POST', '/refreshClient?', 401], ['POST', '/accountLogin', 200, $badService]
]);
fails(fn () => (new AppleClient($transport, $authenticated))->devices(), 'endpoint');
$transport->complete();

foreach (['https://idmsa.apple.com/appleauth/auth', 'https://setup.icloud.com:443/setup/ws/1', 'https://p01-fmipweb.icloud.com'] as $url) {
    AppleHttpClient::assertURL($url);
}
foreach (['http://setup.icloud.com', 'https://icloud.com.evil.invalid', 'https://evilicloud.com', 'https://setup.icloud.com@evil.invalid', 'https://user@setup.icloud.com', 'https://setup.icloud.com:444', 'https://setup.icloud.com/#fragment', 'https://idmsa.apple.com.evil.invalid'] as $url) {
    fails(fn () => AppleHttpClient::assertURL($url), 'endpoint');
}
$source = file_get_contents(__DIR__ . '/../libs/AppleHttpClient.php');
check(str_contains($source, 'CURLOPT_FOLLOWLOCATION => false'), 'Transport redirects are enabled');
$soundSession = ['state' => 'authenticated', 'findme' => 'https://p01-fmipweb.icloud.com', 'dsid' => '12345', 'headers' => []];
$soundFailure = new ScriptedAppleTransport([
    ['POST', '/playSound?', 200, [], [], static function (): void
    {
        throw new AppleException('network');
    }]
]);
fails(fn () => (new AppleClient($soundFailure, $soundSession))->playSound('synthetic-device'), 'network');
$soundFailure->complete();
check(count($soundFailure->calls) === 1, 'A network timeout during sound submission must never trigger an automatic retry.');
$soundRejection = new ScriptedAppleTransport([['POST', '/playSound?', 200, ['success' => false]]]);
fails(fn () => (new AppleClient($soundRejection, $soundSession))->playSound('synthetic-device'), 'soundRejected');
$soundRejection->complete();

$lostFailure = new ScriptedAppleTransport([
    ['POST', '/lostDevice?', 200, [], [], static function (): void
    {
        throw new AppleException('network');
    }]
]);
fails(fn () => (new AppleClient($lostFailure, $soundSession))->activateLostMode('synthetic-device', '', '', ''), 'network');
$lostFailure->complete();
check(count($lostFailure->calls) === 1, 'An uncertain Lost Mode submission must never be automatically repeated.');
$invalidLostInput = new ScriptedAppleTransport([]);
fails(fn () => (new AppleClient($invalidLostInput, $soundSession))->activateLostMode('synthetic-device', '', '', 'bad'), 'lostModeInput');
check($invalidLostInput->calls === [], 'Invalid input must also be rejected at the protocol boundary.');
$invalidLostInput->complete();

$stopFailure = new ScriptedAppleTransport([
    ['POST', '/lostDevice?', 200, [], [], static function (): void
    {
        throw new AppleException('network');
    }]
]);
fails(fn () => (new AppleClient($stopFailure, $soundSession))->deactivateLostMode('synthetic-device'), 'network');
$stopFailure->complete();
check(count($stopFailure->calls) === 1 && $stopFailure->calls[0]['body']['lostModeEnabled'] === false, 'An uncertain stop request must never retry or fall back to an activation.');
$stopRejection = new ScriptedAppleTransport([['POST', '/lostDevice?', 200, ['success' => false]]]);
fails(fn () => (new AppleClient($stopRejection, $soundSession))->deactivateLostMode('synthetic-device'), 'lostModeStopRejected');
$stopRejection->complete();

echo "PASS: independent SRP vectors, 2FA lifecycle, inventory, renewal, failures, origins, explicit sound without retries\n";
