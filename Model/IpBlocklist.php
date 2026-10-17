<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model;

/**
 * Matching of the admin "Blocked IP addresses": IPv4/IPv6 addresses or CIDR ranges, one per line.
 *
 * IPv4 is compared as IPv4-mapped IPv6 (::ffff:203.0.113.7), so a client that a dual-stack server reports in mapped
 * form matches an IPv4 entry, and the other way round.
 */
class IpBlocklist
{
    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public function contains(string $list, string $ip): bool
    {
        $address = $this->binary(trim($ip));
        if ($address === null) {
            return false;
        }
        $address = $this->toIpv6($address);
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

    /** IPv4 (4 bytes) as IPv4-mapped IPv6 (16 bytes); IPv6 unchanged. */
    private function toIpv6(string $address): string
    {
        return strlen($address) === 4 ? self::IPV4_MAPPED_PREFIX . $address : $address;
    }

    private function matches(string $address, string $entry): bool
    {
        if (!$this->isValidEntry($entry)) {
            return false;
        }
        [$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
        $network = (string)$this->binary((string)$ip);
        $bits = $bits === null ? strlen($network) * 8 : (int)$bits;
        if (strlen($network) === 4) {
            $bits += 96; // the IPv4 prefix length, counted after the 96-bit mapped prefix
        }
        $network = $this->toIpv6($network);
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
