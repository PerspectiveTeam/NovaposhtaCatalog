<?php


namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface;
use Perspective\NovaposhtaCatalog\Api\WarehouseRepositoryInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory as CityCollectionFactory;
use Perspective\NovaposhtaCatalog\Service\SyncMetrics;

/**
 * Class Warehouse
 */
class Warehouse implements UpdateEntityInterface
{
    private ?CityCollectionFactory $cityCollectionFactory = null;

    private ?SyncMetrics $metrics = null;

    /**
     * @var string
     */
    const PAGE_SIZE = 100;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory
     */
    protected $warehouseFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse
     */
    protected $warehouseResourceModel;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate
     */
    private $cronSyncDateLastUpdate;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var \Perspective\NovaposhtaCatalog\Service\HTTP\Post
     */
    private Post $postService;

    /**
     * @var \Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface
     */
    private CityRepositoryInterface $cityRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializerToArray;

    /**
     * @var \Perspective\NovaposhtaCatalog\Api\WarehouseRepositoryInterface
     */
    private WarehouseRepositoryInterface $warehouseRepository;

    /**
     * Warehouse constructor.
     *
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $configHelper
     * @param \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Framework\Serialize\SerializerInterface $serializerToArray
     * @param \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory $warehouseFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse $warehouseResourceModel
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     * @param \Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface $cityRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param WarehouseRepositoryInterface $warehouseRepository
     * @param DirectorySnapshot $snapshot
     * @param PagedDirectory $pages
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\CollectionFactory $collectionFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param CityCollectionFactory|null $cityCollectionFactory
     * @param SyncMetrics|null $metrics
     */
    public function __construct(
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializer,
        SerializerInterface $serializerToArray,
        WarehouseFactory $warehouseFactory,
        \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse $warehouseResourceModel,
        Post $postService,
        CityRepositoryInterface $cityRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        WarehouseRepositoryInterface $warehouseRepository,
        private DirectorySnapshot $snapshot,
        private PagedDirectory $pages,
        private \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\CollectionFactory $collectionFactory,
        private \Psr\Log\LoggerInterface $logger,
        ?CityCollectionFactory $cityCollectionFactory = null,
        ?SyncMetrics $metrics = null
    ) {
        $this->warehouseResourceModel = $warehouseResourceModel;
        $this->warehouseFactory = $warehouseFactory;
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->serializer = $serializer;
        $this->postService = $postService;
        $this->cityRepository = $cityRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->serializerToArray = $serializerToArray;
        $this->warehouseRepository = $warehouseRepository;
        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->metrics = $metrics;
    }

