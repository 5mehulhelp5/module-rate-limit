<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Plugin;

use CommerceKings\RateLimit\Model\Config;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Framework\App\Backpressure\ContextInterface;
use Magento\Framework\App\Backpressure\SlidingWindow\LimitConfig;
use Magento\Framework\App\Backpressure\SlidingWindow\LimitConfigManagerInterface;
use Magento\Framework\Exception\RuntimeException;

/**
 * Limits of our request types from the admin table. Magento's CompositeLimitConfigManager only knows request types
 * fixed in di.xml; ours come from the open endpoint registry, so they are answered here and everything else passes on.
 */
class LimitConfigPlugin
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly Config $config
    ) {
    }

    /** @throws RuntimeException Magento's enforcer logs it and lets the request through (fail open) */
    public function aroundReadLimit(
        LimitConfigManagerInterface $subject,
        callable $proceed,
        ContextInterface $context
    ): LimitConfig {
        $endpointId = $this->registry->getIdForTypeId($context->getTypeId());
        if ($endpointId === null) {
            return $proceed($context);
        }
        $rule = $this->config->getRule($endpointId);
        if ($rule === null) {
            throw new RuntimeException(__('No rate limit configured for "%1".', $endpointId));
        }
        $limit = $context->getIdentityType() === ContextInterface::IDENTITY_TYPE_IP
            ? $rule['guest_limit']
            : $rule['limit'];
        return new LimitConfig($limit, $rule['period']);
    }
}
