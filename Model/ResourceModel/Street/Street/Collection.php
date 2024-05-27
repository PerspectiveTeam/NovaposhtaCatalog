<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterface;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street as StreetResource;
use Perspective\NovaposhtaCatalog\Model\Street\Street;

class Collection extends AbstractCollection implements SearchResultInterface
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(Street::class, StreetResource::class);
    }

    /**
     * Filter collection by City Ref
     *
     * @param string $cityRef
     * @return Collection<Street>
     */
    public function addCityRefToFilter(string $cityRef): Collection
    {
        return $this->addFieldToFilter(StreetInterface::CITY_REF, $cityRef);
    }

    /**
     * Filter collection by Street Name
     * @param string $streetName
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection
     */
    public function addStreetNameToFilter(string $streetName): Collection
    {
        return $this->addFieldToFilter(StreetInterface::DESCRIPTION, [
            'like' => '%' . $streetName . '%'
        ]);
    }
    /**
     * @inheritDoc
     */
    public function setItems(array $items = null)
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * @inheritDoc
     */
    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;
    }

    /**
     * @inheritDoc
     */
    public function getSearchCriteria()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function setSearchCriteria(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * @inheritDoc
     */
    public function setTotalCount($totalCount)
    {
        return $this;
    }
}
