<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model\Backpressure;

use CommerceKings\RateLimit\Model\Config;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use CommerceKings\RateLimit\Model\IpBlocklist;
use Magento\Framework\App\Backpressure\SlidingWindow\RequestLoggerInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Whether a registered endpoint goes through Magento's backpressure: only while rate limiting is enabled, and only
 * with a limit for it or a blocked client address. Unlisted endpoints cost nothing: no counter, no Redis call.
 * Without the backpressure logger in env.php only blocked addresses go through: Magento could not count anyway and
 * would log an error for every request.
 */
class RequestTypeResolver
{
    public function __construct(
        private readonly Config $config,
        private readonly EndpointRegistry $registry,
        private readonly IpBlocklist $blocklist,
        private readonly RemoteAddress $remoteAddress,
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    public function resolve(?string $endpointId): ?string
    {
        if ($endpointId === null || !$this->config->isEnabled()) {
            return null;
        }
        $countable = $this->hasLogger() && $this->config->getRule($endpointId) !== null;
        if (!$countable && !$this->isClientBlocked()) {
            return null;
        }
        return $this->registry->getTypeId($endpointId);
    }

    public function hasLogger(): bool
    {
        return (string)$this->deploymentConfig->get(RequestLoggerInterface::CONFIG_PATH_BACKPRESSURE_LOGGER) !== '';
    }

    /** The client address is Magento's (REMOTE_ADDR, or X-Forwarded-For from trusted proxies), like the limit's. */
    public function isClientBlocked(): bool
    {
        $list = $this->config->getBlockedIps();
        $ip = $this->remoteAddress->getRemoteAddress();
        return $list !== '' && is_string($ip) && $this->blocklist->contains($list, $ip);
    }
}
