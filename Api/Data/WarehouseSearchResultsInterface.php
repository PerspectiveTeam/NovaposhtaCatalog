<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface WarehouseSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get Street list.
     * @return \Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface[]
     */
    public function getItems();

    /**
     * Set test list.
     * @param \Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
