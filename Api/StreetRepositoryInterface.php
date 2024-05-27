<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Api;

interface StreetRepositoryInterface
{

    /**
     * Save Street
     * @param \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface $street
     * @return \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface $street
    );

    /**
     * Retrieve Street
     * @param string $streetId
     * @return \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($streetId);

    /**
     * Retrieve Street matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Perspective\NovaposhtaCatalog\Api\Data\StreetSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Retrieve Streets for by city ref.
     *
     * @param string $cityRef
     * @return \Perspective\NovaposhtaCatalog\Api\Data\StreetSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByCityRef(string $cityRef);
    /**
     * Retrieve Streets for by city ref.
     *
     * @param string $cityRef
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection<\Perspective\NovaposhtaCatalog\Model\Street\Street>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCollectionByCityRef(string $cityRef);
    /**
     * Retrieve Streets for by city ref.
     *
     * @param string $ref
     * @return \Perspective\NovaposhtaCatalog\Api\Data\StreetSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByRef(string $ref);

    /**
     * Retrieve Street obj by city ref.
     *
     * @param string $ref
     * @return \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface||null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getObjectByRef(string $ref);

    /**
     * Retrieve Streets Formatted array for by city ref.
     * [
     *    'value' => \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface::REF,
     *    'lable' => \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface::DESCRIPTION
     * ]
     * @param string $cityRef
     * @param int $pageSize
     * @param int $currentPage
     * @param string|null $term
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFormattedByCityRef(string $cityRef, int $pageSize = 20, int $currentPage = 1, ?string $term = null);

    /**
     * Retrieve Streets Formatted array for by city ref.
     * [
     *    'value' => \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface::REF,
     *    'lable' => \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface::DESCRIPTION
     * ]
     * @param string $cityName
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFormattedByCityName(string $cityName);

    /**
     * Delete Street
     * @param \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface $street
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Perspective\NovaposhtaCatalog\Api\Data\StreetInterface $street
    );

    /**
     * Delete Street by ID
     * @param string $streetId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($streetId);
}
