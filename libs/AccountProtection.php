<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/helper/DebugHelper.php';

/** Apple-specific protection policy. Counters and holds survive logout and configuration changes. */
trait AccountProtection
{
    use \Burki24\SymconModuleHelper\DebugHelper;

    private const AUTH_LIMITS = ['login' => [3, 1800, 60], 'verify' => [5, 600, 0], 'resend' => [3, 600, 60]];
    private const DIAGNOSTIC_OPERATIONS = ['login', 'verify', 'resend', 'devices', 'contacts', 'photoCatalog', 'photoPage', 'photoImage', 'PlaySound', 'ActivateLostMode', 'DeactivateLostMode', 'signinInit', 'signinComplete', 'verifyCode', 'requestCode', 'trustBrowser', 'authChallenge', 'sessionRenewal', 'photos', 'deviceAction', 'pause', 'resume', 'other'];
    private const PROTECTION_REASONS = ['credentials', 'authentication', 'accessDenied', 'accountLocked'];
    private const DIAGNOSTIC_REASONS = ['ok', 'sessionReused', 'verificationRequired', 'requestsPaused', 'accountBlocked', 'loginLimited', 'codeLimited', 'resendLimited', 'challengePending', 'protectionState', 'rateLimited', 'retryBackoff', 'credentials', 'authentication', 'accessDenied', 'accountLocked', 'network', 'server', 'response', 'internal', 'configuration', 'invalidCode', 'codeFormat', 'challengeExpired', 'dependency', 'srp', 'endpoint', 'serviceUnavailable', 'contactsAccess', 'contactsUnavailable', 'contactsDisabled', 'contactsConfiguration', 'contactsWait', 'contactsLimit', 'photosAccess', 'photosUnavailable', 'photosDisabled', 'photosIndexing', 'photosAlbum', 'photosCache', 'photoDownload', 'photoFormat', 'soundRejected', 'lostModeRejected', 'lostModeStopRejected', 'deviceUnknown', 'lostModeUnsupported', 'lostModeActive', 'lostModeInput'];

