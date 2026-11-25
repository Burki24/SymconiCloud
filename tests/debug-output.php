<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/autoload.php';

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary(dirname(__DIR__) . '/library.json');
$assertDebug = static function (bool $ok, string $message): void
{ if (!$ok) { throw new RuntimeException($message); } };
$verifyTrace = static function (int $id) use ($assertDebug): array
{
    $messages = IPS\DebugServer::getDebugMessages($id);
    $stack = [];
    $operations = [];
    foreach ($messages as $entry) {
        $assertDebug(preg_match('/^\w+ \| (START|ENDE|AKTION|SENDEN|EMPFANG|FEHLER) \| [^:]+:\d+$/D', $entry['Message']) === 1, 'Every module debug line needs the standard category and automatic source.');
        $data = json_decode($entry['Data'], true, 512, JSON_THROW_ON_ERROR);
        $assertDebug(isset($data['Instanz'], $data['Vorgang'], $data['Aufruf'], $data['Meldung'], $data['Bezug']) && $data['Vorgang'] !== '', 'The standard envelope must be complete.');
        $assertDebug($data['Instanz'] === $id, 'Debug must identify the actual instance, including lifecycle entries.');
        $assertDebug(strlen($entry['Data']) <= 4096, 'Each trace payload is limited to 4096 bytes.');
        $key = $data['Vorgang'] . ':' . $data['Aufruf'];
        if (str_contains($entry['Message'], ' | START | ')) {
            $assertDebug($stack === [] ? $data['Aufruf'] === 1 : str_starts_with(end($stack), $data['Vorgang'] . ':'), 'Nested calls must share their operation and new outer calls must reset call numbering.');
            $stack[] = $key;
            $operations[$data['Vorgang']] = true;
        }
        if (str_contains($entry['Message'], ' | ENDE | ')) {
            $assertDebug(array_pop($stack) === $key, 'Early return/exception lost a matching end.');
        }
    }
    $assertDebug($messages !== [] && $stack === [] && $operations !== [], 'Every module must emit balanced traces.');
    return $messages;
};
$debugInstances = [];
foreach (glob(dirname(__DIR__) . '/*/module.json') as $metadata) {
    $module = json_decode(file_get_contents($metadata), true, 512, JSON_THROW_ON_ERROR);
    $id = IPS_CreateInstance($module['id']);
    $instance = IPS\InstanceManager::getInstanceInterface($id);
    $instance->ApplyChanges();
    $instance->GetConfigurationForm();
    try {
        $instance->RequestAction('PRIVATE-UNKNOWN-OPERATION', 'PRIVATE-INPUT');
        throw new LogicException('Expected unsupported action.');
    } catch (InvalidArgumentException) {
        // Public exceptions must keep their original type and still close their trace.
    }
    $verifyTrace($id);
    $assertDebug(!str_contains(json_encode(IPS\DebugServer::getDebugMessages($id)), 'PRIVATE-'), 'Unknown action input cannot enter debug output.');
    $debugInstances[$module['name']] = $id;
}
$assertDebug(count($debugInstances) === 7, 'All seven real modules must be covered.');

// An unavailable visualisation hook must explain its HTTP refusal without leaking its URL/token.
$savedServer = $_SERVER;
$photoMetadata = json_decode(file_get_contents(dirname(__DIR__) . '/iCloud Photos/module.json'), true);
$photoID = IPS_CreateInstance($photoMetadata['id']);
$photoInstance = IPS\InstanceManager::getInstanceInterface($photoID);
$_SERVER['REQUEST_URI'] = '/PRIVATE-PHOTO-URL';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_PHOTO_TOKEN'] = 'PRIVATE-PHOTO-TOKEN';
ob_start();
try {
    (new ReflectionMethod($photoInstance, 'ProcessHookData'))->invoke($photoInstance);
}
finally {
    ob_end_clean();
    $_SERVER = $savedServer;
}
$hookOutput = json_encode($verifyTrace($photoID), JSON_UNESCAPED_UNICODE);
$assertDebug(str_contains($hookOutput, 'HTTP-Status') && str_contains($hookOutput, '404') && !str_contains($hookOutput, 'PRIVATE-'), 'Unavailable hooks need a safe, explicit HTTP refusal.');

// Reuse the existing real account integration fixtures and their no-network transport.
ob_start();
require __DIR__ . '/account-2fa.php';
ob_end_clean();
$a = $newAccount('awaiting2FA');
$a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 409, ['securityCode' => ['valid' => true]], ['scnt' => 'PRIVATE-NONCE']);
$a->transport->enqueue('GET', '/2sv/trust', 204);
$a->transport->enqueue('POST', '/accountLogin', 200, $trustedAccount);
$refreshOK($a);
$assertDebug($a->VerifyCode('654321'), 'Tracing must preserve accepted 2FA completion.');
$messages = $verifyTrace($idOf($a));
$serialized = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
foreach (['654321', 'PRIVATE-', 'SYNTHETIC-COOKIE-SECRET'] as $secret) {
    $assertDebug(!str_contains($serialized, $secret), 'Authentication secrets must never enter debug output.');
}
$assertDebug(str_contains($serialized, 'HTTP-Status') && str_contains($serialized, '409'), 'A verified conflict needs a readable HTTP result.');
$assertDebug(count($a->transport->requests) === 4, 'Tracing cannot add Apple requests.');

$paused = $newAccount();
IPS_SetProperty($idOf($paused), 'PauseRequests', true);
$paused->ApplyChanges();
$assertDebug(!$paused->RefreshDevices() && $paused->transport->requests === [], 'A paused account must remain paused without HTTP.');
$trace = json_encode($verifyTrace($idOf($paused)), JSON_UNESCAPED_UNICODE);
$assertDebug(str_contains($trace, 'pausiert') && str_contains($trace, 'Sitzung bleibt erhalten'), 'The pause reason must be understandable without English diagnostic codes.');

class DebugFaultAccount extends AccountFixtureModule
{
    protected function SendDebug(string $Message, string $Data, int $Format): bool
    {
        throw new RuntimeException('PRIVATE-DEBUG-FAILURE');
    }
}
$loaded = $metadata->getValue();
$loaded[$accountModule]['Class'] = DebugFaultAccount::class;
$metadata->setValue(null, $loaded);
$fault = $newAccount('awaiting2FA');
$fault->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 202);
$assertDebug(!$fault->VerifyCode('654321') && count($fault->transport->requests) === 1, 'Broken debug output cannot retry, suppress or turn an incomplete request into success.');
$assertDebug($attrs($fault)['LastError'] === 'authenticationIncomplete', 'Broken debug must preserve the original error classification.');

echo "Seven-module German debug envelopes, balanced calls, HTTP results, privacy and unchanged requests passed.\n";
