<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Block\Adminhtml\Form\Field;


use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Html\Select;

class NovaPoshtaColumn extends Select
{
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        $select = $this->resourceConnection->getConnection()->select();
        $select->from(
            $this->resourceConnection->getTableName('perspective_novaposhta_catalog_areas'),
            ['ref', 'description_ua']
        );
        $select->order('description_ua');
        $rows = $this->resourceConnection->getConnection()->fetchAll($select);
        $result = [];
        foreach ($rows as $row) {
            $result[] = ['label' => $row['description_ua'], 'value' => $row['ref']];
        }
        return $result;
    }
}
