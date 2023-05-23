<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Street;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Street extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Api\Data\StreetInterface::DATABASE_TABLE_NAME, 'id');
        $this->_useIsObjectNew = true;
    }
}
