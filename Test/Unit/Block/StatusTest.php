<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Block;

use CommerceKings\RateLimit\Block\Adminhtml\Status;
use PHPUnit\Framework\TestCase;

/** The warning for a proxy that hides the visitor's address: then every guest shares one counter. */
class StatusTest extends TestCase
{
    public function testPrivateLocalAndReservedAddressesLookLikeAProxy(): void
    {
        foreach (['10.0.0.5', '172.18.0.3', '192.168.1.10', '127.0.0.1', '::1', 'fd00::1', '169.254.0.1'] as $address) {
            $this->assertTrue(Status::isProxyAddress($address), $address);
        }
    }

    public function testPublicAddressesAndNoAddressDoNotWarn(): void
    {
        foreach (['203.0.113.7', '8.8.8.8', '2a00:1450:4001::1', '', 'not-an-ip'] as $address) {
            $this->assertFalse(Status::isProxyAddress($address), $address);
        }
    }

    /** Stopping emails is never limited by default: a refused "no" would keep emails going. */
    public function testDefaultRulesNeverLimitStoppingEmails(): void
    {
        $config = (string)file_get_contents(__DIR__ . '/../../../etc/config.xml');
        preg_match('#<rules>(.*)</rules>#s', $config, $match);
        $rules = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        foreach (['consent_withdraw', 'consent_optout', 'notification_block'] as $endpoint) {
            $this->assertArrayNotHasKey($endpoint, $rules, $endpoint);
        }
        $this->assertArrayHasKey('notification_subscribe', $rules, 'sign-ups stay limited');
    }
}
