<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Plugin\Update;

use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Model\Update;
use Perspective\NovaposhtaCatalog\Service\SyncMetrics;

class SyncMetricsPlugin
{
    public function __construct(private SyncMetrics $metrics)
    {
    }

    public function aroundExecute(UpdateEntityInterface $subject, callable $proceed): mixed
    {
        $entity = match (true) {
            $subject instanceof Update\Area => 'areas',
            $subject instanceof Update\City => 'cities',
            $subject instanceof Update\Package => 'packages',
            $subject instanceof Update\WarehouseType => 'warehouse_types',
            $subject instanceof Update\Warehouse => 'warehouses',
            $subject instanceof Update\Street => 'streets',
            default => 'directory',
        };
        return $this->metrics->run($entity, $proceed);
    }
}
