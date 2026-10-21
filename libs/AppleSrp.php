<?php

declare(strict_types=1);

namespace SymconiCloud;

require_once __DIR__ . '/AppleException.php';

/** Independent SRP-6a implementation of Apple's s2k/s2k_fo wire protocol. */
final class AppleSrp
{
    // RFC 5054, Appendix A, 2048-bit group; generator 2.
    private const N = 'AC6BDB41324A9A9BF166DE5E1389582FAF72B6651987EE07FC3192943DB56050A37329CBB4A099ED8193E0757767A13DD52312AB4B03310DCD7F48A9DA04FD50E8083969EDB767B0CF6095179A163AB3661A05FBD5FAAAE82918A9962F0B93B855F97993EC975EEAA80D740ADBF4FF747359D041D5C33EA71D281E446B14773BCA97B43A23FB801676BD207A436C6481F1D2B9078717461A5B9D32E688F87748544523B524B0D57D5EA77A2775D2ECFA032CFBDBF52FB3786160279004E57AE6AF874E7303CE53299CCC041C7BC308D82A5698F3A8D0C38271AE35F8E9DBFBB694B5C803D89F7AE435DE236D525F54759B65E372FCD68EF20FA7111F9E4AFF73';
    private string $n;
    private string $a;
    private string $public;

    /** $private is a test seam; production always uses random_bytes. */
    public function __construct(private readonly string $username, ?string $private = null)
    {
        if (!extension_loaded('bcmath')) {
            throw new AppleException('dependency');
        }
        $this->n = self::decimal(hex2bin(self::N));
        $this->a = self::decimal($private ?? random_bytes(32));
        if (bccomp($this->a, '0', 0) <= 0) {
            throw new AppleException('srp');
        }
        $this->public = self::bytes(bcpowmod('2', $this->a, $this->n, 0));
    }

    /** Returns base64 client public ephemeral for signin/init. */
    public function publicValue(): string
    {
        return base64_encode($this->public);
    }

    /** @return array{m1:string,m2:string} Base64 proofs; password never leaves this operation. */
    public function proofs(string $password, array $challenge): array
    {
        $protocol = $challenge['protocol'] ?? '';
        $iterations = $challenge['iteration'] ?? 0;
        $salt = is_string($challenge['salt'] ?? null) ? base64_decode($challenge['salt'], true) : false;
        $server = is_string($challenge['b'] ?? null) ? base64_decode($challenge['b'], true) : false;
        if (!in_array($protocol, ['s2k', 's2k_fo'], true) || !is_int($iterations) || $iterations < 1 || $iterations > 1000000
            || $salt === false || strlen($salt) < 1 || strlen($salt) > 64 || $server === false || strlen($server) < 1 || strlen($server) > 256) {
            throw new AppleException('srp');
        }
        $b = self::decimal($server);
        if (bccomp($b, '0', 0) <= 0 || bccomp($b, $this->n, 0) >= 0) {
            throw new AppleException('srp');
        }
        $server = self::bytes($b);
        $digest = hash('sha256', $password, $protocol === 's2k');
        $derived = hash_pbkdf2('sha256', $digest, $salt, $iterations, 32, true);
        $x = self::decimal(self::hash($salt . self::hash(':' . $derived)));
        $nBytes = hex2bin(self::N);
        $gPadded = str_pad("\x02", 256, "\x00", STR_PAD_LEFT);
        $k = self::decimal(self::hash($nBytes . $gPadded));
        $u = self::decimal(self::hash(str_pad($this->public, 256, "\x00", STR_PAD_LEFT) . str_pad($server, 256, "\x00", STR_PAD_LEFT)));
        if (bccomp($u, '0', 0) === 0) {
            throw new AppleException('srp');
        }
        $base = bcmod(bcsub($b, bcmul($k, bcpowmod('2', $x, $this->n, 0), 0), 0), $this->n, 0);
        if (bccomp($base, '0', 0) < 0) {
            $base = bcadd($base, $this->n, 0);
        }
        $exponent = bcadd($this->a, bcmul($u, $x, 0), 0);
        $key = self::hash(self::bytes(bcpowmod($base, $exponent, $this->n, 0)));
        $m1 = self::hash((self::hash($nBytes) ^ self::hash($gPadded)) . self::hash($this->username) . $salt . $this->public . $server . $key);
        return ['m1' => base64_encode($m1), 'm2' => base64_encode(self::hash($this->public . $m1 . $key))];
    }

    private static function hash(string $value): string
    {
        return hash('sha256', $value, true);
    }

    private static function decimal(string $bytes): string
    {
        $number = '0';
        foreach (unpack('C*', $bytes) as $byte) {
            $number = bcadd(bcmul($number, '256', 0), (string) $byte, 0);
        }
        return $number;
    }

    private static function bytes(string $number): string
    {
        $bytes = '';
        while (bccomp($number, '0', 0) > 0) {
            $bytes = chr((int) bcmod($number, '256', 0)) . $bytes;
            $number = bcdiv($number, '256', 0);
        }
        return $bytes === '' ? "\x00" : $bytes;
    }
}
