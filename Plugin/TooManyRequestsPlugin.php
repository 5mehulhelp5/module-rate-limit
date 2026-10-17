<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Plugin;

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
        private readonly JsonFactory $jsonFactory
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
            if (!$exception->getPrevious() instanceof BackpressureExceededException
                || $this->registry->getIdForController($action) === null
            ) {
                throw $exception;
            }
            $result = $this->jsonFactory->create()
                ->setHttpResponseCode(429)
                ->setData(['message' => (string)__('Too Many Requests')]);
            throw new InvalidRequestException($result);
        }
    }
}
