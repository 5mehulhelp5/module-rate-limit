<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model\Backpressure;

use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Backpressure\RequestTypeExtractorInterface;
use Magento\Framework\App\RequestInterface;

/** Registered storefront controllers in Magento's controller backpressure (like Magento_InstantPurchase's). */
class ControllerRequestTypeExtractor implements RequestTypeExtractorInterface
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly RequestTypeResolver $resolver
    ) {
    }

    public function extract(RequestInterface $request, ActionInterface $action): ?string
    {
        return $this->resolver->resolve($this->registry->getIdForController($action));
    }
}
