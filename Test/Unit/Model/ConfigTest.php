<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Model;

use CommerceKings\RateLimit\Model\Config;
use CommerceKings\RateLimit\Model\Config\Source\Period;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /** @param array<string, mixed> $values */
    private function config(array $values = []): Config
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(fn ($path) => $values[$path] ?? null);
        $scope->method('isSetFlag')->willReturnCallback(fn ($path) => !empty($values[$path]));
        return new Config($scope, new Json(), new Period());
    }

    public function testReadsValidRowsByEndpoint(): void
    {
        $rules = $this->config()->parseRules(json_encode([
            '_1' => ['endpoint' => 'a', 'guest_limit' => '10', 'limit' => '20', 'period' => '60'],
            '_2' => ['endpoint' => 'b', 'guest_limit' => 5, 'limit' => '5', 'period' => '86400'],
        ]));
        $this->assertSame(
            [
                'a' => ['guest_limit' => 10, 'limit' => 20, 'period' => 60],
                'b' => ['guest_limit' => 5, 'limit' => 5, 'period' => 86400],
            ],
            $rules
        );
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidRows(): array
    {
        return [
            'zero limit' => [['endpoint' => 'a', 'guest_limit' => '0', 'limit' => '1', 'period' => '60']],
            'negative' => [['endpoint' => 'a', 'guest_limit' => '-1', 'limit' => '1', 'period' => '60']],
            'decimal' => [['endpoint' => 'a', 'guest_limit' => '1.5', 'limit' => '1', 'period' => '60']],
            'missing limit' => [['endpoint' => 'a', 'guest_limit' => '1', 'period' => '60']],
            'unknown period' => [['endpoint' => 'a', 'guest_limit' => '1', 'limit' => '1', 'period' => '0']],
            'no endpoint' => [['guest_limit' => '1', 'limit' => '1', 'period' => '60']],
        ];
    }

    /** @param array<string, mixed> $row */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRows')]
    public function testInvalidRowsAreSkippedSoTheyCannotBlockAnything(array $row): void
    {
        $this->assertSame([], $this->config()->parseRules(json_encode(['_1' => $row])));
    }

    public function testFirstRowWinsAndBrokenValuesAreEmpty(): void
    {
        $config = $this->config();
        $twice = json_encode([
            ['endpoint' => 'a', 'guest_limit' => '1', 'limit' => '1', 'period' => '60'],
            ['endpoint' => 'a', 'guest_limit' => '9', 'limit' => '9', 'period' => '60'],
        ]);
        $this->assertSame(1, $config->parseRules($twice)['a']['guest_limit']);
        $this->assertSame([], $config->parseRules('{not json'));
        $this->assertSame([], $config->parseRules(''));
        $this->assertSame([], $config->parseRules('"text"'));
    }

    public function testReadsTheStoreScopeSettings(): void
    {
        $config = $this->config([
            Config::XML_PATH_ENABLED => '1',
            Config::XML_PATH_RULES => '{"_1":{"endpoint":"a","guest_limit":"3","limit":"4","period":"3600"}}',
            Config::XML_PATH_BLOCKED_IPS => '203.0.113.7',
        ]);
        $this->assertTrue($config->isEnabled());
        $this->assertSame(['guest_limit' => 3, 'limit' => 4, 'period' => 3600], $config->getRule('a'));
        $this->assertNull($config->getRule('b'));
        $this->assertSame('203.0.113.7', $config->getBlockedIps());
        $this->assertFalse($this->config()->isEnabled());
    }
}
