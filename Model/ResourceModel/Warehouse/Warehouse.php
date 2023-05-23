<?php

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse;

class Warehouse extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface::DATABASE_TABLE_NAME, 'id');
    }
}