    /**
     * @return array
     * @throws \Throwable
     */
    public function execute()
    {
        $message = "Error has been occur";
        $error = true;
        $data = [];
        if ($this->configHelper->isEnabled()) {
            try {
                $cities = $this->getCities();
                foreach ($cities as $city) {
                    $cityRef = $city->getRef();
                    $this->snapshot->withLock('np_warehouses_' . sha1($cityRef), function () use ($cityRef) {
                        $this->setDataToDb($this->getDataFromEndpoint($cityRef), $cityRef);
                    });
                }
                if ($cities) {
                    $this->cronSyncDateLastUpdate->updateSyncDate(CronSyncDateLastUpdate::XML_PATH_LAST_SYNC_WAREHOUSE);
                    $error = false;
                    $message = 'Successfully synced';
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->logger->error('Nova Poshta warehouses sync failed: ' . $message);
            }
        }
        return [
            'message' => $message,
            'data' => $data,
            'error' => $error
        ];
    }

    private function getCities(): array
    {
        $read = function () {
            if ($this->cityCollectionFactory === null) {
                $criteria = $this->searchCriteriaBuilder->addFilter('is_active', 1)->create();
                return $this->cityRepository->getList($criteria)->getItems();
            }
            return $this->cityCollectionFactory->create()
                ->removeAllFieldsFromSelect()
                ->addFieldToSelect(['id', 'ref'])
                ->addFieldToFilter('is_active', 1)
                ->getItems();
        };
        return $this->metrics !== null ? $this->metrics->measure('db_read', $read) : $read();
    }

    /**
     * @param ...$params
     * @return array|bool|float|int|mixed|string|null
     * @throws \Throwable
     */
    public function getDataFromEndpoint(...$params)
    {
        $cityRef = $params[0] ?? null;
        if (!is_string($cityRef) || trim($cityRef) === '') {
            throw new LocalizedException(__('Invalid warehouses sync scope.'));
        }
        $this->postService->setTimeout(120);
        $rows = $this->pages->read(function ($page) use ($cityRef) {
            return $this->serializerToArray->unserialize(
                $this->getServicePromise($this->prepareParamsByCityRefAndPage($cityRef, $page))->getBody()
            );
        }, self::PAGE_SIZE);
        $response = $this->serializer->unserialize($this->serializer->serialize(array_merge($rows, ['success' => true])));
        $this->snapshot->validate(
            $this->getSnapshotRows($response),
            fn ($row) => $this->validateWarehouse($row, $cityRef),
            true
        );
        return $response;
    }

    /**
     * @param mixed ...$params
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function setDataToDb(...$params)
    {
        $cityRef = $params[1] ?? null;
        if (!is_string($cityRef) || trim($cityRef) === '') {
            throw new LocalizedException(__('Invalid warehouses sync scope.'));
        }
        $models = $this->prepareSnapshot($params[0] ?? null, $cityRef);
        $this->snapshot->saveScoped(
            $models,
            $this->warehouseResourceModel,
            fn () => $this->collectionFactory->create(),
            $cityRef,
            'np_warehouses_'
        );
    }

    private function prepareSnapshot(mixed $response, string $cityRef): array
    {
        return $this->snapshot->prepare($this->getSnapshotRows($response), function ($row) use ($cityRef) {
            $this->validateWarehouse($row, $cityRef);
            return $this->prepareData($row);
        }, true);
    }

    private function getSnapshotRows(mixed $response): array
    {
        if (!is_object($response) || ($response->success ?? null) !== true) {
            throw new LocalizedException(__('Nova Poshta warehouses request failed, sync aborted.'));
        }
        $rows = (array)$response;
        unset($rows['success']);
        return $rows;
    }

    private function validateWarehouse(object $row, string $cityRef): void
    {
        if (($row->CityRef ?? null) !== $cityRef) {
            throw new LocalizedException(__('Nova Poshta returned a warehouse outside the requested city.'));
        }
        foreach (['TypeOfWarehouse', 'WarehouseStatus'] as $field) {
            $value = $row->$field ?? null;
            if (!is_string($value) || trim($value) === '' || strlen($value) > 255) {
                throw new LocalizedException(__('Nova Poshta returned an invalid warehouse field: %1.', $field));
            }
        }
        foreach (['SiteKey', 'Number'] as $field) {
            $value = $row->$field ?? null;
            if ((!is_string($value) && !is_int($value)) || trim((string)$value) === '') {
                throw new LocalizedException(__('Nova Poshta returned an invalid warehouse field: %1.', $field));
            }
        }
        $statuses = [
            \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses::WORKING,
            \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses::IN_PROCESS_OPENING,
            \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses::NOT_WORKING,
            \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses::NOT_WORKING_TEMPORARY,
        ];
        if (!in_array($row->WarehouseStatus, $statuses, true)) {
            throw new LocalizedException(__('Nova Poshta returned an unknown warehouse status; review before syncing.'));
        }
    }

    /**
     * @param $datum
     * @param null $warehouseModel
     * @return \Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse
     */
    public function prepareData($datum, $warehouseModel = null)
    {
        if (!$warehouseModel || !$warehouseModel->getId()) {
            /**@var $warehouseModel \Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse */
            $warehouseModel = $this->warehouseFactory->create();
        }
        isset($datum->SiteKey) ? $warehouseModel->setSiteKey($datum->SiteKey) : null;
        isset($datum->Description) ? $warehouseModel->setDescriptionUa($datum->Description) : null;
        isset($datum->DescriptionRu) ? $warehouseModel->setDescriptionRu($datum->DescriptionRu) : null;
        isset($datum->ShortAddress) ? $warehouseModel->setShortAddressUa($datum->ShortAddress) : null;
        isset($datum->ShortAddressRu) ? $warehouseModel->setShortAddressRu($datum->ShortAddressRu) : null;
        isset($datum->Phone) ? $warehouseModel->setPhone($datum->Phone) : null;
        isset($datum->TypeOfWarehouse) ? $warehouseModel->setTypeOfWarehouse($datum->TypeOfWarehouse) : null;
        isset($datum->Ref) ? $warehouseModel->setRef($datum->Ref) : null;
        isset($datum->Number) ? $warehouseModel->setNumberInCity($datum->Number) : null;
        isset($datum->CityRef) ? $warehouseModel->setCityRef($datum->CityRef) : null;
        isset($datum->CityDescription) ? $warehouseModel->setCityDescriptionUa($datum->CityDescription) : null;
        isset($datum->CityDescriptionRu) ? $warehouseModel->setCityDescriptionRu($datum->CityDescriptionRu) : null;
        isset($datum->SettlementRef) ? $warehouseModel->setSettlementRef($datum->SettlementRef) : null;
        isset($datum->SettlementDescription)
            ? $warehouseModel->setSettlementDescription($datum->SettlementDescription)
            : null;
        isset($datum->SettlementAreaDescription)
            ? $warehouseModel->setSettlementAreaDescription($datum->SettlementAreaDescription)
            : null;
        isset($datum->SettlementRegionsDescription)
            ? $warehouseModel->setSettlementRegionDescription($datum->SettlementRegionsDescription)
            : null;
        isset($datum->SettlementTypeDescription)
            ? $warehouseModel->setSettlementTypeDescription($datum->SettlementTypeDescription)
            : null;
        isset($datum->Longitude) ? $warehouseModel->setLongitude($datum->Longitude) : null;
        isset($datum->Latitude) ? $warehouseModel->setLatitude($datum->Latitude) : null;
        isset($datum->PostFinance) ? $warehouseModel->setPostFinance($datum->PostFinance) : null;
        isset($datum->BicycleParking) ? $warehouseModel->setBicycleParking($datum->BicycleParking) : null;
        isset($datum->PaymentAccess) ? $warehouseModel->setPaymentAccess($datum->PaymentAccess) : null;
        isset($datum->POSTerminal) ? $warehouseModel->setPOSTerminal($datum->POSTerminal) : null;
        isset($datum->InternationalShipping)
            ? $warehouseModel->setInternationalShipping($datum->InternationalShipping)
            : null;
        isset($datum->TotalMaxWeightAllowed)
            ? $warehouseModel->setTotalMaxWeightAllowed($datum->TotalMaxWeightAllowed)
            : null;
        isset($datum->PlaceMaxWeightAllowed)
            ? $warehouseModel->setPlaceMaxWeightAllowed($datum->PlaceMaxWeightAllowed)
            : null;
        isset($datum->Reception) ? $warehouseModel->setReception($datum->Reception) : null;
        isset($datum->Delivery) ? $warehouseModel->setDelivery($datum->Delivery) : null;
        isset($datum->Schedule) ? $warehouseModel->setSchedule($datum->Schedule) : null;
        isset($datum->DistrictCode) ? $warehouseModel->setDistrictCode($datum->DistrictCode) : null;
        isset($datum->WarehouseStatus) ? $warehouseModel->setWarehouseStatus($datum->WarehouseStatus) : null;
        isset($datum->CategoryOfWarehouse)
            ? $warehouseModel->setCategoryOfWarehouse($datum->CategoryOfWarehouse)
            : null;
        return $warehouseModel;
    }

    /**
     * @param string $cityRef
     * @param int $page
     * @return array
     */
    protected function prepareParamsByCityRefAndPage(string $cityRef, int $page = 1): array
    {
        $paramsForRequest = [
            'modelName' => 'Address',
            'calledMethod' => 'getWarehouses',
            'apiKey' => $this->configHelper->getApiKey(),
            'methodProperties' => [
                'CityRef' => $cityRef,
                'Page' => $page,
                'Limit' => self::PAGE_SIZE,
            ]
        ];
        return $paramsForRequest;
    }

    /**
     * @param array $paramsForRequest
     * @return \Magento\Framework\HTTP\AsyncClient\Response
     * @throws \Throwable
     */
    protected function getServicePromise(array $paramsForRequest): \Magento\Framework\HTTP\AsyncClient\Response
    {
        return $this->postService->getResponse('Address', 'getWarehouses', $paramsForRequest);
    }
}
