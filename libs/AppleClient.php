<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/AppleHttpClient.php';
require_once __DIR__ . '/AppleSrp.php';

/** Account/session protocol; never exposes raw service responses to children. */
final class AppleClient
{
    private const AUTH = 'https://idmsa.apple.com/appleauth/auth';
    private const SETUP = 'https://setup.icloud.com/setup/ws/1';
    private const WIDGET = 'd39ba9916b7251055b22c7f910e2ea796ee65e98b2ddecea8f5dde8d9d1a815d';
    private array $session;

    public function __construct(private readonly AppleTransport $transport, array $session = [])
    {
        $this->session = $session + ['state' => 'signedOut', 'clientId' => bin2hex(random_bytes(16)), 'headers' => [], 'challengeAt' => 0];
    }

    /** Session snapshot for protected Symcon attributes; contains no password/2FA code. */
    public function session(): array
    {
        return array_replace($this->session, ['cookies' => $this->transport->cookies()]);
    }

    /** Begins explicit user login. Returns authenticated or awaiting2FA. */
    public function login(string $username, string $password): string
    {
        if (trim($username) === '' || $password === '') {
            throw new AppleException('configuration');
        }
        $this->session['state'] = 'signedOut';
        $srp = new AppleSrp($username);
        $init = $this->auth('POST', '/signin/init', ['accountName' => $username, 'a' => $srp->publicValue(), 'protocols' => ['s2k', 's2k_fo']]);
        $this->expect($init, [200]);
        $challenge = $init['body'];
        if (!is_string($challenge['c'] ?? null) || $challenge['c'] === '') {
            throw new AppleException('response');
        }
        $proofs = $srp->proofs($password, $challenge);
        $complete = $this->auth('POST', '/signin/complete?isRememberMeEnabled=true', $proofs + [
            'accountName' => $username,
            'c'           => $challenge['c'],
            'rememberMe'  => true,
            'trustTokens' => isset($this->session['headers']['x-apple-twosv-trust-token']) ? [$this->session['headers']['x-apple-twosv-trust-token']] : []
        ]);
        $this->expect($complete, [200, 204, 409]);
        if (isset($complete['body']['m2']) && (!is_string($complete['body']['m2']) || !hash_equals($proofs['m2'], $complete['body']['m2']))) {
            throw new AppleException('srp');
        }
        if ($complete['status'] === 409) {
            $this->awaitCode();
        } else {
            $this->accountLogin();
            if ($this->session['state'] === 'awaiting2FA') {
                $this->sendCode();
            }
        }
        return $this->session['state'];
    }

