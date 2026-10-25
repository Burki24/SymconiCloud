<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/account-protection.php';
ob_end_clean();

$row = ['contactId'  => 'synthetic-contact-1', 'firstName' => 'Alex', 'lastName' => 'Example', 'companyName' => 'Example Ltd',
    'phones'         => [['label' => 'mobile', 'phoneNumber' => '+49 170 1234567']],
    'emailAddresses' => [['label' => 'home', 'emailAddress' => 'alex@example.invalid']],
    'notes'          => 'PRIVATE-NOTE', 'birthday' => 'PRIVATE-BIRTHDAY', 'photo' => ['url' => 'https://private.invalid']];
$normalized = \SymconiCloud\ContactsDirectory::normalize([$row, ['contactId' => 'empty']]);
$check($normalized[0]['displayName'] === 'Alex Example' && $normalized[0]['phones'][0] === ['label' => 'mobile', 'value' => '+49 170 1234567'], 'Names and labelled addresses must retain user spelling without guessing a preferred route.');
$check($normalized[1]['phones'] === [] && $normalized[1]['emails'] === [] && $normalized[1]['displayName'] === 'Unnamed contact', 'Absent fields must be explicit empty values.');
$check(array_keys($normalized[0]) === ['id', 'displayName', 'firstName', 'lastName', 'company', 'phones', 'emails'], 'Only the recipient allowlist may leave the provider adapter.');
$check(\SymconiCloud\ContactsDirectory::page($normalized, 'EXAMPLE.INVALID', 0, 10)['total'] === 1, 'Search must include email addresses and be case insensitive.');
foreach ([[-1, 10], [0, 0], [0, 101]] as [$offset, $limit]) {
    try {
        \SymconiCloud\ContactsDirectory::page([], '', $offset, $limit);
        throw new RuntimeException('Invalid query accepted.');
    } catch (InvalidArgumentException) {
    }
}
foreach ([[$row, $row], [['firstName' => 'No ID']], [['contactId' => 'bad' . "\n"]]] as $invalid) {
    try {
        \SymconiCloud\ContactsDirectory::normalize($invalid);
        throw new RuntimeException('Invalid contact snapshot accepted.');
    } catch (\SymconiCloud\AppleException $error) {
        $check($error->reason === 'response', 'Malformed snapshots must fail atomically.');
    }
}

$directoryAccount = static function () use ($newAccount, $setAttribute, $attrs, $idOf)
{
    $a = $newAccount();
    IPS_SetProperty($idOf($a), 'EnableContacts', true);
    $a->ApplyChanges();
    $session = json_decode($attrs($a)['Session'], true);
    $session['contacts'] = 'https://p01-contacts.icloud.com';
    $setAttribute($a, 'Session', json_encode($session, JSON_THROW_ON_ERROR));
    $a->transport = new AccountFixtureTransport();
    return $a;
};
$enqueueContacts = static function ($a, array $rows) use ($row): void
{
    $a->transport->enqueue('GET', '/co/startup', 200, ['prefToken' => 'PRIVATE-PREF', 'syncToken' => 'PRIVATE-SYNC']);
    $a->transport->enqueue('GET', '/co/contacts', 200, ['contacts' => $rows]);
};
$contactTimer = static function ($a): int
{
    $delegate = (new ReflectionProperty(IPSModuleStrict::class, 'module'))->getValue($a);
    $timers = (new ReflectionProperty(IPSModule::class, 'timers'))->getValue($delegate);
    return $timers['ContactsRefresh']['millis'];
};

