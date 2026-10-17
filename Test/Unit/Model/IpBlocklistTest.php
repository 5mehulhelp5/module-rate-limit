<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Model;

use CommerceKings\RateLimit\Model\IpBlocklist;
use PHPUnit\Framework\TestCase;

class IpBlocklistTest extends TestCase
{
    private const LIST = "203.0.113.7\n198.51.100.0/24, 10.1.0.0/17\n\n2001:db8::/32\n::1\n";

    /** @return array<string, array{string, bool}> */
    public static function addresses(): array
    {
        return [
            'exact IPv4' => ['203.0.113.7', true],
            'neighbour of exact IPv4' => ['203.0.113.8', false],
            'inside /24' => ['198.51.100.254', true],
            'outside /24' => ['198.51.101.1', false],
            '/17 lower half' => ['10.1.127.255', true],
            '/17 upper half' => ['10.1.128.0', false],
            'inside IPv6 /32' => ['2001:db8:ffff::1', true],
            'outside IPv6 /32' => ['2001:db9::1', false],
            'exact IPv6' => ['::1', true],
            'IPv4 client, IPv6 entries only' => ['0.0.0.1', false],
            'IPv4-mapped client, exact IPv4 entry' => ['::ffff:203.0.113.7', true],
            'IPv4-mapped client inside IPv4 /24' => ['::ffff:198.51.100.254', true],
            'IPv4-mapped client outside IPv4 /24' => ['::ffff:198.51.101.1', false],
            'IPv4-mapped client, upper case' => ['::FFFF:203.0.113.7', true],
            'IPv4-mapped client, hex form' => ['::ffff:cb00:7107', true],
            'surrounding spaces' => [' 203.0.113.7 ', true],
            'not an address' => ['unknown', false],
            'empty' => ['', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('addresses')]
    public function testMatchesAddressesAndRanges(string $ip, bool $blocked): void
    {
        $this->assertSame($blocked, (new IpBlocklist())->contains(self::LIST, $ip));
    }

    public function testMappedEntriesMatchIpv4Clients(): void
    {
        $list = "::ffff:192.0.2.9\n::ffff:192.0.2.128/121";
        $blocklist = new IpBlocklist();
        $this->assertTrue($blocklist->contains($list, '192.0.2.9'));
        $this->assertTrue($blocklist->contains($list, '192.0.2.200'));
        $this->assertFalse($blocklist->contains($list, '192.0.2.127'));
        $this->assertFalse($blocklist->contains($list, '::192.0.2.9'), 'IPv4-compatible is not IPv4-mapped');
    }

    public function testEmptyListBlocksNobody(): void
    {
        $this->assertFalse((new IpBlocklist())->contains('', '203.0.113.7'));
    }

    public function testInvalidStoredEntriesAreSkipped(): void
    {
        $list = "not-an-ip\n203.0.113.0/33\n203.0.113.7";
        $this->assertTrue((new IpBlocklist())->contains($list, '203.0.113.7'));
        $this->assertFalse((new IpBlocklist())->contains($list, '203.0.113.8'));
    }
}
