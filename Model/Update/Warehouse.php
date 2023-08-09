<?php


namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface;
use Perspective\NovaposhtaCatalog\Api\WarehouseRepositoryInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;

/**
 * Class Warehouse
 */
class Warehouse implements UpdateEntityInterface
{
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
     * @param \Perspective\NovaposhtaCatalog\Api\WarehouseRepositoryInterface $warehouseRepository
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
        WarehouseRepositoryInterface $warehouseRepository
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
            $listOfAllCities = $this->cityRepository->getList($this->searchCriteriaBuilder->create())->getItems();
            foreach ($listOfAllCities as $city) {
                //$city->setRef('e221d64c-391c-11dd-90d9-001a92567626'); //todo debug
                /** @var \stdClass $warehouseListJsonDecoded */
                $warehouseListJsonDecoded = $this->getDataFromEndpoint($city->getRef());
                if (property_exists($warehouseListJsonDecoded, 'success')
                    && $warehouseListJsonDecoded->success === true) {
                    try {
                        $error = false;
                        $message = 'In Progress..';
                        $this->setDataToDb($warehouseListJsonDecoded, $city->getRef());
                    } catch (AlreadyExistsException $e) {
                        $error = true;
                        $message = "Key already exist\n" . $e->getMessage();
                    }
                    if (!$error) {
                        $error = false;
                        $message = "Successfully synced";
                        $this->cronSyncDateLastUpdate
                            ->updateSyncDate($this->cronSyncDateLastUpdate::XML_PATH_LAST_SYNC_WAREHOUSE);
                    }
                }
            }
        }
        return [
            'message' => $message,
            'data' => $data,
            'error' => $error
        ];
    }

    /**
     * @param ...$params
     * @return array|bool|float|int|mixed|string|null
     * @throws \Throwable
     */
    public function getDataFromEndpoint(...$params)
    {
        $this->postService->setTimeout(120);
        $cityRef = $params[0];
        $cityWarehousesArray = [];
        $cityWarehousesArray['success'] = false;
        $paramsForRequest = $this->prepareParamsByCityRefAndPage($cityRef);
        $resultFormApi = $this->serializerToArray->unserialize(
            $this->postService
                ->execute('Address', 'getWarehouses', $paramsForRequest)
                ->get()
                ->getBody()
        );
        if (isset($resultFormApi['success']) && $resultFormApi['success'] === true) {
            $cityWarehousesArray = $resultFormApi['data'];
            if ($resultFormApi['info']['totalCount'] > self::PAGE_SIZE) {
                $pages = ceil($resultFormApi['info']['totalCount'] / self::PAGE_SIZE);
                for ($i = 2; $i <= $pages; $i++) {
                    $paramsForRequest = $this->prepareParamsByCityRefAndPage($cityRef, $i);
                    $resultFormApi = $this->serializerToArray->unserialize(
                        $this->postService
                            ->execute('Address', 'getWarehouses', $paramsForRequest)
                            ->get()
                            ->getBody()
                    );
                    if (isset($resultFormApi['success']) && $resultFormApi['success'] === true) {
                        $cityWarehousesArray = array_merge($cityWarehousesArray, $resultFormApi['data']);
                    }
                }
            }
            $cityWarehousesArray['success'] = true;
        }
        return $this->serializer->unserialize($this->serializer->serialize($cityWarehousesArray));
    }

    /**
     * @param mixed ...$params
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function setDataToDb(...$params)
    {
        $data = $params[0];
        $cityRef = $params[1];

        $collection = $this->warehouseRepository->getCollectionOfWarehousesByCityRef($cityRef);
        $allIds = $collection->getAllIds();
        $presentedIds = [];
        foreach ($data as $item) {
            if (!is_object($item)) {
                continue;
            }
            $singleItem = $this->warehouseFactory->create();
            $this->warehouseResourceModel->load($singleItem, $item->Ref, WarehouseInterface::REF);
            $filledModel = $this->prepareData($item, $singleItem);
            $this->warehouseResourceModel->save($filledModel);
            $presentedIds[] = $filledModel->getId();
        }
        foreach ($allIds as $id) {
            if (!in_array($id, $presentedIds)) {
                $singleItem = $this->warehouseFactory->create();
                $this->warehouseResourceModel->load($singleItem, $id, WarehouseInterface::ID);
                $this->warehouseResourceModel->delete($singleItem);
            }
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
}
