<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/AppleClient.php';
require_once __DIR__ . '/../libs/PhotosAccount.php';
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';

class iCloudAccount extends IPSModuleStrict
{
    use \SymconiCloud\PhotosAccount;
    use \Burki24\SymconModuleHelper\ConfigurationFormHelper;
    use \Burki24\SymconModuleHelper\DataFlowHelper;

    private const REQUEST_DATA = '{1E60F0BE-03E4-4AA4-A1CE-AA838725A4E2}';
    private const NOTIFICATION_DATA = '{FCE7780F-9520-4E52-92F3-60034FFAED6D}';

    /** Registers account settings and restart-safe, account-private state. */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPhotosAccount();
        $this->RegisterPropertyBoolean('Enabled', false);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('RefreshInterval', 300);
        $this->RegisterAttributeString('Session', '{}');
        $this->RegisterAttributeString('DeviceCache', '{"devices":[],"updatedAt":0}');
        $this->RegisterAttributeString('CredentialFingerprint', '');
        $this->RegisterAttributeString('LastError', '');
        $this->RegisterAttributeInteger('RetryAt', 0);
        $this->RegisterTimer('Refresh', 0, 'ICAC_RefreshDevices($_IPS["TARGET"]);');
    }

    /** Applies settings without logging in or performing any network request. */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return;
        }
        try {
            $fingerprint = $this->credentialFingerprint();
            if ($fingerprint !== $this->ReadAttributeString('CredentialFingerprint')) {
                $this->clearState();
                $this->WriteAttributeString('CredentialFingerprint', $fingerprint);
            }
            if (!$this->ReadPropertyBoolean('Enabled')) {
                $this->clearState();
            }
            if (!$this->ReadPropertyBoolean('EnablePhotos')) {
                $this->ClearPhotoState();
            }
            else {
                try {
                    $this->PhotoCache()->trim();
                } catch (Throwable) {
                    $this->WriteAttributeString('PhotoError', $this->Translate('Photo cache unavailable or configured limits are invalid.'));
                }
            }
            $this->updateState();
        } finally {
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        $this->notifyChildren();
    }

    /** @return string Local form state only; never contacts Apple. */
    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $state = $this->readSession()['state'] ?? 'signedOut';
        $form['actions'][0]['caption'] = $this->Translate('Session state') . ': ' . $this->Translate((string) $state);
        $form['actions'][1]['caption'] = $this->errorMessage($this->ReadAttributeString('LastError'));
        $form['actions'][] = ['type' => 'Label', 'caption' => $this->ReadAttributeString('PhotoError')];
        $form['actions'] = $this->SetFormFieldVisibility($form['actions'], ['TwoFactor'], $state === 'awaiting2FA');
        return $this->EncodeConfigurationForm($form);
    }

    /** @return bool True if explicit login and subsequent device inventory succeeded. */
    public function Login(): bool
    {
        return $this->operate('login');
    }

    /** @param string $Code Six digits from a trusted Apple device. @return bool Authentication and inventory success. */
    public function VerifyCode(string $Code): bool
    {
        return $this->operate('verify', $Code);
    }

    /** @return bool True if a trusted-device code was requested successfully. */
    public function ResendCode(): bool
    {
        return $this->operate('resend');
    }

    /** @return bool True if a fresh read-only inventory was stored. No password login is started. */
    public function RefreshDevices(): bool
    {
        return $this->operate('devices');
    }

    /** @return string Normalized cached inventory envelope without tokens or account credentials. */
    public function GetDevices(): string
    {
        return json_encode($this->inventory(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** Deletes local session/cache and stops requests; does not revoke the Apple account. */
    public function Logout(): void
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return;
        }
        try {
            $this->clearState();
            $this->updateState();
        } finally {
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        $this->notifyChildren();
        $this->ReloadForm();
    }

    /** @param string $JSONString Allowlisted child command. @return string Sanitized inventory or action result. */
    public function ForwardData(string $JSONString): string
    {
        try {
            $request = $this->DecodeDataFlowMessage($JSONString, self::REQUEST_DATA);
            if (in_array($request['Command'] ?? '', ['GetPhotoAlbums', 'RefreshPhotoAlbums', 'GetPhotoPage', 'GetPhotoImage'], true)) {
                return json_encode($this->ForwardPhotosRequest($request), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            if (($request['Command'] ?? '') === 'PlaySound') {
                if (!is_string($request['DeviceID'] ?? null) || array_diff(array_keys($request), ['DataID', 'Command', 'DeviceID']) !== []) {
                    throw new InvalidArgumentException();
                }
                return json_encode($this->performDeviceAction($request['DeviceID'], 'PlaySound'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            if (($request['Command'] ?? '') === 'ActivateLostMode') {
                if (($request['Confirmed'] ?? null) !== true
                    || array_diff(array_keys($request), ['DataID', 'Command', 'DeviceID', 'Confirmed', 'PhoneNumber', 'Message', 'NewPasscode']) !== []) {
                    throw new InvalidArgumentException();
                }
                foreach (['DeviceID', 'PhoneNumber', 'Message', 'NewPasscode'] as $field) {
                    if (!is_string($request[$field] ?? null)) {
                        throw new InvalidArgumentException();
                    }
                }
                return json_encode($this->performDeviceAction($request['DeviceID'], 'ActivateLostMode', [
                    $request['PhoneNumber'], $request['Message'], $request['NewPasscode']
                ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            if (($request['Command'] ?? '') === 'DeactivateLostMode') {
                if (($request['Confirmed'] ?? null) !== true || !is_string($request['DeviceID'] ?? null)
                    || array_diff(array_keys($request), ['DataID', 'Command', 'DeviceID', 'Confirmed']) !== []) {
                    throw new InvalidArgumentException();
                }
                return json_encode($this->performDeviceAction($request['DeviceID'], 'DeactivateLostMode'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            if (($request['Command'] ?? '') === 'RefreshDevices') {
                if (!$this->RefreshDevices()) {
                    $result = $this->inventory();
                    $result['success'] = false;
                    if ($result['error'] === '') {
                        $result['error'] = $this->Translate('Account request already running.');
                    }
                    return json_encode($result, JSON_THROW_ON_ERROR);
                }
            } elseif (($request['Command'] ?? '') !== 'GetDevices') {
                throw new InvalidArgumentException();
            }
            return $this->GetDevices();
        } catch (Throwable) {
            return json_encode(['success' => false, 'state' => 'error', 'devices' => [], 'updatedAt' => 0, 'error' => $this->Translate('Unsupported request.')], JSON_THROW_ON_ERROR);
        }
    }

    /** @param string $Ident Explicit account action. @param mixed $Value Action payload. */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        match ($Ident) {
            'Login'          => $this->Login(),
            'VerifyCode'     => $this->VerifyCode(is_string($Value) ? $Value : ''),
            'ResendCode'     => $this->ResendCode(),
            'RefreshDevices' => $this->RefreshDevices(),
            'Logout'         => $this->Logout(),
            default          => throw new InvalidArgumentException('Unsupported account action.')
        };
    }

    /** Transport seam for offline integration tests; production owns its cookie jar. */
    protected function CreateAppleClient(array $session): \SymconiCloud\AppleClient
    {
        return new \SymconiCloud\AppleClient(new \SymconiCloud\AppleHttpClient($session['cookies'] ?? []), $session);
    }

    private function operate(string $operation, string $value = ''): bool
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return false;
        }
        $client = null;
        $success = false;
        try {
            if (!$this->configurationValid()) {
                throw new \SymconiCloud\AppleException('configuration');
            }
            if ($this->credentialFingerprint() !== $this->ReadAttributeString('CredentialFingerprint')) {
                $this->clearState();
                $this->WriteAttributeString('CredentialFingerprint', $this->credentialFingerprint());
            }
            if ($this->ReadAttributeInteger('RetryAt') > time()) {
                throw new \SymconiCloud\AppleException('rateLimited');
            }
            $client = $this->CreateAppleClient($this->readSession());
            if ($operation === 'login') {
                $state = $client->login(trim($this->ReadPropertyString('Username')), $this->ReadPropertyString('Password'));
            } elseif ($operation === 'verify') {
                $state = $client->verifyCode($value);
            } elseif ($operation === 'resend') {
                $client->sendCode();
                $state = 'awaiting2FA';
            } else {
                $state = $client->session()['state'];
            }
            if ($state === 'authenticated' && $operation !== 'resend') {
                $devices = $client->devices();
                $this->WriteAttributeString('DeviceCache', json_encode(['devices' => $devices, 'updatedAt' => time()], JSON_THROW_ON_ERROR));
                $success = true;
            } elseif ($operation === 'resend') {
                $success = true;
            }
            $this->WriteAttributeString('LastError', '');
            $this->WriteAttributeInteger('RetryAt', 0);
        } catch (\SymconiCloud\AppleException $exception) {
            $this->WriteAttributeString('LastError', $exception->reason);
            if ($exception->retryAfter > 0) {
                $this->WriteAttributeInteger('RetryAt', time() + $exception->retryAfter);
            }
        } catch (Throwable) {
            // Do not forward exception text, response bodies or stack arguments.
            $this->WriteAttributeString('LastError', 'internal');
        } finally {
            if ($client !== null) {
                $this->WriteAttributeString('Session', json_encode($client->session(), JSON_THROW_ON_ERROR));
            }
            $this->updateState();
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        $this->notifyChildren();
        if ($operation !== 'devices') {
            $this->ReloadForm();
        }
        return $success;
    }

    /** Device actions share account locking, membership checks, session state and cooldown. */
    private function performDeviceAction(string $deviceID, string $action, array $parameters = []): array
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return ['success' => false, 'message' => $this->Translate('Account request already running.')];
        }
        $client = null;
        $success = false;
        $reason = '';
        try {
            if (!$this->configurationValid() || $this->credentialFingerprint() !== $this->ReadAttributeString('CredentialFingerprint')) {
                throw new \SymconiCloud\AppleException('configuration');
            }
            if (($this->readSession()['state'] ?? '') !== 'authenticated') {
                throw new \SymconiCloud\AppleException('authentication');
            }
            if ($this->ReadAttributeInteger('RetryAt') > time()) {
                throw new \SymconiCloud\AppleException('rateLimited');
            }
            $cache = json_decode($this->ReadAttributeString('DeviceCache'), true, 512, JSON_THROW_ON_ERROR);
            $target = null;
            foreach ($cache['devices'] ?? [] as $device) {
                if (is_array($device) && ($device['id'] ?? null) === $deviceID) {
                    $target = $device;
                    break;
                }
            }
            if ($target === null || (int) ($cache['updatedAt'] ?? 0) <= 0) {
                throw new \SymconiCloud\AppleException('deviceUnknown');
            }
            if (in_array($action, ['ActivateLostMode', 'DeactivateLostMode'], true) && ($target['details']['lostModeCapable'] ?? null) !== true) {
                throw new \SymconiCloud\AppleException('lostModeUnsupported');
            }
            if ($action === 'ActivateLostMode') {
                if (($target['details']['lostModeEnabled'] ?? null) === true) {
                    throw new \SymconiCloud\AppleException('lostModeActive');
                }
                \SymconiCloud\AppleClient::validateLostModeInput(...$parameters);
            }
            $client = $this->CreateAppleClient($this->readSession());
            if ($action === 'ActivateLostMode') {
                $client->activateLostMode($deviceID, ...$parameters);
            } elseif ($action === 'DeactivateLostMode') {
                // Also permit a stop when the cache has not yet confirmed a pending activation.
                $client->deactivateLostMode($deviceID);
            } else {
                $client->playSound($deviceID);
            }
            $success = true;
        } catch (\SymconiCloud\AppleException $exception) {
            $reason = $exception->reason;
            if ($exception->retryAfter > 0) {
                $this->WriteAttributeInteger('RetryAt', time() + $exception->retryAfter);
            }
        } catch (Throwable) {
            $reason = 'internal';
        } finally {
            if ($client !== null) {
                $this->WriteAttributeString('Session', json_encode($client->session(), JSON_THROW_ON_ERROR));
            }
            $this->updateState();
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        if ($client !== null && ($client->session()['state'] ?? '') === 'expired') {
            $this->notifyChildren();
        }
        return ['success' => $success, 'message' => $success
            ? $this->Translate(match ($action) {
                'ActivateLostMode'   => 'Lost Mode request accepted by Apple. Activation is not confirmed; offline devices may activate it later. Check Find My before trying again.',
                'DeactivateLostMode' => 'Lost Mode stop request accepted by Apple. Deactivation is not confirmed; offline devices may process it later. The device may remain locked. Check Find My.',
                default              => 'Sound request accepted by Apple. Playback is not confirmed; offline devices may play it later.'
            })
            : $this->errorMessage($reason)];
    }

    private function configurationValid(): bool
    {
        return $this->ReadPropertyBoolean('Enabled') && trim($this->ReadPropertyString('Username')) !== ''
            && $this->ReadPropertyString('Password') !== '' && $this->ReadPropertyInteger('RefreshInterval') >= 60
            && $this->ReadPropertyInteger('RefreshInterval') <= 86400;
    }

    private function credentialFingerprint(): string
    {
        return hash('sha256', json_encode([$this->ReadPropertyString('Username'), $this->ReadPropertyString('Password')], JSON_THROW_ON_ERROR));
    }

    private function readSession(): array
    {
        $session = json_decode($this->ReadAttributeString('Session'), true);
        return is_array($session) ? $session : [];
    }

    private function clearState(): void
    {
        $this->ClearPhotoState();
        $this->WriteAttributeString('Session', '{}');
        $this->WriteAttributeString('DeviceCache', '{"devices":[],"updatedAt":0}');
        $this->WriteAttributeString('LastError', '');
        $this->WriteAttributeInteger('RetryAt', 0);
    }

    private function inventory(): array
    {
        $cache = json_decode($this->ReadAttributeString('DeviceCache'), true);
        $cache = is_array($cache) ? $cache : ['devices' => [], 'updatedAt' => 0];
        $state = (string) ($this->readSession()['state'] ?? 'signedOut');
        if (!$this->ReadPropertyBoolean('Enabled') || $this->credentialFingerprint() !== $this->ReadAttributeString('CredentialFingerprint')) {
            $cache = ['devices' => [], 'updatedAt' => 0];
            $state = 'signedOut';
        }
        $error = $this->ReadAttributeString('LastError');
        $success = $this->configurationValid() && $state === 'authenticated' && $error === '' && (int) ($cache['updatedAt'] ?? 0) > 0;
        return ['success' => $success, 'state' => $state, 'updatedAt' => (int) ($cache['updatedAt'] ?? 0), 'devices' => $cache['devices'] ?? [],
            'error'       => $this->errorMessage($error !== '' ? $error : ($success ? '' : 'authentication'))];
    }

    private function updateState(): void
    {
        $this->SetTimerInterval('Refresh', 0);
        if (!$this->ReadPropertyBoolean('Enabled')) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (!$this->configurationValid()) {
            $this->SetStatus(201);
            return;
        }
        if (!extension_loaded('curl') || !extension_loaded('bcmath')) {
            $this->SetStatus(205);
            return;
        }
        $state = $this->readSession()['state'] ?? 'signedOut';
        $error = $this->ReadAttributeString('LastError');
        if ($state === 'authenticated') {
            $seconds = max($this->ReadPropertyInteger('RefreshInterval'), $this->ReadAttributeInteger('RetryAt') - time());
            $this->SetTimerInterval('Refresh', $seconds * 1000);
        }
        $status = match (true) {
            $this->ReadAttributeInteger('RetryAt') > time() => 206,
            $state === 'awaiting2FA'                        => 202,
            $state === 'expired'                            => 203,
            $error !== ''                                   => 204,
            $state === 'authenticated'                      => IS_ACTIVE,
            default                                         => IS_INACTIVE
        };
        $this->SetStatus($status);
    }

    private function notifyChildren(): void
    {
        $this->SendDataToChildren($this->EncodeDataFlowMessage(self::NOTIFICATION_DATA, $this->inventory()));
    }

    private function errorMessage(string $reason): string
    {
        return $this->Translate(match ($reason) {
            ''                     => '',
            'configuration'        => 'Enable the account and enter valid credentials and interval.',
            'dependency'           => 'PHP extensions curl and bcmath are required.',
            'credentials'          => 'Apple rejected the account credentials. Sign in again.',
            'authentication'       => 'Sign in to the Apple account first.',
            'invalidCode'          => 'Apple rejected the verification code.',
            'codeFormat'           => 'Enter the six-digit verification code.',
            'challengeExpired'     => 'Verification has expired. Sign in again.',
            'rateLimited'          => 'Apple requests a pause. Try again later.',
            'accessDenied'         => 'Apple denied access. Check the account in a browser.',
            'serviceUnavailable'   => 'Find My is unavailable for this account.',
            'network'              => 'The Apple server could not be reached securely.',
            'server'               => 'The Apple service is temporarily unavailable.',
            'endpoint'             => 'Apple returned an unsupported service address.',
            'srp'                  => 'The Apple authentication challenge is unsupported or invalid.',
            'deviceUnknown'        => 'Device not in the authenticated account cache.',
            'soundRejected'        => 'Apple rejected the sound request.',
            'lostModeRejected'     => 'Apple rejected the Lost Mode request. Check Find My before trying again.',
            'lostModeStopRejected' => 'Apple rejected the Lost Mode stop request. Check Find My before trying again.',
            'lostModeUnsupported'  => 'Lost Mode support is missing or unknown for this device. Check Find My.',
            'lostModeActive'       => 'Lost Mode is already enabled according to the account cache. Check Find My.',
            'lostModeInput'        => 'Check the contact number, message (maximum 160 characters) and optional new passcode (4 or 6 digits).',
            default                => 'The Apple response could not be processed.'
        });
    }
}
