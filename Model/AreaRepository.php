<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Perspective\NovaposhtaCatalog\Api\AreaRepositoryInterface;
use Perspective\NovaposhtaCatalog\Api\Data\AreaInterface;
use Perspective\NovaposhtaCatalog\Api\Data\AreaSearchResultsInterfaceFactory;
use Perspective\NovaposhtaCatalog\Model\Area\AreaFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area as AreaResourceModel;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\Collection;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\CollectionFactory;

class AreaRepository implements AreaRepositoryInterface
{
    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Area\AreaFactory
     */
    private AreaFactory $areaFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area
     */
    private AreaResourceModel $areaResourceModel;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Api\Data\AreaSearchResultsInterfaceFactory
     */
    private AreaSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    public function __construct(
        AreaFactory $areaFactory,
        AreaResourceModel $areaResourceModel,
        CollectionFactory $collectionFactory,
        AreaSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->areaFactory = $areaFactory;
        $this->areaResourceModel = $areaResourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function getAreaByAreaRef(string $ref)
    {
        $area = $this->areaFactory->create();
        $this->areaResourceModel->load($area, $ref, AreaInterface::REF);
        return $area;
    }

    /**
     * @inheritDoc
     */
    public function getAreasByName(string $areaName)
    {
        return $this->getAreaCollectionByName($areaName)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getAreaCollectionByName(string $areaName)
    {
        $collection = $this->collectionFactory->create();
        return $collection->addFieldToFilter(
            [AreaInterface::DESCRIPTION_UA, AreaInterface::DESCRIPTION_RU],
            [
                ['like' => "$areaName%"],
                ['like' => "$areaName%"]
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getAreaById(int $id)
    {
        $area = $this->areaFactory->create();
        $this->areaResourceModel->load($area, $id, AreaInterface::ID);
        return $area;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->processCollectionWithCriteria($searchCriteria);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return Collection
     */
    private function processCollectionWithCriteria(SearchCriteriaInterface $searchCriteria): Collection
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        return $collection;
    }
}
