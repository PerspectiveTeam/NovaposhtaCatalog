<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package;

/** Explicit collection for packing new shipments. The base collection retains history. */
class ActiveCollection extends Collection
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->addFieldToFilter('main_table.is_active', 1);
        return $this;
    }
}
