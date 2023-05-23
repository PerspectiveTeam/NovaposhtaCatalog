<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface CitySearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get Street list.
     * @return \Perspective\NovaposhtaCatalog\Api\Data\CityInterface[]
     */
    public function getItems();

    /**
     * Set test list.
     * @param \Perspective\NovaposhtaCatalog\Api\Data\CityInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
