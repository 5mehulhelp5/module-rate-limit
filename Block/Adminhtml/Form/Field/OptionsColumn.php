<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/** A dropdown cell of the "Limits" table; its options come from the `options` constructor argument. */
class OptionsColumn extends Select
{
    /**
     * @param Context $context
     * @param array<array{value: string, label: mixed}> $options
     * @param array<string, mixed> $data
     */
    public function __construct(Context $context, private readonly array $options = [], array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->options as $option) {
                $this->addOption($option['value'], $this->escapeHtml((string)$option['label']));
            }
        }
        return parent::_toHtml();
    }
}