$a = $directoryAccount();
$check($a->transport->requests === [] && $contactTimer($a) === 60000, 'Enabling Contacts must schedule an initial cached-session read without HTTP in ApplyChanges.');
$enqueueContacts($a, [$row]);
$beforeDeviceCache = $attrs($a)['DeviceCache'];
$check($a->RefreshContacts(), 'Contacts must load using the existing session.');
$result = json_decode($a->GetContacts(), true);
$check($result['success'] && !$result['stale'] && $result['total'] === 1 && $contactTimer($a) === 3600000, 'A complete snapshot must become current and use the independent hourly timer.');
$check($attrs($a)['DeviceCache'] === $beforeDeviceCache && count($a->transport->requests) === 2, 'Contact refresh must neither refresh devices nor perform a password sign-in.');
parse_str(parse_url($a->transport->requests[1]['url'], PHP_URL_QUERY), $parameters);
$check($parameters['includePhoneNumbers'] === 'true' && $parameters['includePhotos'] === 'false' && $parameters['limit'] === '0' && $parameters['offset'] === '0', 'The protocol must request phone numbers and a complete snapshot, without photo resources.');
$check(json_decode($a->GetContact('synthetic-contact-1'), true)['contact']['displayName'] === 'Alex Example', 'Exact ID lookup must resolve a cached recipient.');
$check(json_decode($a->GetContact('deleted-contact'), true)['contact'] === null, 'Missing IDs must never fall back to another recipient.');
$export = $a->GetContacts() . $a->GetAccountDiagnostics();
foreach (['PRIVATE-NOTE', 'PRIVATE-BIRTHDAY', 'PRIVATE-PREF', 'PRIVATE-SYNC', 'SYNTHETIC-TOKEN-SECRET', 'https://'] as $secret) {
    $check(!str_contains($export, $secret), 'Directory and diagnostics must exclude unrelated fields, URLs and tokens.');
}
$diagnostics = $a->GetAccountDiagnostics();
$check(!str_contains($diagnostics, 'Alex') && !str_contains($diagnostics, 'example.invalid') && !str_contains($diagnostics, '170'), 'Diagnostics must not contain contact content.');
$check(!$a->RefreshContacts() && count($a->transport->requests) === 2 && !json_decode($a->GetContacts(), true)['stale'], 'Rapid repeat clicks must not perform HTTP or invalidate a good snapshot.');

// Real parent/child flow: browsing and form opening never perform HTTP, and IDs remain instance-scoped.
$childID = IPS_CreateInstance('{D9D8872B-3323-41AC-BB8F-D2756D5694E1}');
IPS_ConnectInstance($childID, $idOf($a));
IPS_ApplyChanges($childID);
$child = IPS\InstanceManager::getInstanceInterface($childID);
$check(json_decode(ICCT_GetContacts($childID, 'Alex', 0, 100), true)['total'] === 1, 'The public child API must expose searchable account cache pages.');
$check(json_decode(ICCT_GetContact($childID, 'synthetic-contact-1'), true)['contact']['id'] === 'synthetic-contact-1', 'The messaging seam must resolve the exact contact ID.');
$form = json_decode(IPS_GetConfigurationForm($childID), true);
$check($form['actions'][2]['values'][0]['Name'] === 'Alex Example', 'The console directory must show normalized recipients.');
ICCT_SearchContacts($childID, 'not-present');
$check(json_decode(IPS_GetConfigurationForm($childID), true)['actions'][2]['values'] === [], 'Search must update the local read-only list.');
$check(count($a->transport->requests) === 2, 'Connecting, browsing, API reads and form opening must be free of Apple traffic.');

// Pause keeps both session and directory byte-identical, stops the timer, and prevents HTTP.
$sessionBefore = $attrs($a)['Session'];
$cacheBefore = $attrs($a)['ContactsCache'];
IPS_SetProperty($idOf($a), 'PauseRequests', true);
$a->ApplyChanges();
$check(!$a->RefreshContacts() && $contactTimer($a) === 0 && count($a->transport->requests) === 2, 'Pause must stop contact HTTP and its timer.');
$paused = json_decode($a->GetContacts(), true);
$check($paused['success'] && $paused['paused'] && $paused['stale'] && $paused['total'] === 1, 'Paused cached recipients must stay readable but be visibly non-current.');
$check($attrs($a)['Session'] === $sessionBefore && $attrs($a)['ContactsCache'] === $cacheBefore, 'Pause must preserve shared session and directory byte for byte.');
IPS_SetProperty($idOf($a), 'PauseRequests', false);
$a->ApplyChanges();
$a->clock += 3601;
$enqueueContacts($a, []);
$check($a->RefreshContacts() && json_decode($a->GetContacts(), true)['success'] && json_decode($a->GetContacts(), true)['total'] === 0, 'A successfully empty address book is valid and must remove deleted contacts.');
$check(json_decode($child->GetContact('synthetic-contact-1'), true)['contact'] === null, 'Deleted recipients must disappear from connected consumers.');

