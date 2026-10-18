<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Plugin;

use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Framework\App\Backpressure\ContextInterface;
use Magento\Framework\App\Request\Backpressure\ControllerContext;
use Magento\Framework\Webapi\Backpressure\RestContext;

/**
 * Counts an IPv6 guest by its /64 network on the registered endpoints. One client usually controls a whole /64, so
 * counting single addresses would let it rotate its address on every request. IPv4, signed-in customers and Magento's
 * own request types (e.g. placing an order) keep Magento's identity.
 */
class Ipv6NetworkIdentityPlugin
{
    /** After ContextFactory::create() (pages) and BackpressureContextFactory::create() (REST). */
    public function afterCreate(object $subject, ?ContextInterface $context): ?ContextInterface
    {
        if ($context === null || !str_starts_with($context->getTypeId(), EndpointRegistry::TYPE_PREFIX)
            || $context->getIdentityType() !== ContextInterface::IDENTITY_TYPE_IP) {
            return $context;
        }
        $network = self::network($context->getIdentity());
        if ($network === null) {
            return $context;
        }
        if ($context instanceof ControllerContext) {
            return new ControllerContext($context->getRequest(), $network, $context->getIdentityType(),
                $context->getTypeId(), $context->getAction());
        }
        if ($context instanceof RestContext) {
            return new RestContext($context->getRequest(), $network, $context->getIdentityType(), $context->getTypeId(),
                $context->getService(), $context->getMethod(), $context->getEndpoint());
        }
        return $context;
    }

    /** "2001:db8:1:2::/64" for any address in that network; null for IPv4 (and IPv4-mapped IPv6) or no address. */
    public static function network(string $address): ?string
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }
        $packed = (string)inet_pton($address);
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            return null;   // ::ffff:a.b.c.d is an IPv4 client
        }
        return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
}
