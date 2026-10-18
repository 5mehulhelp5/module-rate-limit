<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Test\Unit\Plugin;

use CommerceKings\RateLimit\Plugin\Ipv6NetworkIdentityPlugin;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Backpressure\ContextInterface;
use Magento\Framework\App\Request\Backpressure\ControllerContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Webapi\Backpressure\RestContext;
use PHPUnit\Framework\TestCase;

class Ipv6NetworkIdentityPluginTest extends TestCase
{
    private function controller(string $identity, int $type = ContextInterface::IDENTITY_TYPE_IP,
        string $typeId = 'ck-rate-limit/consent_grant'): ControllerContext
    {
        return new ControllerContext($this->createStub(RequestInterface::class), $identity, $type, $typeId,
            $this->createStub(ActionInterface::class));
    }

    private function after(?ContextInterface $context): ?ContextInterface
    {
        return (new Ipv6NetworkIdentityPlugin())->afterCreate(new \stdClass(), $context);
    }

    public function testAddressesInOneIpv6NetworkShareOneCounter(): void
    {
        $a = $this->after($this->controller('2001:db8:1:2:aaaa::1'));
        $b = $this->after($this->controller('2001:db8:1:2:ffff:1:2:3'));
        $this->assertSame('2001:db8:1:2::/64', $a->getIdentity());
        $this->assertSame($a->getIdentity(), $b->getIdentity(), 'rotating inside the /64 does not help');
        $this->assertNotSame($a->getIdentity(), $this->after($this->controller('2001:db8:1:3::1'))->getIdentity(),
            'another /64 is another client');
        $this->assertSame('ck-rate-limit/consent_grant', $a->getTypeId());
        $this->assertInstanceOf(ControllerContext::class, $a);
    }

    public function testRestContextsKeepServiceMethodAndEndpoint(): void
    {
        $rest = new RestContext($this->createStub(RequestInterface::class), '2001:db8::5', ContextInterface::IDENTITY_TYPE_IP,
            'ck-rate-limit/notification_subscribe', 'Svc', 'subscribeGuest', '/V1/x');
        $after = $this->after($rest);
        $this->assertInstanceOf(RestContext::class, $after);
        $this->assertSame(['2001:db8::/64', 'Svc', 'subscribeGuest', '/V1/x'],
            [$after->getIdentity(), $after->getService(), $after->getMethod(), $after->getEndpoint()]);
    }

    public function testIpv4CustomersAndMagentosOwnTypesAreUnchanged(): void
    {
        $ipv4 = $this->controller('203.0.113.7');
        $this->assertSame($ipv4, $this->after($ipv4));
        $mapped = $this->controller('::ffff:203.0.113.7');
        $this->assertSame($mapped, $this->after($mapped), 'an IPv4-mapped address is an IPv4 client');
        $customer = $this->controller('42', ContextInterface::IDENTITY_TYPE_CUSTOMER);
        $this->assertSame($customer, $this->after($customer));
        $magento = $this->controller('2001:db8::1', ContextInterface::IDENTITY_TYPE_IP, 'quote-order');
        $this->assertSame($magento, $this->after($magento), 'Magento\'s own limits keep its identity');
        $this->assertNull($this->after(null));
    }
}