    /** Verifies a trusted-device code; never persists the submitted code. */
    public function verifyCode(string $code): string
    {
        $this->assertChallenge();
        if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
            throw new AppleException('codeFormat');
        }
        $response = $this->auth('POST', '/verify/trusteddevice/securitycode', ['securityCode' => ['code' => $code]]);
        if (in_array($response['status'], [400, 401], true)) {
            throw new AppleException('invalidCode', $response['status']);
        }
        $this->expect($response, [200, 204]);
        $this->expect($this->auth('GET', '/2sv/trust', null), [200, 204]);
        $this->accountLogin();
        return $this->session['state'];
    }

    /** Sends a new trusted-device push for an existing, unexpired challenge. */
    public function sendCode(): void
    {
        $this->assertChallenge();
        $this->expect($this->auth('PUT', '/verify/trusteddevice/securitycode', null), [200, 204]);
    }

    /** Reads inventory without sound, lost mode, wipe, or active locate actions. */
    public function devices(): array
    {
        if ($this->session['state'] !== 'authenticated') {
            throw new AppleException('authentication');
        }
        $response = $this->readDevices();
        if (in_array($response['status'], [401, 421, 450], true)) {
            // One token-based renewal only. Never restart password login in a timer.
            try {
                $this->accountLogin();
            } catch (AppleException $exception) {
                if (in_array($exception->reason, ['authentication', 'credentials'], true)) {
                    $this->session['state'] = 'expired';
                }
                throw $exception;
            }
            if ($this->session['state'] !== 'authenticated') {
                throw new AppleException('authentication');
            }
            $response = $this->readDevices();
        }
        if (in_array($response['status'], [401, 421, 450], true)) {
            $this->session['state'] = 'expired';
        }
        $this->expect($response, [200]);
        $content = $response['body']['content'] ?? null;
        if (!is_array($content) || !array_is_list($content)) {
            throw new AppleException('response');
        }
        $devices = [];
        $seen = [];
        foreach ($content as $device) {
            if (!is_array($device) || !is_string($device['id'] ?? null) || $device['id'] === '' || isset($seen[$device['id']])) {
                throw new AppleException('response');
            }
            $seen[$device['id']] = true;
            $devices[] = self::normalizeDevice($device);
        }
        return $devices;
    }

    /** Explicit side effect: submit once, without retrying an uncertain result. */
    public function playSound(string $deviceID): void
    {
        $this->sendDeviceCommand($deviceID, 'playSound', [
            'subject' => 'Find My iPhone Alert', 'clientContext' => ['fmly' => true]
        ], 'soundRejected');
    }

    /** Validates transient contact information and an optional new numeric device passcode. */
    public static function validateLostModeInput(string $phoneNumber, string $message, string $newPasscode): void
    {
        if (preg_match('/\A[0-9+() .-]{0,40}\z/D', $phoneNumber) !== 1
            || ($phoneNumber !== '' && preg_match('/[0-9]/', $phoneNumber) !== 1)
            || preg_match('/\A[^\x00-\x1F\x7F]{0,160}\z/uD', $message) !== 1
            || preg_match('/\A(?:[0-9]{4}|[0-9]{6})?\z/D', $newPasscode) !== 1) {
            throw new AppleException('lostModeInput');
        }
    }

    /** Explicit activation only; contact information/passcode never become session state. */
    public function activateLostMode(string $deviceID, string $phoneNumber, string $message, string $newPasscode): void
    {
        self::validateLostModeInput($phoneNumber, $message, $newPasscode);
        $this->sendDeviceCommand($deviceID, 'lostDevice', [
            'text'            => $message, 'userText' => true, 'ownerNbr' => $phoneNumber,
            'lostModeEnabled' => true, 'trackingEnabled' => true, 'passcode' => $newPasscode
        ], 'lostModeRejected');
    }

    /** One authenticated POST, never token renewal, password login or an automatic retry. */
    private function sendDeviceCommand(string $deviceID, string $command, array $payload, string $rejectedReason): void
    {
        if ($this->session['state'] !== 'authenticated') {
            throw new AppleException('authentication');
        }
        if ($deviceID === '' || strlen($deviceID) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $deviceID)) {
            throw new AppleException('deviceUnknown');
        }
        $url = ($this->session['findme'] ?? '') . '/fmipservice/client/web/' . $command . '?' . http_build_query([
            'clientId' => $this->session['clientId'], 'dsid' => $this->session['dsid'] ?? ''
        ]);
        $response = $this->call('POST', $url, [], ['device' => $deviceID] + $payload);
        if (in_array($response['status'], [401, 421, 450], true)) {
            $this->session['state'] = 'expired';
        }
        $this->expect($response, [200, 202, 204]);
        $code = $response['body']['statusCode'] ?? null;
        if ($code !== null && !in_array($code, [200, '200', 202, '202', 204, '204'], true)) {
            throw new AppleException($rejectedReason);
        }
        if (($response['body']['success'] ?? null) === false || !empty($response['body']['error'])) {
            throw new AppleException($rejectedReason);
        }
    }

    private function awaitCode(): void
    {
        $this->session['state'] = 'awaiting2FA';
        $this->session['challengeAt'] = time();
        $this->sendCode();
    }

    private function assertChallenge(): void
    {
        if ($this->session['state'] !== 'awaiting2FA') {
            throw new AppleException('challengeExpired');
        }
        if (time() - (int) $this->session['challengeAt'] > 600) {
            $this->session['state'] = 'expired';
            throw new AppleException('challengeExpired');
        }
    }

    private function accountLogin(): void
    {
        $headers = $this->session['headers'];
        $token = $headers['x-apple-session-token'] ?? '';
        if (!is_string($token) || $token === '') {
            $this->session['state'] = 'expired';
            throw new AppleException('authentication');
        }
        $response = $this->call('POST', self::SETUP . '/accountLogin', [], [
            'accountCountryCode' => $headers['x-apple-id-account-country'] ?? 'DE',
            'dsWebAuthToken'     => $token,
            'extended_login'     => true,
            'trustToken'         => $headers['x-apple-twosv-trust-token'] ?? ''
        ]);
        $this->expect($response, [200]);
        $data = $response['body'];
        $info = $data['dsInfo'] ?? [];
        if (!is_array($info)) {
            throw new AppleException('response');
        }
        if (($info['hsaVersion'] ?? 0) === 2 && (($data['hsaChallengeRequired'] ?? false) || !($data['hsaTrustedBrowser'] ?? false))) {
            $this->session['state'] = 'awaiting2FA';
            $this->session['challengeAt'] = time();
            return;
        }
        $dsid = $info['dsid'] ?? null;
        if ((!is_string($dsid) && !is_int($dsid)) || (string) $dsid === '') {
            throw new AppleException('response');
        }
        $url = $data['webservices']['findme']['url'] ?? '';
        if (!is_string($url) || $url === '') {
            throw new AppleException('serviceUnavailable');
        }
        AppleHttpClient::assertURL($url);
        $parts = parse_url($url);
        if (!str_ends_with(strtolower($parts['host']), '.icloud.com') || isset($parts['query'])
            || (isset($parts['path']) && !in_array($parts['path'], ['', '/'], true))) {
            throw new AppleException('endpoint');
        }
        $this->session['dsid'] = (string) $dsid;
        $this->session['findme'] = rtrim($url, '/');
        $this->session['state'] = 'authenticated';
        $this->session['challengeAt'] = 0;
    }

    private function readDevices(): array
    {
        $url = ($this->session['findme'] ?? '') . '/fmipservice/client/web/refreshClient?' . http_build_query([
            'clientId' => $this->session['clientId'], 'dsid' => $this->session['dsid'] ?? ''
        ]);
        return $this->call('POST', $url, [], ['clientContext' => [
            'fmly' => true, 'shouldLocate' => false, 'selectedDevice' => 'all', 'deviceListVersion' => 1
        ]]);
    }

    private function auth(string $method, string $path, ?array $body): array
    {
        $headers = [
            'X-Apple-OAuth-Client-Id'          => self::WIDGET,
            'X-Apple-Widget-Key'               => self::WIDGET,
            'X-Apple-OAuth-Client-Type'        => 'firstPartyAuth',
            'X-Apple-OAuth-Redirect-URI'       => 'https://www.icloud.com',
            'X-Apple-OAuth-Require-Grant-Code' => 'true',
            'X-Apple-OAuth-Response-Mode'      => 'web_message',
            'X-Apple-OAuth-Response-Type'      => 'code',
            'X-Apple-OAuth-State'              => $this->session['clientId']
        ];
        foreach (['scnt', 'x-apple-id-session-id'] as $name) {
            if (isset($this->session['headers'][$name])) {
                $headers[$name] = $this->session['headers'][$name];
            }
        }
        return $this->call($method, self::AUTH . $path, $headers, $body);
    }

    private function call(string $method, string $url, array $headers, ?array $body): array
    {
        $response = $this->transport->request($method, $url, $headers, $body);
        foreach (['scnt', 'x-apple-id-session-id', 'x-apple-session-token', 'x-apple-twosv-trust-token', 'x-apple-id-account-country'] as $name) {
            if (isset($response['headers'][$name])) {
                $this->session['headers'][$name] = $response['headers'][$name];
            }
        }
        return $response;
    }

    private function expect(array $response, array $allowed): void
    {
        $status = $response['status'];
        if (in_array($status, $allowed, true)) {
            return;
        }
        $reason = match (true) {
            $status === 429 || $status === 423  => 'rateLimited',
            $status === 401                     => 'credentials',
            in_array($status, [421, 450], true) => 'authentication',
            $status === 403                     => 'accessDenied',
            $status >= 500                      => 'server',
            default                             => 'response'
        };
        $retryHeader = (string) ($response['headers']['retry-after'] ?? '300');
        $retrySeconds = ctype_digit($retryHeader) ? (int) $retryHeader : max(0, (strtotime($retryHeader) ?: time() + 300) - time());
        $retry = max(60, min(86400, $retrySeconds));
        throw new AppleException($reason, $status, $reason === 'rateLimited' ? $retry : 0);
    }

    private static function normalizeDevice(array $device): array
    {
        $details = [];
        foreach (['lowPowerMode', 'locationEnabled', 'locationCapable', 'isLocating', 'lostModeEnabled', 'lostModeCapable', 'activationLocked', 'fmlyShare'] as $field) {
            $details[$field] = is_bool($device[$field] ?? null) ? $device[$field] : null;
        }
        $modelIdentifier = is_string($device['rawDeviceModel'] ?? null) ? trim($device['rawDeviceModel']) : '';
        $modelIdentifier = preg_match('/\A[^\x00-\x1F\x7F]{1,128}\z/uD', $modelIdentifier) === 1 ? $modelIdentifier : null;
        $battery = $device['batteryLevel'] ?? null;
        $battery = is_numeric($battery) && is_finite((float) $battery) && (float) $battery >= 0 && (float) $battery <= 1 ? (float) $battery : null;
        $location = null;
        $raw = $device['location'] ?? null;
        if (is_array($raw) && is_numeric($raw['latitude'] ?? null) && is_numeric($raw['longitude'] ?? null)
            && abs((float) $raw['latitude']) <= 90 && abs((float) $raw['longitude']) <= 180
            && is_numeric($raw['timeStamp'] ?? null) && (float) $raw['timeStamp'] > 0) {
            $accuracy = $raw['horizontalAccuracy'] ?? null;
            $location = [
                'latitude'           => (float) $raw['latitude'], 'longitude' => (float) $raw['longitude'],
                'horizontalAccuracy' => is_numeric($accuracy) && is_finite((float) $accuracy) && (float) $accuracy >= 0 ? (float) $accuracy : null,
                'timestamp'          => (int) ((float) $raw['timeStamp'] / 1000), 'isOld' => (bool) ($raw['isOld'] ?? false),
                'isInaccurate'       => is_bool($raw['isInaccurate'] ?? null) ? $raw['isInaccurate'] : null,
                'finished'           => is_bool($raw['locationFinished'] ?? null) ? $raw['locationFinished'] : null
            ];
        }
        return [
            'id'              => $device['id'],
            'name'            => is_string($device['name'] ?? null) ? $device['name'] : '',
            'model'           => is_string($device['deviceDisplayName'] ?? null) ? $device['deviceDisplayName'] : '',
            'modelIdentifier' => $modelIdentifier,
            'details'         => $details,
            'deviceClass'     => is_string($device['deviceClass'] ?? null) ? $device['deviceClass'] : '',
            'deviceStatus'    => is_numeric($device['deviceStatus'] ?? null) ? (int) $device['deviceStatus'] : 0,
            'batteryLevel'    => $battery,
            'batteryStatus'   => is_string($device['batteryStatus'] ?? null) ? $device['batteryStatus'] : '',
            'location'        => $location
        ];
    }
}