// Partial, malformed and failed snapshots must retain the preceding complete snapshot.
foreach ([403, 429, 423, 401, 503] as $status) {
    $a = $directoryAccount();
    $enqueueContacts($a, [$row]);
    $check($a->RefreshContacts(), 'Seed snapshot failed.');
    $before = $attrs($a)['ContactsCache'];
    $a->clock += 60;
    $a->transport->enqueue('GET', '/co/startup', $status, ['error' => 'PRIVATE-RESPONSE'], ['retry-after' => '600']);
    $check(!$a->RefreshContacts() && $attrs($a)['ContactsCache'] === $before && json_decode($a->GetContacts(), true)['stale'], 'Provider failures must retain and mark the previous snapshot.');
    if ($status === 403) {
        $check($attrs($a)['AccountBlock'] === '' && json_decode($attrs($a)['Session'], true)['state'] === 'authenticated' && json_decode($a->GetDevices(), true)['success'], 'Contact-only denial must leave shared authentication and device data usable.');
    } else {
        $calls = count($a->transport->requests);
        $check(!$a->RefreshContacts() && count($a->transport->requests) === $calls, 'Shared protection must suppress retries after auth, lock, throttle and server failures.');
        if ($status === 401) {
            $check(json_decode($attrs($a)['Session'], true)['state'] === 'expired' && $contactTimer($a) === 0, 'Expired contact authentication must stop timers without retrying password login.');
        }
        if ($status === 429) {
            $check($attrs($a)['RetryAt'] === $a->clock + 600 && $attrs($a)['AccountBlock'] === '', 'Contacts must share the account throttle deadline.');
        }
        if ($status === 423) {
            $check($attrs($a)['AccountBlock'] === 'accountLocked' && $contactTimer($a) === 0, 'Contacts must respect persistent manual account holds.');
        }
    }
}
$a = $directoryAccount();
$enqueueContacts($a, [$row]);
$check($a->RefreshContacts(), 'Seed snapshot failed.');
$before = $attrs($a)['ContactsCache'];
$a->clock += 60;
$enqueueContacts($a, [['firstName' => 'Incomplete']]);
$check(!$a->RefreshContacts() && $attrs($a)['ContactsCache'] === $before, 'Invalid snapshots must not partially replace good data.');
$check(!str_contains($a->GetAccountDiagnostics(), 'Incomplete'), 'Malformed provider content must not leak into diagnostics.');

