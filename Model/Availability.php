<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses;

class Availability
{
    /** Explicitly for new selections; raw collections and reference lookups retain history. */
    public function apply(AbstractCollection $collection): AbstractCollection
    {
        $db = $collection->getConnection();
        $table = $collection->getMainTable();
        $collection->addFieldToFilter('main_table.is_active', 1);
        $areas = $collection->getTable('perspective_novaposhta_catalog_areas');
        $cities = $collection->getTable('perspective_novaposhta_catalog_cities');
        $types = $collection->getTable('perspective_novaposhta_catalog_warehouse_types');
        if (str_ends_with($table, '_cities')) {
            $area = $db->select()->from(['np_area' => $areas], ['ref'])
                ->where('np_area.ref = main_table.area')->where('np_area.is_active = 1');
            $collection->getSelect()->where('EXISTS (' . $area . ')');
        }
        if (str_ends_with($table, '_warehouse') || str_ends_with($table, '_street')) {
            $city = $db->select()->from(['np_city' => $cities], ['ref'])
                ->where('np_city.ref = main_table.city_ref')->where('np_city.is_active = 1');
            $area = $db->select()->from(['np_area' => $areas], ['ref'])
                ->where('np_area.ref = np_city.area')->where('np_area.is_active = 1');
            $city->where('EXISTS (' . $area . ')');
            $collection->getSelect()->where('EXISTS (' . $city . ')');
        }
        if (str_ends_with($table, '_warehouse')) {
            $type = $db->select()->from(['np_type' => $types], ['ref'])
                ->where('np_type.ref = main_table.type_of_warehouse')->where('np_type.is_active = 1');
            $collection->getSelect()->where('EXISTS (' . $type . ')');
            $collection->addFieldToFilter('main_table.warehouse_status', WarehouseStatuses::WORKING);
        }
        return $collection;
    }
}
