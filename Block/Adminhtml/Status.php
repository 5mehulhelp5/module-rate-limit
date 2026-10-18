<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Block\Adminhtml;

use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Backpressure\SlidingWindow\RequestLoggerInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/** Whether Magento can count requests: the backpressure logger in app/etc/env.php is needed for the limits. */
class Status extends Field
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Context $context,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly EndpointRegistry $endpoints,
        private readonly RemoteAddress $remoteAddress,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $logger = (string)$this->deploymentConfig->get(RequestLoggerInterface::CONFIG_PATH_BACKPRESSURE_LOGGER);
        $message = $logger !== ''
            ? __(
                'Counting requests with the "%1" backpressure logger. %2 endpoints can be limited.',
                $logger,
                count($this->endpoints->getAll())
            )
            : __('No backpressure logger in app/etc/env.php: limits are off, blocked IP addresses still apply.');
        $html = '<div class="control-value">' . $this->escapeHtml((string)$message) . '</div>';
        if (self::isProxyAddress((string)$this->remoteAddress->getRemoteAddress())) {
            $html .= '<div class="message message-warning">' . $this->escapeHtml((string)__(
                'Magento sees this request from %1, a private or local address – usually your proxy or load balancer.'
                . ' Unless it forwards the visitor\'s address, every guest shares one counter and limits hit everyone'
                . ' together.',
                (string)$this->remoteAddress->getRemoteAddress()
            )) . '</div>';
        }
        return $html;
    }

    /** A private, loopback or reserved address: what Magento sees when a proxy does not forward the client's. */
    public static function isProxyAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP) !== false
            && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }
}