    /** Local recovery only: never logs in, clears a cooldown, or resets attempt limits. */
    public function ResumeAccountRequests(): void
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return;
        }
        try {
            try {
                $this->authenticationAttempts();
            } catch (AppleException $error) {
                $this->AccountRequestFailed('resume', $error, false);
                $this->updateState();
                $this->setAuthenticationFeedback('resume', $error->reason);
                return;
            }
            $this->WriteAttributeString('AccountBlock', '');
            if (in_array($this->ReadAttributeString('LastError'), self::PROTECTION_REASONS, true)) {
                $this->WriteAttributeString('LastError', '');
            }
            $this->RecordAccountDiagnostic('resume', 0, 'ok');
            $this->updateState();
            $this->setAuthenticationFeedback('resume', 'ok');
        } finally {
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        $this->notifyChildren();
        $this->ReloadForm();
    }

    /** Bounded, allowlisted records only; no provider payloads, identities, URLs, or secrets. */
    public function GetAccountDiagnostics(): string
    {
        $raw = json_decode($this->ReadAttributeString('AccountDiagnostics'), true);
        $entries = [];
        foreach (is_array($raw) ? array_slice($raw, -50) : [] as $entry) {
            if (!is_array($entry) || !is_int($entry['time'] ?? null) || !is_int($entry['httpStatus'] ?? null) || !is_int($entry['waitSeconds'] ?? null)
                || !in_array($entry['operation'] ?? null, self::DIAGNOSTIC_OPERATIONS, true) || !in_array($entry['reason'] ?? null, self::DIAGNOSTIC_REASONS, true)) {
                continue;
            }
            $entries[] = ['time' => max(0, $entry['time']), 'operation' => $entry['operation'], 'httpStatus' => max(0, min(599, $entry['httpStatus'])), 'reason' => $entry['reason'], 'waitSeconds' => max(0, min(86400, $entry['waitSeconds']))];
        }
        return json_encode($entries, JSON_THROW_ON_ERROR);
    }

    /** Clearing diagnostic history cannot release a hold or reset security counters. */
    public function ClearAccountDiagnostics(): void
    {
        if (!IPS_SemaphoreEnter('SymconiCloud.' . $this->InstanceID, 1000)) {
            return;
        }
        try {
            $this->WriteAttributeString('AccountDiagnostics', '[]');
        } finally {
            IPS_SemaphoreLeave('SymconiCloud.' . $this->InstanceID);
        }
        $this->ReloadForm();
    }

    protected function RegisterAccountProtection(): void
    {
        $this->RegisterPropertyBoolean('PauseRequests', false);
        $this->RegisterAttributeString('AuthenticationAttempts', '{}');
        $this->RegisterAttributeString('AccountBlock', '');
        $this->RegisterAttributeString('AccountDiagnostics', '[]');
        $this->RegisterAttributeInteger('TransientFailures', 0);
        $this->RegisterAttributeString('RetryReason', '');
        $this->RegisterAttributeBoolean('WasRequestsPaused', false);
    }

    /** Clock seam for deterministic tests; production uses wall-clock seconds. */
    protected function AccountProtectionTime(): int
    {
        return time();
    }

    protected function AssertAccountRequestsAllowed(): void
    {
        if ($this->ReadPropertyBoolean('PauseRequests')) {
            throw new AppleException('requestsPaused');
        }
        if ($this->ReadAttributeString('AccountBlock') !== '') {
            throw new AppleException('accountBlocked');
        }
        if ($this->ReadAttributeInteger('RetryAt') > $this->AccountProtectionTime()) {
            throw new AppleException(in_array($this->ReadAttributeString('RetryReason'), ['network', 'server'], true) ? 'retryBackoff' : 'rateLimited');
        }
    }

    protected function AuthenticationWait(string $kind): int
    {
        [$limit, $window, $spacing] = self::AUTH_LIMITS[$kind];
        $times = array_values(array_filter($this->authenticationAttempts()[$kind] ?? [], fn ($t) => $t > $this->AccountProtectionTime() - $window));
        $wait = $times === [] ? 0 : max($times) + $spacing - $this->AccountProtectionTime();
        return max(0, $wait, count($times) >= $limit ? min($times) + $window - $this->AccountProtectionTime() : 0);
    }

    protected function ReserveAuthenticationAttempt(string $kind): void
    {
        if ($this->AuthenticationWait($kind) > 0) {
            throw new AppleException(match ($kind) {
                'login' => 'loginLimited', 'verify' => 'codeLimited', default => 'resendLimited'
            });
        }
        $attempts = $this->authenticationAttempts();
        $attempts[$kind] = array_values(array_filter($attempts[$kind] ?? [], fn ($t) => $t > $this->AccountProtectionTime() - self::AUTH_LIMITS[$kind][1]));
        $attempts[$kind][] = $this->AccountProtectionTime();
        $this->WriteAttributeString('AuthenticationAttempts', json_encode($attempts, JSON_THROW_ON_ERROR));
    }

    protected function AccountRequestFailed(string $operation, AppleException $error, bool $remote): void
    {
        if ($remote && in_array($error->reason, self::PROTECTION_REASONS, true)) {
            $this->WriteAttributeString('AccountBlock', $error->reason);
        }
        if ($error->reason === 'protectionState') {
            $this->WriteAttributeString('AccountBlock', 'protectionState');
        }
        if ($error->retryAfter > 0) {
            $this->WriteAttributeInteger('RetryAt', max($this->ReadAttributeInteger('RetryAt'), $this->AccountProtectionTime() + $error->retryAfter));
            $this->WriteAttributeString('RetryReason', 'rateLimited');
        } elseif ($remote && in_array($error->reason, ['network', 'server'], true)) {
            $failures = min(7, max(0, $this->ReadAttributeInteger('TransientFailures')) + 1);
            $this->WriteAttributeInteger('TransientFailures', $failures);
            $this->WriteAttributeString('RetryReason', $error->reason);
            $this->WriteAttributeInteger('RetryAt', max($this->ReadAttributeInteger('RetryAt'), $this->AccountProtectionTime() + min(3600, 60 * (2 ** ($failures - 1)))));
        }
        $this->RecordAccountDiagnostic($operation, $error->httpStatus, $error->reason);
    }

    protected function RecordAccountDiagnostic(string $operation, int $status, string $reason): void
    {
        try {
            $wait = max(0, $this->ReadAttributeInteger('RetryAt') - $this->AccountProtectionTime());
            $kind = match ($reason) {
                'loginLimited' => 'login', 'codeLimited' => 'verify', 'resendLimited' => 'resend', default => null
            };
            if ($kind !== null) {
                $wait = max($wait, $this->AuthenticationWait($kind));
            }
            $entry = ['time'  => $this->AccountProtectionTime(), 'operation' => in_array($operation, self::DIAGNOSTIC_OPERATIONS, true) ? $operation : 'other',
                'httpStatus'  => max(0, min(599, $status)), 'reason' => in_array($reason, self::DIAGNOSTIC_REASONS, true) ? $reason : 'internal',
                'waitSeconds' => min(86400, $wait)];
            $entries = json_decode($this->GetAccountDiagnostics(), true, 512, JSON_THROW_ON_ERROR);
            $entries[] = $entry;
            $this->WriteAttributeString('AccountDiagnostics', json_encode(array_slice($entries, -50), JSON_THROW_ON_ERROR));
            $this->SendSafeDebug('AccountProtection', $entry, 2048);
        } catch (\Throwable) {
            // Diagnostics must never change authentication or device-command outcomes.
        }
    }

    private function authenticationAttempts(): array
    {
        $attempts = json_decode($this->ReadAttributeString('AuthenticationAttempts'), true);
        if (!is_array($attempts)) {
            throw new AppleException('protectionState');
        }
        foreach ($attempts as $kind => $times) {
            if (!isset(self::AUTH_LIMITS[$kind]) || !is_array($times) || !array_is_list($times) || count($times) > 5) {
                throw new AppleException('protectionState');
            }
            foreach ($times as $timestamp) {
                if (!is_int($timestamp) || $timestamp < 0) {
                    throw new AppleException('protectionState');
                }
            }
        }
        return $attempts;
    }
}
