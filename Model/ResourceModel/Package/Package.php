<?php


namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Package;

use Perspective\NovaposhtaCatalog\Api\Data\PackageInterface;

class Package extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected function _construct()
    {
        $this->_init(PackageInterface::DATABASE_TABLE_NAME, PackageInterface::DB_ID);
    }
}