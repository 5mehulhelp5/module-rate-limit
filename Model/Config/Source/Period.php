<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/** The "Per" column of the rate limits, in seconds. */
class Period implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '60', 'label' => __('Minute')],
            ['value' => '3600', 'label' => __('Hour')],
            ['value' => '86400', 'label' => __('Day')],
        ];
    }

    /** @return string[] */
    public function getValues(): array
    {
        return array_column($this->toOptionArray(), 'value');
    }
}
