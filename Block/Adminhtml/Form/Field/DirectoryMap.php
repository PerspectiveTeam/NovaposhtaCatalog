<?php

namespace Perspective\NovaposhtaCatalog\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Perspective\NovaposhtaCatalog\Block\Adminhtml\Form\Field\NovaPoshtaColumn;
use Perspective\NovaposhtaCatalog\Block\Adminhtml\Form\Field\MagentoColumn;

/**
 * Class Ranges
 */
class DirectoryMap extends AbstractFieldArray
{
    /**
     * @var NovaPoshtaColumn
     */
    private $novaPoshtaColumnRenderer;
    /**
     * @var MagentoColumn
     */
    private $magentoColumnRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('novaposhta', [
            'label' => __('Novaposhta'),
            'renderer' => $this->getNovaposhtaRenderer()
        ]);
        $this->addColumn('magento', [
            'label' => __('Magento'),
            'renderer' => $this->getMagentoRenderer()
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $novaposhta = $row->getNovaposhta();
        if ($novaposhta !== null) {
            $options['option_' . $this->getNovaposhtaRenderer()->calcOptionHash($novaposhta)] = 'selected="selected"';
        }
        $magento = $row->getMagento();
        if ($magento !== null) {
            $options['option_' . $this->getMagentoRenderer()->calcOptionHash($magento)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @return NovaPoshtaColumn
     * @throws LocalizedException
     */
    private function getNovaposhtaRenderer()
    {
        if (!$this->novaPoshtaColumnRenderer) {
            $this->novaPoshtaColumnRenderer = $this->getLayout()->createBlock(
                NovaPoshtaColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->novaPoshtaColumnRenderer;
    }
    /**
     * @return NovaPoshtaColumn
     * @throws LocalizedException
     */
    private function getMagentoRenderer()
    {
        if (!$this->magentoColumnRenderer) {
            $this->magentoColumnRenderer = $this->getLayout()->createBlock(
                MagentoColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->magentoColumnRenderer;
    }
}
