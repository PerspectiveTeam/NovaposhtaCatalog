<?php

namespace Perspective\NovaposhtaCatalog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface;
use Perspective\NovaposhtaCatalog\Api\Data\WarehouseSearchResultsInterfaceFactory;
use Perspective\NovaposhtaCatalog\Api\WarehouseRepositoryInterface;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\Collection;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\CollectionFactory
     */
    private $warehouseCollectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse
     */
    private $warehouseResourceModel;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse
     */
    private $warehouseModelFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Api\Data\WarehouseSearchResultsInterfaceFactory
     */
    private WarehouseSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * WarehouseRepository constructor.
     * @param CollectionFactory $warehouseCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse $warehouseResourceModel
     * @param \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory $warehouseModelFactory
     * @param \Perspective\NovaposhtaCatalog\Api\Data\CitySearchResultsInterfaceFactory $searchResultsFactory
     * @param \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        CollectionFactory $warehouseCollectionFactory,
        ResourceModel\Warehouse\Warehouse $warehouseResourceModel,
        WarehouseFactory $warehouseModelFactory,
        WarehouseSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->warehouseCollectionFactory = $warehouseCollectionFactory;
        $this->warehouseResourceModel = $warehouseResourceModel;
        $this->warehouseModelFactory = $warehouseModelFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function getArrayOfWarehouseModelsByCityRef(string $cityRef, string $locale)
    {
        $warehouseCollection = $this->warehouseCollectionFactory->create();
        return $warehouseCollection
            ->addFieldToFilter(Warehouse::CITY_REF, ['eq' => $cityRef])->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getCollectionOfWarehousesByCityRef(string $cityRef)
    {
        $warehouseCollection = $this->warehouseCollectionFactory->create();
        return $warehouseCollection
            ->addFieldToFilter(Warehouse::CITY_REF, ['like' => $cityRef]);
    }

    /**
     * @inheritDoc
     */
    public function getListOfWarehousesByCityRef(string $cityRef, string $locale)
    {
        /**
         * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\Collection $warehouseCollection
         */
        $warehouseCollection = $this->warehouseCollectionFactory->create();
        $warehouseOptionArr = [];
        $cityWarehouseCollection = $warehouseCollection
            ->addFieldToFilter(Warehouse::CITY_REF, ['eq' => $cityRef])->getItems();
        if (isset($cityWarehouseCollection)) {
            foreach ($cityWarehouseCollection as $idxCityWarehouse => $valCityWarehouse) {
                if ($valCityWarehouse->getData(Warehouse::WAREHOUSE_STATUS) === WarehouseStatuses::WORKING) {
                    if ($locale === 'uk_UA') {
                        $warehouseOptionArr[] = [
                            'label' => '№'
                                . $valCityWarehouse->getData(Warehouse::NUMBER_IN_CITY)
                                . ' - '
                                . $valCityWarehouse->getData(Warehouse::DESCRIPTION_UA),
                            'value' => $valCityWarehouse->getData(Warehouse::SITE_KEY)
                        ];
                    } else {
                        $warehouseOptionArr[] = [
                            'label' => '№'
                                . $valCityWarehouse->getData(Warehouse::NUMBER_IN_CITY)
                                . ' - '
                                . $valCityWarehouse->getData(Warehouse::DESCRIPTION_RU),
                            'value' => $valCityWarehouse->getData(Warehouse::SITE_KEY)
                        ];
                    }
                }
            }
        }
        return $warehouseOptionArr ? $warehouseOptionArr : [
            [
                'label' => 'Error occur when warehouse collection have been fetched', 'value' => -502
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function getWarehouseById(int $id)
    {
        $warehouseModel = $this->warehouseModelFactory->create();
        $this->warehouseResourceModel->load($warehouseModel, $id, WarehouseInterface::SITE_KEY);
        return $warehouseModel;
    }

    /**
     * @inheritDoc
     */
    public function getWarehouseByWarehouseRef(string $ref)
    {
        $warehouseModel = $this->warehouseModelFactory->create();
        $this->warehouseResourceModel->load($warehouseModel, $ref, WarehouseInterface::REF);
        return $warehouseModel;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria
    ) {
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
    protected function processCollectionWithCriteria(SearchCriteriaInterface $searchCriteria): Collection
    {
        $collection = $this->warehouseModelFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);
        return $collection;
    }
}
