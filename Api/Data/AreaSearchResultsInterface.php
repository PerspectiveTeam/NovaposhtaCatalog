<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface AreaSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * @return \Perspective\NovaposhtaCatalog\Api\Data\AreaInterface[]
     */
    public function getItems();

    /**
     * @param \Perspective\NovaposhtaCatalog\Api\Data\AreaInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
