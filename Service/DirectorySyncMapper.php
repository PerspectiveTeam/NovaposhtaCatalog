<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Service;

use Magento\Framework\App\ResourceConnection;
use Perspective\NovaposhtaCatalog\Helper\Config;

class DirectorySyncMapper
{
    private array $regionIdToAreaRef = [];

    public function __construct(
        private readonly Config $catalogConfig,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Returns flat map of {regionId: areaRef} for all configured UA regions.
     */
    public function getRegionIdToAreaRefMap(?int $storeId = null): array
    {
        $cacheKey = $storeId ?? 'current';
        if (array_key_exists($cacheKey, $this->regionIdToAreaRef)) {
            return $this->regionIdToAreaRef[$cacheKey];
        }

        $map = [];
        $regionToArea = $this->catalogConfig->getDirectoryMap($storeId);
        if (empty($regionToArea)) {
            return $this->regionIdToAreaRef[$cacheKey] = [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('directory_country_region');
        $select = $connection->select()
            ->from($table, ['region_id', 'code'])
            ->where('country_id = ?', 'UA')
            ->where('code IN (?)', array_keys($regionToArea));

        foreach ($connection->fetchAll($select) as $row) {
            $code = $row['code'];
            $regionId = (int)$row['region_id'];
            if (isset($regionToArea[$code])) {
                $map[$regionId] = $regionToArea[$code];
            }
        }

        return $this->regionIdToAreaRef[$cacheKey] = $map;
    }
}
