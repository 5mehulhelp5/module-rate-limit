<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model\Config\Backend;

use CommerceKings\RateLimit\Model\IpBlocklist;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/** "Blocked IP addresses": refuses entries that are not an IP address or CIDR range, stores one per line. */
class IpList extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param IpBlocklist $blocklist
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly IpBlocklist $blocklist,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /** @throws LocalizedException */
    public function beforeSave()
    {
        $entries = $this->blocklist->parse((string)$this->getValue());
        $invalid = array_filter($entries, fn (string $entry) => !$this->blocklist->isValidEntry($entry));
        if ($invalid) {
            throw new LocalizedException(
                __('Not an IP address or CIDR range: %1', implode(', ', array_slice($invalid, 0, 5)))
            );
        }
        $this->setValue(implode("\n", array_unique($entries)));
        return parent::beforeSave();
    }
}
