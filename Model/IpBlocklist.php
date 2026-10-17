<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model;

/** Matching of the admin "Blocked IP addresses": IPv4/IPv6 addresses or CIDR ranges, one per line. */
class IpBlocklist
{
    public function contains(string $list, string $ip): bool
    {
        $address = $this->binary(trim($ip));
        if ($address === null) {
            return false;
        }
        foreach ($this->parse($list) as $entry) {
            if ($this->matches($address, $entry)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Entries of the text box: one per line (commas and spaces also separate), blank lines ignored.
     *
     * @return string[]
     */
    public function parse(string $list): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $list) ?: [])));
    }

    /** An IPv4/IPv6 address, or one with a prefix length: 203.0.113.0/24, 2001:db8::/32. */
    public function isValidEntry(string $entry): bool
    {
        [$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
        $address = $this->binary((string)$ip);
        if ($address === null) {
            return false;
        }
        return $bits === null || (ctype_digit($bits) && (int)$bits <= strlen($address) * 8);
    }

    private function binary(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : (inet_pton($ip) ?: null);
    }

    private function matches(string $address, string $entry): bool
    {
        if (!$this->isValidEntry($entry)) {
            return false;
        }
        [$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
        $network = (string)$this->binary((string)$ip);
        if (strlen($network) !== strlen($address)) {
            return false; // IPv4 entry, IPv6 client or the other way round
        }
        $bits = $bits === null ? strlen($network) * 8 : (int)$bits;
        $bytes = intdiv($bits, 8);
        if (substr($address, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
