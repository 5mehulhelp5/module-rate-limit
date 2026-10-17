<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Block\Adminhtml;

use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Backpressure\SlidingWindow\RequestLoggerInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Data\Form\Element\AbstractElement;

/** Whether Magento can count requests: the backpressure logger in app/etc/env.php is needed for the limits. */
class Status extends Field
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Context $context,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly EndpointRegistry $endpoints,
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
        return '<div class="control-value">' . $this->escapeHtml((string)$message) . '</div>';
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }
}