// Token-based discovery upgrades an old session without calling signin endpoints.
$a = $directoryAccount();
$session = json_decode($attrs($a)['Session'], true);
unset($session['contacts']);
$setAttribute($a, 'Session', json_encode($session, JSON_THROW_ON_ERROR));
$a->transport->enqueue('POST', '/accountLogin', 200, ['dsInfo' => ['dsid' => '1234'], 'webservices' => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com'], 'contacts' => ['url' => 'https://p01-contacts.icloud.com']]]);
$enqueueContacts($a, [$row]);
$check($a->RefreshContacts() && count($a->transport->requests) === 3, 'Old sessions must discover Contacts once with the existing token.');
$check($a->transport->requests[0]['body']['dsWebAuthToken'] === $session['headers']['x-apple-session-token'], 'Discovery must use the existing token and not submit credentials.');
foreach ($a->transport->requests as $request) {
    $check(!str_contains($request['url'], '/signin') && !str_contains(json_encode($request), 'PRIVATE-PASSWORD'), 'No contact path may perform password authentication.');
}

// Scope removal, account switching and malformed queries are covered at the public seam.
$check(!$forward($a, 'GetContacts', ['URL' => 'https://private.invalid'])['success'] && !$forward($a, 'GetContacts', ['Query' => null])['success'], 'Extra fields and mistyped queries must be rejected.');
IPS_SetProperty($idOf($a), 'ContactsRefreshInterval', 899);
$a->ApplyChanges();
$calls = count($a->transport->requests);
$a->clock += 3600;
$check(!$a->RefreshContacts() && count($a->transport->requests) === $calls && $contactTimer($a) === 0, 'Invalid intervals must stop only the contact timer and prevent requests.');
IPS_SetProperty($idOf($a), 'EnableContacts', false);
$a->ApplyChanges();
$check(!json_decode($a->GetContacts(), true)['success'] && json_decode($attrs($a)['ContactsCache'], true)['contacts'] === [], 'Disabling Contacts must remove its private cache.');
IPS_SetProperty($idOf($a), 'EnableContacts', true);
IPS_SetProperty($idOf($a), 'ContactsRefreshInterval', 3600);
$a->ApplyChanges();
$enqueueContacts($a, [$row]);
$check($a->RefreshContacts(), 'Re-enabled cache failed.');
IPS_SetProperty($idOf($a), 'Username', 'another@example.invalid');
IPS_ApplyChanges($idOf($a));
$check(!json_decode($a->GetContacts(), true)['success'], 'Applied credential scope changes must not expose the old contact cache.');
$check(json_decode($attrs($a)['ContactsCache'], true)['contacts'] === [] && $contactTimer($a) === 0, 'Account changes must clear the directory and stop its unauthenticated timer.');

// Oversized public pages are shortened by byte size with an explicit continuation offset.
$large = [];
for ($i = 0; $i < 30; $i++) {
    $copy = $row;
    $copy['contactId'] = 'large-' . $i;
    $copy['phones'] = array_map(static fn ($n) => ['label' => str_repeat('x', 240), 'phoneNumber' => str_repeat('1', 240) . $n], range(1, 20));
    $large[] = $copy;
}
$large = \SymconiCloud\ContactsDirectory::normalize($large);
$offset = 0;
$seen = [];
do {
    $page = \SymconiCloud\ContactsDirectory::page($large, '', $offset, 100);
    $check(strlen(json_encode($page, JSON_UNESCAPED_UNICODE)) < 60000 && $page['contacts'] !== [], 'Transport pages must stay below the Symcon string budget and make progress.');
    $seen = [...$seen, ...array_column($page['contacts'], 'id')];
    $offset = $page['nextOffset'];
} while ($offset !== null);
$check(count(array_unique($seen)) === 30 && count($seen) === 30, 'Byte-limited continuation must neither skip nor duplicate contacts.');

// Unsupported destinations and unusually large contacts must fail instead of creating a changed recipient or an endless page.
$oversized = $row;
$oversized['phones'][0]['phoneNumber'] = str_repeat('1', 257);
try {
    \SymconiCloud\ContactsDirectory::normalize([$oversized]);
    throw new RuntimeException('A destination was silently shortened.');
} catch (\SymconiCloud\AppleException $error) {
    $check($error->reason === 'contactsLimit', 'Overlong destinations require a size failure.');
}
$oversized['phones'] = array_fill(0, 21, $row['phones'][0]);
try {
    \SymconiCloud\ContactsDirectory::normalize([$oversized]);
    throw new RuntimeException('Addresses were silently dropped.');
} catch (\SymconiCloud\AppleException $error) {
    $check($error->reason === 'contactsLimit', 'An oversized address list must not be truncated silently.');
}
foreach (['', 'https://p01-contacts.icloud.com.evil.invalid', 'https://p01-contacts.icloud.com/private?token=secret'] as $endpoint) {
    $a = $directoryAccount();
    $session = json_decode($attrs($a)['Session'], true);
    unset($session['contacts']);
    $setAttribute($a, 'Session', json_encode($session, JSON_THROW_ON_ERROR));
    $a->transport->enqueue('POST', '/accountLogin', 200, ['dsInfo' => ['dsid' => '1234'], 'webservices' => ['findme' => ['url' => 'https://p01-fmipweb.icloud.com'], 'contacts' => ['url' => $endpoint]]]);
    $check(!$a->RefreshContacts() && count($a->transport->requests) === 1 && $attrs($a)['AccountBlock'] === '', 'Missing or unsafe optional service addresses must stop Contacts without submitting credentials or blocking other services.');
    $check(json_decode($attrs($a)['Session'], true)['state'] === 'authenticated', 'Unavailable Contacts must retain the shared authenticated session.');
}
$a = $directoryAccount();
$enqueueContacts($a, [$row]);
$check($a->RefreshContacts(), 'Restart seed snapshot failed.');
$saved = $a->GetPersistence();
$restoredID = IPS_CreateInstance($accountModule);
$restored = IPS\InstanceManager::getInstanceInterface($restoredID);
$restored->ApplyPersistence($saved);
$restored->ApplyChanges();
$restored->ApplyChanges();
$check($attrs($restored)['ContactsCache'] === $saved['attributes']['ContactsCache'] && $restored->transport === null && json_decode($restored->GetContacts(), true)['total'] === 1, 'Restart and repeated ApplyChanges must preserve contacts without creating a transport.');
IPS_SemaphoreEnter('SymconiCloud.' . $restoredID, 1000);
try {
    $check(!$restored->RefreshContacts() && $restored->transport === null, 'Concurrent account operations must not start a second contact request.');
} finally {
    IPS_SemaphoreLeave('SymconiCloud.' . $restoredID);
}

echo "Read-only Contacts discovery, recipient schema, protected account cache, module API, privacy and paging passed.\n";
