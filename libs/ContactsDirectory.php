<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Library-local, read-only recipient schema. Provider tokens and unrelated personal fields are discarded. */
final class ContactsDirectory
{
    public static function normalize(array $rows): array
    {
        if (!array_is_list($rows) || count($rows) > 10000) {
            throw new AppleException('contactsLimit');
        }
        $contacts = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['contactId'] ?? null)
                || preg_match('/\A[^\x00-\x20\x7f]{1,256}\z/uD', $row['contactId']) !== 1 || isset($contacts[$row['contactId']])) {
                throw new AppleException('response');
            }
            $first = self::text($row['firstName'] ?? '');
            $last = self::text($row['lastName'] ?? '');
            $company = self::text($row['companyName'] ?? '');
            $contacts[$row['contactId']] = [
                'id'        => $row['contactId'], 'displayName' => trim($first . ' ' . $last) ?: ($company ?: 'Unnamed contact'),
                'firstName' => $first, 'lastName' => $last, 'company' => $company,
                'phones'    => self::addresses($row['phones'] ?? [], 'phoneNumber'),
                'emails'    => self::addresses($row['emailAddresses'] ?? [], 'emailAddress')
            ];
            if (strlen(json_encode($contacts[$row['contactId']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) > 30000) {
                throw new AppleException('contactsLimit');
            }
        }
        $contacts = array_values($contacts);
        usort($contacts, static fn ($a, $b) => strnatcasecmp($a['displayName'], $b['displayName']) ?: strcmp($a['id'], $b['id']));
        return $contacts;
    }

    /** A bounded local query never changes addresses or guesses a preferred channel. */
    public static function page(array $contacts, string $query, int $offset, int $limit): array
    {
        if (strlen($query) > 256 || preg_match('//u', $query) !== 1 || $offset < 0 || $offset > 10000 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Invalid directory query.');
        }
        $needle = preg_quote(trim($query), '/');
        $matches = array_values(array_filter($contacts, static function ($contact) use ($needle): bool
        {
            $fields = [$contact['displayName'], $contact['company'], ...array_column($contact['phones'], 'value'), ...array_column($contact['emails'], 'value')];
            return $needle === '' || preg_match('/' . $needle . '/iu', implode(' ', $fields)) === 1;
        }));
        $page = [];
        $bytes = 0;
        foreach (array_slice($matches, $offset, $limit) as $contact) {
            $size = strlen(json_encode($contact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($bytes + $size > 32000) {
                break;
            }
            $page[] = $contact;
            $bytes += $size;
        }
        $next = $offset + count($page);
        return ['contacts' => $page, 'total' => count($matches), 'offset' => $offset, 'limit' => $limit, 'nextOffset' => $next < count($matches) ? $next : null];
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            return '';
        }
        $value = trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', $value));
        preg_match('/\A.{0,256}/us', $value, $match);
        return $match[0];
    }

    private static function addresses(mixed $entries, string $field): array
    {
        $values = [];
        if ($entries !== null && !is_array($entries)) {
            throw new AppleException('response');
        }
        if (is_array($entries) && count($entries) > 20) {
            throw new AppleException('contactsLimit');
        }
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $raw = $entry[$field] ?? '';
            if (!is_string($raw) || preg_match('//u', $raw) !== 1 || preg_match('/[\x00-\x1f\x7f]/u', $raw) === 1) {
                continue;
            }
            $value = trim($raw);
            if (preg_match('/\A.{0,256}\z/usD', $value) !== 1) {
                // Never shorten a destination into a different, apparently usable address.
                throw new AppleException('contactsLimit');
            }
            if ($value === '' || ($field === 'emailAddress' && filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
                continue;
            }
            $item = ['label' => self::text($entry['label'] ?? ''), 'value' => $value];
            if (!in_array($item, $values, true)) {
                $values[] = $item;
            }
        }
        return $values;
    }
}
