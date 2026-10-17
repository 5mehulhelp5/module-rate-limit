<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Model\Config\Backend;

use CommerceKings\RateLimit\Model\Config\Source\Period;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

/** The "Limits" table: one row per registered endpoint, limits above 0 and a period from the dropdown. */
class Rules extends ArraySerialized
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param EndpointRegistry $endpoints
     * @param Period $periods
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param Json|null $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly EndpointRegistry $endpoints,
        private readonly Period $periods,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?Json $serializer = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data,
            $serializer
        );
    }

    /** @throws LocalizedException */
    public function beforeSave()
    {
        // ArraySerialized receives the table as an array; Value::getValue() is typed as string
        $rows = $this->getData('value');
        $seen = [];
        foreach (is_array($rows) ? $rows : [] as $key => $row) {
            if ($key === '__empty' || !is_array($row)) {
                continue;
            }
            $endpoint = (string)($row['endpoint'] ?? '');
            if (!$this->endpoints->has($endpoint)) {
                throw new LocalizedException(__('Unknown endpoint "%1".', $endpoint));
            }
            if (isset($seen[$endpoint])) {
                throw new LocalizedException(
                    __('"%1" is listed twice. Keep one row per endpoint.', $this->endpoints->getLabel($endpoint))
                );
            }
            $seen[$endpoint] = true;
            foreach (['guest_limit', 'limit'] as $field) {
                $value = trim((string)($row[$field] ?? ''));
                if (!ctype_digit($value) || (int)$value < 1) {
                    throw new LocalizedException(
                        __('The limits of "%1" must be whole numbers above 0.', $this->endpoints->getLabel($endpoint))
                    );
                }
            }
            if (!in_array((string)($row['period'] ?? ''), $this->periods->getValues(), true)) {
                throw new LocalizedException(
                    __('Choose a period for "%1".', $this->endpoints->getLabel($endpoint))
                );
            }
        }
        return parent::beforeSave();
    }
}
