<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Model\Config\Backend;

use CommerceKings\RateLimit\Model\Config\Backend\IpList;
use CommerceKings\RateLimit\Model\Config\Backend\Rules;
use CommerceKings\RateLimit\Model\Config\Source\Period;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use CommerceKings\RateLimit\Model\IpBlocklist;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedAction;
use CommerceKings\RateLimit\Test\Unit\Fixture\OtherAction;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class BackendTest extends TestCase
{
    /** Constructor arguments of a config backend model, as stubs. */
    private function valueArguments(): array
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        return [
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class),
        ];
    }

    private function rules(array $rows): Rules
    {
        $registry = new EndpointRegistry([
            'limited_action' => ['label' => 'Limited action', 'controller' => LimitedAction::class],
            'other_action' => ['label' => 'Other action', 'controller' => OtherAction::class],
        ]);
        $backend = new Rules(
            ...[...$this->valueArguments(), $registry, new Period(), null, null, [], new Json()]
        );
        $backend->setData('value', $rows);
        return $backend;
    }

    private function row(string $endpoint, string $guest = '10', string $limit = '20', string $period = '60'): array
    {
        return ['endpoint' => $endpoint, 'guest_limit' => $guest, 'limit' => $limit, 'period' => $period];
    }

    public function testValidRowsAreSavedAsJson(): void
    {
        $backend = $this->rules([
            '_1' => $this->row('limited_action'),
            '_2' => $this->row('other_action', '1', '1', '86400'),
            '__empty' => '',
        ]);
        $backend->beforeSave();
        $this->assertSame(
            ['_1' => $this->row('limited_action'), '_2' => $this->row('other_action', '1', '1', '86400')],
            json_decode((string)$backend->getValue(), true)
        );
    }

    /** @return array<string, array{array<string, array<string, string>>}> */
    public static function invalidTables(): array
    {
        return [
            'unknown endpoint' => [['_1' => ['endpoint' => 'gone', 'guest_limit' => '1', 'limit' => '1', 'period' => '60']]],
            'twice' => [[
                '_1' => ['endpoint' => 'limited_action', 'guest_limit' => '1', 'limit' => '1', 'period' => '60'],
                '_2' => ['endpoint' => 'limited_action', 'guest_limit' => '2', 'limit' => '2', 'period' => '60'],
            ]],
            'zero' => [['_1' => ['endpoint' => 'limited_action', 'guest_limit' => '0', 'limit' => '1', 'period' => '60']]],
            'text' => [['_1' => ['endpoint' => 'limited_action', 'guest_limit' => '1', 'limit' => 'ten', 'period' => '60']]],
            'period' => [['_1' => ['endpoint' => 'limited_action', 'guest_limit' => '1', 'limit' => '1', 'period' => '5']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTables')]
    public function testInvalidTablesAreRefused(array $rows): void
    {
        $this->expectException(LocalizedException::class);
        $this->rules($rows)->beforeSave();
    }

    private function ipList(string $value): IpList
    {
        $backend = new IpList(...[...$this->valueArguments(), new IpBlocklist()]);
        $backend->setValue($value);
        return $backend;
    }

    public function testBlocklistIsStoredOneEntryPerLine(): void
    {
        $backend = $this->ipList(" 203.0.113.7, 198.51.100.0/24\n\n203.0.113.7\n2001:db8::/32 ");
        $backend->beforeSave();
        $this->assertSame("203.0.113.7\n198.51.100.0/24\n2001:db8::/32", $backend->getValue());
    }

    /** @return array<string, array{string}> */
    public static function invalidLists(): array
    {
        return [
            'hostname' => ["203.0.113.7\nexample.com"],
            'prefix too long' => ['203.0.113.0/33'],
            'IPv6 prefix too long' => ['2001:db8::/129'],
            'prefix not a number' => ['203.0.113.0/x'],
            'wildcard' => ['203.0.113.*'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLists')]
    public function testBlocklistRefusesEntriesThatAreNotAddresses(string $value): void
    {
        $this->expectException(LocalizedException::class);
        $this->ipList($value)->beforeSave();
    }
}
