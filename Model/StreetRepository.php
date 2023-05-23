<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterface;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory as StreetFactory;
use Perspective\NovaposhtaCatalog\Api\Data\StreetSearchResultsInterfaceFactory;
use Perspective\NovaposhtaCatalog\Api\StreetRepositoryInterface;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street as ResourceStreet;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory as StreetCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface as CityResource;

class StreetRepository implements StreetRepositoryInterface
{
    /**
     * @var ResourceStreet
     */
    private $resource;

    /**
     * @var StreetFactory
     */
    private $streetFactory;

    /**
     * @var StreetCollectionFactory
     */
    private $streetCollectionFactory;

    /**
     * @var StreetSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;

    /**
     * @var CityResource
     */
    private $resourceModelCity;

    /**
     * @param ResourceStreet $resource
     * @param StreetFactory $streetFactory
     * @param StreetCollectionFactory $streetCollectionFactory
     * @param StreetSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param CityResource $resourceModelCity
     */
    public function __construct(
        ResourceStreet $resource,
        StreetFactory $streetFactory,
        StreetCollectionFactory $streetCollectionFactory,
        StreetSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        CityResource $resourceModelCity
    ) {
        $this->resource = $resource;
        $this->streetFactory = $streetFactory;
        $this->streetCollectionFactory = $streetCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->resourceModelCity = $resourceModelCity;
    }

    /**
     * {@inheritdoc}
     */
    public function save(
        StreetInterface $street
    ) {
        try {
            $this->resource->save($street);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the street: %1',
                $exception->getMessage()
            ));
        }
        return $street;
    }

    /**
     * {@inheritdoc}
     */
    public function get($streetId)
    {
        $street = $this->streetFactory->create();
        $this->resource->load($street, $streetId);
        if (!$street->getId()) {
            throw new NoSuchEntityException(__('Street with id "%1" does not exist.', $streetId));
        }
        return $street;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ) {
        $collection = $this->processCollectionWithCriteria($searchCriteria);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function getByCityRef(string $cityRef)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(StreetInterface::CITY_REF, $cityRef);
        $searchCriteria = $searchCriteriaBuilder->create();
        return $this->getList($searchCriteria);
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionByCityRef(string $cityRef)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(StreetInterface::CITY_REF, $cityRef);
        $searchCriteria = $searchCriteriaBuilder->create();
        return $this->processCollectionWithCriteria($searchCriteria);
    }
    /**
     * {@inheritdoc}
     */
    public function getByRef(string $ref)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(StreetInterface::REF, $ref);
        $searchCriteria = $searchCriteriaBuilder->create();
        return $this->getList($searchCriteria);
    }
    /**
     * {@inheritdoc}
     */
    public function getObjectByRef(string $ref)
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(StreetInterface::REF, $ref);
        $searchCriteria = $searchCriteriaBuilder->create();
        return current($this->getList($searchCriteria)->getItems());
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedByCityRef(string $cityRef)
    {
        $streets = $this->getByCityRef($cityRef);
        $result = [];

        foreach ($streets->getItems() as $street) {
            $result[] = [
                'value' => $street->getRef(),
                'label' => sprintf('%s %s', $street->getStreetType(), $street->getDescription())
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedByCityName(string $cityName)
    {
        $cityRef = $this->resourceModelCity->getCityByName($cityName)->getFirstItem()->getRef();
        return $this->getFormattedByCityRef($cityRef);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(
        StreetInterface $street
    ) {
        try {
            $this->resource->delete($street);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the Street: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($streetId)
    {
        return $this->delete($this->get($streetId));
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection
     */
    protected function processCollectionWithCriteria(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria): ResourceStreet\Collection
    {
        $collection = $this->streetCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);
        return $collection;
    }
}
