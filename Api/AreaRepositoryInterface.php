<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface AreaRepositoryInterface
{
    /**
     * @param string $ref
     * @return \Perspective\NovaposhtaCatalog\Model\Area\Area
     */
    public function getAreaByAreaRef(string $ref);

    /**
     * @param string $areaName
     * @return array<\Perspective\NovaposhtaCatalog\Model\Area\Area>
     */
    public function getAreasByName(string $areaName);

    /**
     * @param string $areaName
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\Collection<\Perspective\NovaposhtaCatalog\Model\Area\Area>
     */
    public function getAreaCollectionByName(string $areaName);

    /**
     * @param int $id
     * @return \Perspective\NovaposhtaCatalog\Model\Area\Area
     */
    public function getAreaById(int $id);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Perspective\NovaposhtaCatalog\Api\Data\AreaSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
