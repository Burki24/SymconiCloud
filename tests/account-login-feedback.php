<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account-protection.php';
ob_end_clean();

$feedback = static function ($a): string
{
    $form = json_decode($a->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
    foreach ($form['actions'] as $item) {
        if (($item['name'] ?? '') === 'AuthenticationResult') {
            return $item['caption'];
        }
    }
    throw new RuntimeException('Authentication actions require a visible result field.');
};
$check(method_exists($account, 'LoginFromForm'), 'The Sign in button needs an explicit, confirmed local recovery path.');
$a = $newAccount('expired');
$setAttribute($a, 'AccountBlock', 'authentication');
$before = $attrs($a);
$form = json_decode($a->GetConfigurationForm(), true);
$check(isset($form['actions'][2]['confirm']) && str_contains($form['actions'][2]['onClick'], 'true'), 'Blocked Sign in must request explicit confirmation.');
$check(!$a->LoginFromForm(false) && $a->transport->requests === [], 'Without confirmation a local hold must not be released or contact Apple.');
$check($attrs($a)['AccountBlock'] === 'authentication' && $attrs($a)['AuthenticationAttempts'] === $before['AuthenticationAttempts'], 'Unconfirmed recovery must preserve the hold and attempt counters.');
$check(str_contains($feedback($a), 'local hold') && str_contains($feedback($a), 'not prove'), 'The result must distinguish a module hold from an Apple account lock.');

$a->transport->enqueue('POST', '/signin/init', 401);
$check(!$a->LoginFromForm(true) && count($a->transport->requests) === 1, 'Confirmed recovery permits exactly one bounded password attempt.');
$check(str_contains($feedback($a), 'rejected') && !str_contains($feedback($a), 'PRIVATE-PASSWORD'), 'Credential refusal needs a safe, visible result.');
$check($attrs($a)['AccountBlock'] === 'credentials' && count(json_decode($attrs($a)['AuthenticationAttempts'], true)['login']) === 1, 'Remote refusal must restore the hold and count the attempt.');
$check(!$a->LoginFromForm(true) && count($a->transport->requests) === 1 && str_contains($feedback($a), '60'), 'Confirmed recovery must not bypass login spacing; the remaining wait must be visible.');

$a = $newAccount('expired');
$setAttribute($a, 'AccountBlock', 'authentication');
$setAttribute($a, 'RetryAt', $a->clock + 720);
$setAttribute($a, 'RetryReason', 'rateLimited');
$check(!$a->LoginFromForm(true) && $a->transport->requests === [] && str_contains($feedback($a), '720'), 'Apple cooldown must remain effective after confirmed recovery.');
$a = $newAccount('expired');
$setAttribute($a, 'AccountBlock', 'authentication');
IPS_SetProperty($idOf($a), 'PauseRequests', true);
$a->ApplyChanges();
$check(!$a->LoginFromForm(true) && $a->transport->requests === [] && str_contains($feedback($a), 'paused'), 'A form sign-in must respect an explicit pause.');
$check($attrs($a)['AccountBlock'] === 'authentication', 'A paused form action must not silently release the hold.');

$a = $newAccount();
$check($a->LoginFromForm(false) && $a->transport->requests === [] && str_contains($feedback($a), 'existing session'), 'Reused authentication must be clearly distinguished from a new password login.');
$form = json_decode($a->GetConfigurationForm(), true);
$check(!isset($form['actions'][2]['confirm']) && str_contains($form['actions'][2]['onClick'], 'false'), 'Normal Sign in must not request a hold-release confirmation.');
$lock = 'SymconiCloud.' . $idOf($a);
IPS_SemaphoreEnter($lock, 1000);
try {
    $check(!$a->Login() && str_contains($feedback($a), 'already running') && $a->transport->requests === [], 'A busy account must return visible feedback without a transport.');
} finally {
    IPS_SemaphoreLeave($lock);
}

$a = $newAccount('signedOut');
$a->transport->enqueue('POST', '/signin/init', 200, $fixtures['vectors'][0]['challenge']);
$a->transport->enqueue('POST', '/signin/complete', 409, [], ['scnt' => 'SECRET-SCNT', 'x-apple-session-token' => 'SECRET-TOKEN']);
$a->transport->enqueue('PUT', '/verify/trusteddevice/securitycode', 204);
$check(!$a->LoginFromForm(false) && str_contains($feedback($a), 'verification code'), '2FA must be reported as pending, not as a completed sign-in or unexplained failure.');
$attempts = $attrs($a)['AuthenticationAttempts'];
$count = count($a->transport->requests);
$check(!$a->LoginFromForm(false) && count($a->transport->requests) === $count && $attrs($a)['AuthenticationAttempts'] === $attempts
    && str_contains($feedback($a), 'already pending'), 'Repeated clicks during 2FA must explain the pending challenge and preserve limits.');
$a->transport->enqueue('POST', '/verify/trusteddevice/securitycode', 204);
$a->transport->enqueue('GET', '/2sv/trust', 204);
$a->transport->enqueue('POST', '/accountLogin', 200, ['dsInfo' => ['dsid' => '12345'], 'webservices' => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com']]]);
$refreshOK($a);
$check($a->VerifyCode('654321') && str_contains($feedback($a), 'Sign-in successful'), 'Successful verification must show an explicit successful sign-in.');
$check(!str_contains($feedback($a), '654321') && !str_contains($feedback($a), 'SECRET-'), 'Feedback must not contain codes or transport secrets.');
$before = $feedback($a);
$refreshOK($a);
$check($a->RefreshDevices() && $feedback($a) === $before, 'Background device refresh must not overwrite the manual authentication result.');
$a->Logout();
$check($feedback($a) === '', 'Deleting the local session must clear an obsolete successful sign-in result.');

echo "Sign-in feedback, confirmed recovery, cancellation, 2FA and unchanged protection limits passed.\n";
