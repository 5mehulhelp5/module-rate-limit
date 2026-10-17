<?php
declare(strict_types=1);

namespace CommerceKings\RateLimit\Block\Adminhtml\Form\Field;

use CommerceKings\RateLimit\Model\Config\Source\Period;
use CommerceKings\RateLimit\Model\EndpointRegistry;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/** The "Limits" table: endpoint, requests per guest IP, requests per signed-in customer, period. */
class Rules extends AbstractFieldArray
{
    private ?OptionsColumn $endpointColumn = null;
    private ?OptionsColumn $periodColumn = null;

    /** @param array<string, mixed> $data */
    public function __construct(
        Context $context,
        private readonly EndpointRegistry $endpoints,
        private readonly Period $periods,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** Saved rows of endpoints that are no longer registered (module removed) are not shown and drop on save. */
    public function getArrayRows()
    {
        return array_filter(
            parent::getArrayRows(),
            fn (DataObject $row) => $this->endpoints->has((string)$row->getData('endpoint'))
        );
    }

    /** @throws LocalizedException */
    protected function _prepareToRender()
    {
        $number = 'required-entry validate-digits validate-greater-than-zero admin__control-text';
        $this->addColumn('endpoint', ['label' => __('Endpoint'), 'renderer' => $this->getEndpointColumn()]);
        $this->addColumn('guest_limit', ['label' => __('Per guest IP'), 'class' => $number, 'style' => 'width:70px']);
        $this->addColumn('limit', ['label' => __('Per customer'), 'class' => $number, 'style' => 'width:70px']);
        $this->addColumn('period', ['label' => __('Per'), 'renderer' => $this->getPeriodColumn()]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add limit');
    }

    /** @throws LocalizedException */
    protected function _prepareArrayRow(DataObject $row)
    {
        $selected = [];
        $columns = ['endpoint' => $this->getEndpointColumn(), 'period' => $this->getPeriodColumn()];
        foreach ($columns as $field => $column) {
            $value = $row->getData($field);
            if ($value !== null) {
                $selected['option_' . $column->calcOptionHash($value)] = 'selected="selected"';
            }
        }
        $row->setData('option_extra_attrs', $selected);
    }

    /** @throws LocalizedException */
    private function getEndpointColumn(): OptionsColumn
    {
        if ($this->endpointColumn === null) {
            $options = [];
            foreach ($this->endpoints->getAll() as $id => $endpoint) {
                $options[] = ['value' => $id, 'label' => __($endpoint['label'])];
            }
            $this->endpointColumn = $this->createColumn($options, 260);
        }
        return $this->endpointColumn;
    }

    /** @throws LocalizedException */
    private function getPeriodColumn(): OptionsColumn
    {
        return $this->periodColumn ??= $this->createColumn($this->periods->toOptionArray(), 100);
    }

    /**
     * @param array<array{value: string, label: mixed}> $options
     * @param int $width in pixels, so the labels are readable in the narrow table
     * @throws LocalizedException
     */
    private function createColumn(array $options, int $width): OptionsColumn
    {
        /** @var OptionsColumn $column */
        $column = $this->getLayout()->createBlock(
            OptionsColumn::class,
            '',
            ['data' => ['is_render_to_js_template' => true], 'options' => $options]
        );
        $column->setExtraParams('style="width:' . $width . 'px"');
        return $column;
    }
}
