<?php

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse;

class WarehouseTypes extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected function _construct()
    {
        $this->_init(
            \Perspective\NovaposhtaCatalog\Api\Data\WarehouseTypesInterface::DATABASE_TABLE_NAME,
            'id'
        );
    }
}
