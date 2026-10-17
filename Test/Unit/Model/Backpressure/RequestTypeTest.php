<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Model\Backpressure;

use CommerceKings\RateLimit\Model\Backpressure\ControllerRequestTypeExtractor;
use CommerceKings\RateLimit\Model\Backpressure\RequestTypeResolver;
use CommerceKings\RateLimit\Model\Backpressure\WebapiRequestTypeExtractor;
use CommerceKings\RateLimit\Model\Config;
use CommerceKings\RateLimit\Model\Config\Source\Period;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use CommerceKings\RateLimit\Model\IpBlocklist;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedAction;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedServiceInterface;
use CommerceKings\RateLimit\Test\Unit\Fixture\OtherAction;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class RequestTypeTest extends TestCase
{
    private const RULES = '{"_1":{"endpoint":"limited_action","guest_limit":"5","limit":"5","period":"60"}}';

    private function registry(): EndpointRegistry
    {
        return new EndpointRegistry([
            'limited_action' => ['label' => 'Limited action', 'controller' => LimitedAction::class],
            'limited_service' => [
                'label' => 'Limited service',
                'service' => LimitedServiceInterface::class,
                'methods' => ['create'],
            ],
        ]);
    }

    private function resolver(
        bool $enabled,
        string $rules = self::RULES,
        string $blocked = '',
        string $ip = '192.0.2.1',
        string $logger = 'redis'
    ): RequestTypeResolver {
        $values = [
            Config::XML_PATH_ENABLED => $enabled,
            Config::XML_PATH_RULES => $rules,
            Config::XML_PATH_BLOCKED_IPS => $blocked,
        ];
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(fn ($path) => $values[$path] ?? null);
        $scope->method('isSetFlag')->willReturnCallback(fn ($path) => !empty($values[$path]));
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn($ip);
        $deployment = $this->createStub(DeploymentConfig::class);
        $deployment->method('get')->willReturn($logger);
        return new RequestTypeResolver(
            new Config($scope, new Json(), new Period()),
            $this->registry(),
            new IpBlocklist(),
            $remote,
            $deployment
        );
    }

    public function testControllerWithALimitGetsItsRequestType(): void
    {
        $extractor = new ControllerRequestTypeExtractor($this->registry(), $this->resolver(true));
        $request = $this->createStub(RequestInterface::class);
        $this->assertSame('ck-rate-limit/limited_action', $extractor->extract($request, new LimitedAction()));
        $this->assertNull($extractor->extract($request, new OtherAction()));
    }

    public function testNothingIsLimitedWhileDisabled(): void
    {
        $extractor = new ControllerRequestTypeExtractor($this->registry(), $this->resolver(false));
        $this->assertNull($extractor->extract($this->createStub(RequestInterface::class), new LimitedAction()));
    }

    public function testEndpointWithoutARowCostsNothing(): void
    {
        $extractor = new WebapiRequestTypeExtractor($this->registry(), $this->resolver(true));
        $this->assertNull($extractor->extract(LimitedServiceInterface::class, 'create', '/V1/limited'));
    }

    public function testBlockedClientIsRoutedThroughBackpressureEvenWithoutARow(): void
    {
        $extractor = new WebapiRequestTypeExtractor(
            $this->registry(),
            $this->resolver(true, self::RULES, "198.51.100.0/24", '198.51.100.9')
        );
        $this->assertSame(
            'ck-rate-limit/limited_service',
            $extractor->extract(LimitedServiceInterface::class, 'create', '/V1/limited')
        );
        $this->assertNull($extractor->extract(LimitedServiceInterface::class, 'read', '/V1/limited'));
    }

    public function testWithoutTheLoggerOnlyBlockedClientsAreRouted(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $noLogger = new ControllerRequestTypeExtractor($this->registry(), $this->resolver(true, self::RULES, '', '192.0.2.1', ''));
        $this->assertNull($noLogger->extract($request, new LimitedAction()));
        $blocked = new ControllerRequestTypeExtractor(
            $this->registry(),
            $this->resolver(true, self::RULES, '198.51.100.0/24', '198.51.100.9', '')
        );
        $this->assertSame('ck-rate-limit/limited_action', $blocked->extract($request, new LimitedAction()));
    }

    public function testClientBlocking(): void
    {
        $this->assertTrue($this->resolver(true, '', '198.51.100.0/24', '198.51.100.9')->isClientBlocked());
        $this->assertFalse($this->resolver(true, '', '198.51.100.0/24', '192.0.2.1')->isClientBlocked());
        $this->assertFalse($this->resolver(true, '', '', '198.51.100.9')->isClientBlocked());
    }
}
