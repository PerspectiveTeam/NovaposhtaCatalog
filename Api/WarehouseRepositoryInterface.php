<?php


namespace Perspective\NovaposhtaCatalog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface WarehouseRepositoryInterface
{
    /**
     * @param string $cityRef
     * @return array
     */
    public function getListOfWarehousesByCityRef(string $cityRef, string $locale);
    /**
     * @param string $cityRef
     * @return array<\Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse>
     */
    public function getArrayOfWarehouseModelsByCityRef(string $cityRef, string $locale);

    /**
     * @param string $cityRef
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\Collection<\Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse>
     */
    public function getCollectionOfWarehousesByCityRef(string $cityRef);

    /**
     * @param int $id
     * @return \Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse
     */
    public function getWarehouseById(int $id);

    /**
     * @param string $ref
     * @return \Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse
     */
    public function getWarehouseByWarehouseRef(string $ref);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return mixed
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
