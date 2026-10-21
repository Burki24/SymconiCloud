<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/autoload.php';
require_once dirname(__DIR__) . '/libs/AppleClient.php';

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');

/** Queued synthetic HTTPS responses, with strict request method/path checks. */
final class AccountFixtureTransport implements \SymconiCloud\AppleTransport
{
    /** @var list<array<string, mixed>> */
    public array $queue = [];
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $next = array_shift($this->queue);
        if ($next === null || $next['method'] !== $method || !str_contains($url, $next['path'])) {
            throw new RuntimeException('Unexpected synthetic request.');
        }
        return $next['response'];
    }

    public function cookies(): array
    {
        return [".icloud.com\tTRUE\t/\tTRUE\t0\tsynthetic-session\tSYNTHETIC-COOKIE-SECRET"];
    }

    public function enqueue(string $method, string $path, int $status, array $body = [], array $headers = []): void
    {
        $this->queue[] = ['method' => $method, 'path' => $path, 'response' => compact('status', 'headers', 'body')];
    }
}

/** Real account behavior with only its documented Apple client factory replaced. */
class AccountFixtureModule extends iCloudAccount
{
    public ?AccountFixtureTransport $transport = null;
    /** @var list<array<string, mixed>> */
    public array $restoredSessions = [];

    protected function LoadConfigurationForm(): array
    {
        // The helper resolves the concrete class source directory; this factory
        // subclass lives in tests, so retain the real module's static form here.
        return json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Account/form.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function CreateAppleClient(array $session): \SymconiCloud\AppleClient
    {
        $this->restoredSessions[] = $session;
        $this->transport ??= new AccountFixtureTransport();
        return new \SymconiCloud\AppleClient($this->transport, $session);
    }
}

$check = static function (bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$accountModule = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
$metadata = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $metadata->getValue();
$modules[$accountModule]['Class'] = AccountFixtureModule::class;
$metadata->setValue(null, $modules);
$id = IPS_CreateInstance($accountModule);
/** @var AccountFixtureModule $account */
$account = IPS\InstanceManager::getInstanceInterface($id);
$envelope = static fn (): array => json_decode($account->GetDevices(), true, 512, JSON_THROW_ON_ERROR);
$attributes = static fn (): array => $account->GetPersistence()['attributes'];
$timer = static function () use ($account): int
{
    $delegate = (new ReflectionProperty(IPSModuleStrict::class, 'module'))->getValue($account);
    $timers = (new ReflectionProperty(IPSModule::class, 'timers'))->getValue($delegate);
    return $timers['Refresh']['millis'];
};
$check($envelope()['state'] === 'signedOut' && !$envelope()['success'], 'New account must be signed out with no available inventory.');
$check($account->transport === null && $timer() === 0, 'Creation and initial ApplyChanges must not construct a client or start timer.');
IPS_GetConfigurationForm($id);
$check($account->transport === null, 'Opening account form must not construct a client.');
IPS_SetProperty($id, 'Enabled', true);
IPS_ApplyChanges($id);
$check(!$account->Login() && $account->transport === null, 'Invalid credentials must not reach transport.');

IPS_SetProperty($id, 'Username', 'synthetic.fixture@example.invalid');
IPS_SetProperty($id, 'Password', 'SYNTHETIC-PASSWORD-SECRET');
IPS_ApplyChanges($id);
$check($account->transport === null && $timer() === 0, 'Valid configuration must still require explicit login.');
$transport = new AccountFixtureTransport();
$account->transport = $transport;
$fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures/apple-srp.json'), true, 512, JSON_THROW_ON_ERROR);
$transport->enqueue('POST', '/signin/init', 200, $fixtures['vectors'][0]['challenge']);
$transport->enqueue('POST', '/signin/complete', 409, [], ['scnt' => 'SYNTHETIC-SCNT', 'x-apple-session-token' => 'SYNTHETIC-TOKEN-SECRET']);
$transport->enqueue('PUT', '/verify/trusteddevice/securitycode', 204);
$check(!$account->Login(), 'Login awaiting 2FA must not claim authenticated inventory success.');
$check($envelope()['state'] === 'awaiting2FA' && IPS_GetInstance($id)['InstanceStatus'] === 202, 'HTTP 409 must persist awaiting2FA state.');
$check(count($transport->requests) === 3 && $transport->queue === [], 'Login must complete SRP and request a trusted-device push.');
$check($timer() === 0, '2FA challenge must not schedule device polling.');
$count = count($transport->requests);
$check(!$account->VerifyCode('bad') && count($transport->requests) === $count, 'Malformed 2FA code must not be sent.');
$transport->enqueue('POST', '/verify/trusteddevice/securitycode', 400);
$check(!$account->VerifyCode('123456') && $envelope()['state'] === 'awaiting2FA', 'Rejected 2FA code must preserve the pending challenge.');

$transport->enqueue('POST', '/verify/trusteddevice/securitycode', 204);
$transport->enqueue('GET', '/2sv/trust', 204, [], ['x-apple-twosv-trust-token' => 'SYNTHETIC-TRUST-SECRET']);
$transport->enqueue('POST', '/accountLogin', 200, ['dsInfo' => ['dsid' => '12345'], 'webservices' => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com']]]);
$rawDevice = ['id' => 'synthetic-device', 'name' => 'Synthetic phone', 'deviceStatus' => 200, 'batteryLevel' => 0.5,
    'location'     => ['latitude' => 51.0, 'longitude' => 7.0, 'timeStamp' => 1700000000000, 'horizontalAccuracy' => 10],
    'token'        => 'SYNTHETIC-RAW-SECRET'];
$transport->enqueue('POST', '/refreshClient', 200, ['content' => [$rawDevice]]);
$check($account->VerifyCode('654321'), 'Accepted verification must authenticate and populate inventory.');
$result = $envelope();
$check($result['success'] && $result['state'] === 'authenticated' && count($result['devices']) === 1 && $result['updatedAt'] > 0, 'Successful authentication must expose normalized inventory.');
$check($result['devices'][0]['location']['timestamp'] === 1700000000, 'Account inventory must normalize location milliseconds.');
$export = $account->GetDevices();
foreach (['SYNTHETIC-PASSWORD-SECRET', 'SYNTHETIC-TOKEN-SECRET', 'SYNTHETIC-TRUST-SECRET', 'SYNTHETIC-COOKIE-SECRET', 'SYNTHETIC-RAW-SECRET', '654321', '123456'] as $secret) {
    $check(!str_contains($export, $secret), 'Child inventory must not include authentication or raw provider secrets.');
}
$sessionJSON = $attributes()['Session'];
$check(!str_contains($sessionJSON, '654321') && !str_contains($sessionJSON, 'SYNTHETIC-PASSWORD-SECRET'), 'Session persistence must exclude password and verification code.');
$check($timer() === 300000, 'Authenticated default polling interval must be 300 seconds.');
$count = count($transport->requests);
$account->GetDevices();
$account->ForwardData(json_encode(['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => 'GetDevices'], JSON_THROW_ON_ERROR));
IPS_GetConfigurationForm($id);
IPS_ApplyChanges($id);
$check(count($transport->requests) === $count, 'Cache reads, forms and repeated ApplyChanges must not contact Apple.');

// A new client is constructed for each operation, using only persisted session state.
$transport->enqueue('POST', '/refreshClient', 200, ['content' => [$rawDevice]]);
$check($account->RefreshDevices(), 'Token-backed session must support a later independent client.');
$restored = $account->restoredSessions[array_key_last($account->restoredSessions)];
$check($restored['state'] === 'authenticated' && $restored['headers']['x-apple-session-token'] === 'SYNTHETIC-TOKEN-SECRET' && count($restored['cookies']) === 1, 'New client must receive restart-safe headers and scoped cookies.');
$last = $transport->requests[array_key_last($transport->requests)];
$check($last['body']['clientContext']['shouldLocate'] === false, 'Inventory refresh must not request active location.');
$transport->enqueue('POST', '/refreshClient', 200, ['content' => [$rawDevice]]);
$forwardRefresh = json_decode($account->ForwardData(json_encode(['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => 'RefreshDevices'], JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
$check($forwardRefresh['success'] && $transport->queue === [], 'Allowlisted child refresh must perform the inventory request.');

$count = count($transport->requests);
foreach (['Login', 'VerifyCode', 'Logout', 'ReadSession'] as $command) {
    $rejected = json_decode($account->ForwardData(json_encode(['DataID' => '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}', 'Command' => $command], JSON_THROW_ON_ERROR)), true, 512, JSON_THROW_ON_ERROR);
    $check(!$rejected['success'] && $rejected['devices'] === [], 'Children must not invoke account management commands.');
}
$check(count($transport->requests) === $count, 'Rejected child commands must not contact Apple.');
$check(!json_decode($account->ForwardData('{invalid'), true)['success'], 'Malformed child JSON must be rejected.');
$check(!json_decode($account->ForwardData(json_encode(['DataID' => '{00000000-0000-0000-0000-000000000000}', 'Command' => 'GetDevices'], JSON_THROW_ON_ERROR)), true)['success'], 'Unexpected child DataID must be rejected.');

$transport->enqueue('POST', '/refreshClient', 429, [], ['retry-after' => '1']);
$check(!$account->RefreshDevices(), '429 must fail refresh.');
$check(!$envelope()['success'] && count($envelope()['devices']) === 1, 'Refresh failure must mark cached inventory unavailable without erasing it.');
$check($attributes()['RetryAt'] >= time() + 58 && IPS_GetInstance($id)['InstanceStatus'] === 206, '429 cooldown must clamp Retry-After to at least 60 seconds.');
$count = count($transport->requests);
$check(!$account->RefreshDevices() && count($transport->requests) === $count, 'Cooldown must suppress subsequent transport requests.');

foreach ([59, 86401] as $invalidInterval) {
    IPS_SetProperty($id, 'RefreshInterval', $invalidInterval);
    IPS_ApplyChanges($id);
    $check(!$account->RefreshDevices() && $timer() === 0 && count($transport->requests) === $count, 'Out-of-range interval must disable polling and requests.');
}
IPS_SetProperty($id, 'RefreshInterval', 60);
IPS_SetProperty($id, 'Password', 'SYNTHETIC-NEW-PASSWORD');
IPS_ApplyChanges($id);
$check($attributes()['Session'] === '{}' && $envelope()['devices'] === [] && $attributes()['RetryAt'] === 0, 'Credential change must clear session, cache and cooldown.');
$check($timer() === 0 && !$envelope()['success'], 'Credential change must require explicit login.');

// Exercise persisted session restoration in a genuinely new module object.
$saved = $account->GetPersistence();
$saved['attributes']['Session'] = $sessionJSON;
$saved['attributes']['DeviceCache'] = json_encode(['devices' => $result['devices'], 'updatedAt' => $result['updatedAt']], JSON_THROW_ON_ERROR);
$restoredID = IPS_CreateInstance($accountModule);
/** @var AccountFixtureModule $restoredAccount */
$restoredAccount = IPS\InstanceManager::getInstanceInterface($restoredID);
$restoredAccount->ApplyPersistence($saved);
$restoredAccount->ApplyChanges();
$restoredAccount->transport = new AccountFixtureTransport();
$restoredAccount->transport->enqueue('POST', '/refreshClient', 200, ['content' => [$rawDevice]]);
$check($restoredAccount->RefreshDevices(), 'New account object must refresh using persisted session without password login.');
$check(count($restoredAccount->transport->requests) === 1 && $restoredAccount->restoredSessions[0]['state'] === 'authenticated', 'Restart restoration must issue only inventory request.');
$restoredAccount->Logout();
$persisted = $restoredAccount->GetPersistence()['attributes'];
$check($persisted['Session'] === '{}' && json_decode($persisted['DeviceCache'], true)['devices'] === [], 'Logout must clear session and device cache.');
$before = count($restoredAccount->transport->requests);
IPS_SetProperty($restoredID, 'Enabled', false);
IPS_ApplyChanges($restoredID);
$check(!$restoredAccount->Login() && !$restoredAccount->RefreshDevices() && count($restoredAccount->transport->requests) === $before, 'Disabled account must never contact transport.');

echo "Account integration checks passed.\n";
