<?php

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\City;

class City extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Api\Data\CityInterface::CITIES_TABLE, 'id');
    }
}
