<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Plugin;

use CommerceKings\RateLimit\Model\Backpressure\RequestTypeResolver;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Framework\App\Backpressure\BackpressureExceededException;
use Magento\Framework\App\Backpressure\ContextInterface;
use Magento\Framework\App\BackpressureEnforcerInterface;

/**
 * Blocked IP addresses are refused on our endpoints before any counter is read, so the blocklist also works without
 * the backpressure logger. Magento's and other modules' request types are untouched.
 */
class BlockedIpPlugin
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly RequestTypeResolver $resolver
    ) {
    }

    /** @throws BackpressureExceededException */
    public function beforeEnforce(BackpressureEnforcerInterface $subject, ContextInterface $context): void
    {
        if ($this->registry->getIdForTypeId($context->getTypeId()) !== null && $this->resolver->isClientBlocked()) {
            throw new BackpressureExceededException();
        }
    }
}
