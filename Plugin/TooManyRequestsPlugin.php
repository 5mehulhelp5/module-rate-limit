<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Plugin;

use CommerceKings\RateLimit\Model\Config;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Backpressure\BackpressureExceededException;
use Magento\Framework\App\Request\BackpressureValidator;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Magento's controller backpressure throws a LocalizedException that FrontController does not handle, so a limited
 * request ends on the error page. For registered endpoints we answer HTTP 429 with a small JSON body instead, as
 * Magento's REST API does. Other controllers keep Magento's behaviour.
 */
class TooManyRequestsPlugin
{
    public function __construct(
        private readonly EndpointRegistry $registry,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config
    ) {
    }

    /** @throws LocalizedException|InvalidRequestException */
    public function aroundValidate(
        BackpressureValidator $subject,
        callable $proceed,
        RequestInterface $request,
        ActionInterface $action
    ): void {
        try {
            $proceed($request, $action);
        } catch (LocalizedException $exception) {
            $endpoint = $this->registry->getIdForController($action);
            if (!$exception->getPrevious() instanceof BackpressureExceededException || $endpoint === null) {
                throw $exception;
            }
            // when to try again: the endpoint's period in seconds (a minute without a rule, e.g. a blocked address)
            $retry = (int)($this->config->getRule($endpoint)['period'] ?? 60);
            $result = $this->jsonFactory->create()
                ->setHttpResponseCode(429)
                ->setHeader('Retry-After', (string)max(1, $retry), true)
                ->setData(['message' => (string)__('Too Many Requests')]);
            throw new InvalidRequestException($result);
        }
    }
}
