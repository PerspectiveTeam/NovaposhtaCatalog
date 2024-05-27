<?php


namespace Perspective\NovaposhtaCatalog\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface CityRepositoryInterface
{
    /**
     * @param string $cityName
     * @return array<\Perspective\NovaposhtaCatalog\Model\City\City>
     */
    public function getCityByName(string $cityName);

    /**
     * @param string $cityName
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\Collection<\Perspective\NovaposhtaCatalog\Model\City\City>
     */
    public function getCityCollectionByName(string $cityName);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Perspective\NovaposhtaCatalog\Api\Data\CitySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
    /**
     * @param string $locale
     * @return mixed
     */
    public function getAllCity(string $locale);
    /**
     * @param string $locale
     * @return mixed
     */
    public function getAllCityReturnCityId(string $locale);

    /**
     * @param int $id
     * @return \Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function getCityById(int $id);
    /**
     * @param int $id
     * @return \Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function getCityByCityId(int $id);
    /**
     * @param string $ref
     * @return \Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function getCityByCityRef(string $ref);
}
