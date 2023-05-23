<?php


namespace Perspective\NovaposhtaCatalog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface;
use Perspective\NovaposhtaCatalog\Api\Data\CityInterface;
use Perspective\NovaposhtaCatalog\Api\Data\CitySearchResultsInterfaceFactory;
use Perspective\NovaposhtaCatalog\Model\City\CityFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\Collection;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses;

class CityRepository implements CityRepositoryInterface
{
    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory
     */
    private $cityFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses
     */
    private $warehouseStatuses;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City
     */
    private $cityResourceModel;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\CitySearchResultsInterfaceFactory
     */
    private CitySearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * CityRepository constructor.
     * @param \Perspective\NovaposhtaCatalog\Model\City\CityFactory $cityFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory $collectionFactory
     * @param \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses $warehouseStatuses
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City $cityResourceModel
     * @param \Perspective\NovaposhtaCatalog\Api\Data\CitySearchResultsInterfaceFactory $searchResultsFactory
     * @param \Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        CityFactory $cityFactory,
        CollectionFactory $collectionFactory,
        WarehouseStatuses $warehouseStatuses,
        City $cityResourceModel,
        CitySearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->cityFactory = $cityFactory;
        $this->collectionFactory = $collectionFactory;
        $this->warehouseStatuses = $warehouseStatuses;
        $this->cityResourceModel = $cityResourceModel;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function getCityByName(string $cityName)
    {
        /** @var ResourceModel\City\City\Collection $collection */
        $collection = $this->collectionFactory->create();
        return $collection->addFieldToFilter(
            [CityInterface::DESCRIPTION_UA, CityInterface::DESCRIPTION_RU],
            [
                ['like' => "$cityName%"],
                ['like' => "$cityName%"]
            ]
        )->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getCityCollectionByName(string $cityName)
    {
        /** @var ResourceModel\City\City\Collection $collection */
        $collection = $this->collectionFactory->create();
        return $collection->addFieldToFilter(
            [CityInterface::DESCRIPTION_UA, CityInterface::DESCRIPTION_RU],
            [
                ['like' => "$cityName%"],
                ['like' => "$cityName%"]
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getCityById(int $id)
    {
        $cityModelId = $this->cityFactory->create();
        $this->cityResourceModel->load($cityModelId, $id, 'id');
        return $cityModelId;
    }

    /**
     * @inheritDoc
     */
    public function getCityByCityId(int $id)
    {
        $cityModelId = $this->cityFactory->create();
        $this->cityResourceModel->load($cityModelId, $id, CityInterface::CITYID);
        return $cityModelId;
    }

    /**
     * @inheritDoc
     */
    public function getCityByCityRef(string $ref)
    {
        $cityModelId = $this->cityFactory->create();
        $this->cityResourceModel->load($cityModelId, $ref, CityInterface::REF);
        return $cityModelId;
    }

    /**
     * @inheritDoc
     * @deprecated since new method implemented
     */
    public function getAllCity(string $locale)
    {
        $collection = $this->collectionFactory->create();
        $cityArr = [];
        $cityOptionArr = [];
        if ($locale === 'uk_UA') {
            $cityArr = $collection->addFieldToSelect(CityInterface::DESCRIPTION_UA)
                ->getColumnValues(CityInterface::DESCRIPTION_UA);
        } else {
            $cityArr = $collection->addFieldToSelect(CityInterface::DESCRIPTION_RU)
                ->getColumnValues(CityInterface::DESCRIPTION_RU);
        }
        if (isset($cityArr)) {
            foreach ($cityArr as $idx => $value) {
                $cityOptionArr[] = ['label' => $value, 'value' => $idx];
            }
        }
        return $cityOptionArr ? $cityOptionArr :
            [
                [
                    'label' => 'Error occur when cities collection have been fetched', 'value' => -502
                ]
            ];
    }

    /**
     * @inheritDoc
     */
    public function getAllCityReturnCityId(string $locale)
    {
        $collection = $this->collectionFactory->create();
        $cityArr = [];
        $cityOptionArr = [];
        if ($locale === 'uk_UA') {
            $cityArr = $collection
                ->addFieldToSelect(CityInterface::DESCRIPTION_UA)
                ->addFieldToSelect(CityInterface::CITYID)
                ->addFieldToSelect(CityInterface::REF)
                ->getItems();
        } else {
            $cityArr = $collection
                ->addFieldToSelect(CityInterface::DESCRIPTION_RU)
                ->addFieldToSelect(CityInterface::CITYID)
                ->addFieldToSelect(CityInterface::REF)
                ->getItems();
        }
        if (isset($cityArr)) {
            if ($locale === 'uk_UA') {
                foreach ($cityArr as $idx => $value) {
                    $cityOptionArr[] = ['label' => $value->getDescriptionUa(), 'value' => (int)$value->getCityID(), 'cityRef' => $value->getRef()];
                }
            } else {
                foreach ($cityArr as $idx => $value) {
                    $cityOptionArr[] = ['label' => $value->getDescriptionRu(), 'value' => (int)$value->getCityID(), 'cityRef' => $value->getRef()];
                }
            }
        }
        return $cityOptionArr ? $cityOptionArr :
            [
                [
                    'label' => 'Error occur when cities collection have been fetched', 'value' => -502
                ]
            ];
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
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);
        return $collection;
    }

}
