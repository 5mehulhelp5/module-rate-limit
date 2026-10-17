<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Plugin;

use CommerceKings\RateLimit\Model\Backpressure\RequestTypeResolver;
use CommerceKings\RateLimit\Model\Config;
use CommerceKings\RateLimit\Model\Config\Source\Period;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use CommerceKings\RateLimit\Model\IpBlocklist;
use CommerceKings\RateLimit\Plugin\BlockedIpPlugin;
use CommerceKings\RateLimit\Plugin\LimitConfigPlugin;
use CommerceKings\RateLimit\Plugin\TooManyRequestsPlugin;
use CommerceKings\RateLimit\Test\Unit\Fixture\LimitedAction;
use CommerceKings\RateLimit\Test\Unit\Fixture\OtherAction;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Backpressure\BackpressureExceededException;
use Magento\Framework\App\Backpressure\ContextInterface;
use Magento\Framework\App\Backpressure\SlidingWindow\LimitConfig;
use Magento\Framework\App\Backpressure\SlidingWindow\LimitConfigManagerInterface;
use Magento\Framework\App\BackpressureEnforcerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Request\BackpressureValidator;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class PluginsTest extends TestCase
{
    private function registry(): EndpointRegistry
    {
        return new EndpointRegistry([
            'limited_action' => ['label' => 'Limited action', 'controller' => LimitedAction::class],
            'unlisted' => ['label' => 'No row', 'controller' => OtherAction::class],
        ]);
    }

    private function config(string $blocked = ''): Config
    {
        $values = [
            Config::XML_PATH_ENABLED => true,
            Config::XML_PATH_RULES => '{"_1":{"endpoint":"limited_action","guest_limit":"5","limit":"50","period":"3600"}}',
            Config::XML_PATH_BLOCKED_IPS => $blocked,
        ];
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(fn ($path) => $values[$path] ?? null);
        $scope->method('isSetFlag')->willReturnCallback(fn ($path) => !empty($values[$path]));
        return new Config($scope, new Json(), new Period());
    }

    private function context(string $typeId, int $identityType = ContextInterface::IDENTITY_TYPE_IP): ContextInterface
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getTypeId')->willReturn($typeId);
        $context->method('getIdentityType')->willReturn($identityType);
        return $context;
    }

    private function readLimit(ContextInterface $context): LimitConfig
    {
        $plugin = new LimitConfigPlugin($this->registry(), $this->config());
        return $plugin->aroundReadLimit(
            $this->createStub(LimitConfigManagerInterface::class),
            fn () => new LimitConfig(1, 1),
            $context
        );
    }

    public function testGuestsGetTheGuestLimitPerIp(): void
    {
        $limit = $this->readLimit($this->context('ck-rate-limit/limited_action'));
        $this->assertSame([5, 3600], [$limit->getLimit(), $limit->getPeriod()]);
    }

    public function testCustomersAndAdminsGetTheCustomerLimit(): void
    {
        foreach ([ContextInterface::IDENTITY_TYPE_CUSTOMER, ContextInterface::IDENTITY_TYPE_ADMIN] as $type) {
            $limit = $this->readLimit($this->context('ck-rate-limit/limited_action', $type));
            $this->assertSame([50, 3600], [$limit->getLimit(), $limit->getPeriod()]);
        }
    }

    public function testOtherRequestTypesKeepTheirOwnLimits(): void
    {
        $limit = $this->readLimit($this->context('quote-order'));
        $this->assertSame([1, 1], [$limit->getLimit(), $limit->getPeriod()]);
    }

    public function testAMissingRowFailsOpen(): void
    {
        // Magento's SlidingWindowEnforcer logs this RuntimeException and lets the request through
        $this->expectException(RuntimeException::class);
        $this->readLimit($this->context('ck-rate-limit/unlisted'));
    }

    private function blockedIpPlugin(string $ip): BlockedIpPlugin
    {
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn($ip);
        $registry = $this->registry();
        return new BlockedIpPlugin(
            $registry,
            new RequestTypeResolver(
                $this->config('198.51.100.0/24'),
                $registry,
                new IpBlocklist(),
                $remote,
                $this->createStub(DeploymentConfig::class)
            )
        );
    }

    public function testBlockedClientsAreRefusedOnOurEndpoints(): void
    {
        $this->expectException(BackpressureExceededException::class);
        $this->blockedIpPlugin('198.51.100.9')->beforeEnforce(
            $this->createStub(BackpressureEnforcerInterface::class),
            $this->context('ck-rate-limit/limited_action')
        );
    }

    public function testOtherClientsAndMagentosOwnLimitsPass(): void
    {
        $enforcer = $this->createStub(BackpressureEnforcerInterface::class);
        $this->blockedIpPlugin('192.0.2.1')->beforeEnforce($enforcer, $this->context('ck-rate-limit/limited_action'));
        // Magento's own limits (e.g. placing orders) are never blocked by this list
        $this->blockedIpPlugin('198.51.100.9')->beforeEnforce($enforcer, $this->context('quote-order'));
        $this->addToAssertionCount(1);
    }

    private function validate(object $action, \Throwable $thrown): void
    {
        $result = $this->createStub(JsonResult::class);
        $result->method('setHttpResponseCode')->willReturnSelf();
        $result->method('setData')->willReturnSelf();
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($result);
        (new TooManyRequestsPlugin($this->registry(), $factory))->aroundValidate(
            $this->createStub(BackpressureValidator::class),
            fn () => throw $thrown,
            $this->createStub(RequestInterface::class),
            $action
        );
    }

    public function testLimitedControllerAnswers429InsteadOfAnErrorPage(): void
    {
        $exceeded = new LocalizedException(__('Too Many Requests'), new BackpressureExceededException());
        try {
            $this->validate(new LimitedAction(), $exceeded);
            $this->fail('No InvalidRequestException');
        } catch (InvalidRequestException $exception) {
            $this->assertInstanceOf(JsonResult::class, $exception->getReplaceResult());
        }
    }

    public function testOtherControllersAndOtherErrorsAreUntouched(): void
    {
        $exceeded = new LocalizedException(__('Too Many Requests'), new BackpressureExceededException());
        $unregistered = new class implements ActionInterface {
            public function execute()
            {
                throw new \BadMethodCallException('Not dispatched in unit tests.');
            }
        };
        foreach ([[$unregistered, $exceeded], [new LimitedAction(), new LocalizedException(__('Other'))]] as $case) {
            try {
                $this->validate($case[0], $case[1]);
                $this->fail('Exception swallowed');
            } catch (LocalizedException $exception) {
                $this->assertSame($case[1], $exception);
            }
        }
    }

    public function testAllowedRequestsPassThrough(): void
    {
        $called = false;
        (new TooManyRequestsPlugin($this->registry(), $this->createStub(JsonFactory::class)))->aroundValidate(
            $this->createStub(BackpressureValidator::class),
            function () use (&$called) {
                $called = true;
            },
            $this->createStub(RequestInterface::class),
            new LimitedAction()
        );
        $this->assertTrue($called);
    }
}
