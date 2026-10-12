<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses;

class InitializeWarehouseAvailability implements DataPatchInterface
{
    public function __construct(private ModuleDataSetupInterface $setup)
    {
    }

    public function apply()
    {
        $this->setup->getConnection()->update(
            $this->setup->getTable('perspective_novaposhta_catalog_warehouse'),
            ['is_active' => 0],
            ['warehouse_status IS NULL OR warehouse_status != ?' => WarehouseStatuses::WORKING]
        );
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
