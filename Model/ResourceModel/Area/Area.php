<?php

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Area;

class Area extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Api\Data\AreaInterface::AREAS_TABLE, 'id');
    }
}
