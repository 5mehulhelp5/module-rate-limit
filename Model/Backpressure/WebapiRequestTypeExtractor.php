<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model\Backpressure;

use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Framework\Webapi\Backpressure\BackpressureRequestTypeExtractorInterface;

/** Registered REST service methods in Magento's REST backpressure; Magento answers HTTP 429 above the limit. */
class WebapiRequestTypeExtractor implements BackpressureRequestTypeExtractorInterface
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly RequestTypeResolver $resolver
    ) {
    }

    public function extract(string $service, string $method, string $endpoint): ?string
    {
        return $this->resolver->resolve($this->registry->getIdForService($service, $method));
    }
}
