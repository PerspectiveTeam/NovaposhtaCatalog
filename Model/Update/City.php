<?php

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\City\CityFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Psr\Log\LoggerInterface;

/**
 * Class City
 * Sync Types of novaposhta city and sets to db (Admin and cron)
 */
class City implements UpdateEntityInterface
{

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory
     */
    protected $cityFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse
     */
    protected $cityResourceModel;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse\CollectionFactory
     */
    protected $cityResourceModelCollectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate
     */
    private $cronSyncDateLastUpdate;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var \Perspective\NovaposhtaCatalog\Service\HTTP\Post
     */
    private Post $postService;

    private DirectorySnapshot $snapshot;

    /**
     * City constructor.
     *
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $configHelper
     * @param \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Model\City\CityFactory $cityFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City $cityResourceModel
     * @param CollectionFactory $cityResourceModelCollectionFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     */
    public function __construct(
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializer,
        CityFactory $cityFactory,
        \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City $cityResourceModel,
        CollectionFactory $cityResourceModelCollectionFactory,
        LoggerInterface $logger,
        Post $postService,
        DirectorySnapshot $snapshot
    ) {
        $this->cityResourceModelCollectionFactory = $cityResourceModelCollectionFactory;
        $this->cityResourceModel = $cityResourceModel;
        $this->cityFactory = $cityFactory;
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->postService = $postService;
        $this->snapshot = $snapshot;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $message = "Error has been occur";
        $error = true;
        $data = [];
        if ($this->configHelper->isEnabled()) {
            try {
                $this->snapshot->withLock('perspective_novaposhta_cities_sync', function () {
                    $this->setDataToDB($this->snapshot->getResponseData($this->getDataFromEndpoint()));
                    $this->cronSyncDateLastUpdate->updateSyncDate(CronSyncDateLastUpdate::XML_PATH_LAST_SYNC_CITY);
                });
                $error = false;
                $message = 'Successfully synced';
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->logger->error('Nova Poshta cities sync failed: ' . $message);
            }
        }
        return [
            'message' => $message,
            'data' => $data,
            'error' => $error
        ];
    }

    /**
     * @inheritDoc
     * @param mixed ...$params
     * @throws \Throwable
     */
    public function getDataFromEndpoint(...$params)
    {
        $paramsForRequest = [
            'modelName' => 'Address', 'calledMethod' => 'getCities', 'apiKey' => $this->configHelper->getApiKey(),
        ];
        $this->postService->setTimeout(60);
        $resultFormApi = $this->serializer->unserialize(
            $this->postService
                ->getResponse('Address', 'getCities', $paramsForRequest)
                ->getBody()
        );
        return $resultFormApi;
    }

    /**
     * @inheritDoc
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function setDataToDB(...$params)
    {
        $models = $this->snapshot->prepare($params[0] ?? null, fn ($row) => $this->prepareData($row));
        $this->snapshot->save(
            $models,
            $this->cityResourceModel,
            fn () => $this->cityResourceModelCollectionFactory->create(),
            'perspective_novaposhta_cities_sync'
        );
    }

    /**
     * @param $datum
     * @return \Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function prepareData($datum)
    {
        /**@var $cityModel \Perspective\NovaposhtaCatalog\Model\City\City */
        $cityModel = $this->cityFactory->create();
        isset($datum->Description) ? $cityModel->setDescriptionUa($datum->Description) : null;
        isset($datum->DescriptionRu) ? $cityModel->setDescriptionRu($datum->DescriptionRu) : null;
        isset($datum->Delivery1) ? $cityModel->setDelivery1($datum->Delivery1) : null;
        isset($datum->Delivery2) ? $cityModel->setDelivery2($datum->Delivery2) : null;
        isset($datum->Delivery3) ? $cityModel->setDelivery3($datum->Delivery3) : null;
        isset($datum->Delivery4) ? $cityModel->setDelivery4($datum->Delivery4) : null;
        isset($datum->Delivery5) ? $cityModel->setDelivery5($datum->Delivery5) : null;
        isset($datum->Delivery6) ? $cityModel->setDelivery6($datum->Delivery6) : null;
        isset($datum->Delivery7) ? $cityModel->setDelivery7($datum->Delivery7) : null;
        isset($datum->Ref) ? $cityModel->setRef($datum->Ref) : null;
        isset($datum->Area) ? $cityModel->setArea($datum->Area) : null;
        isset($datum->SettlementType) ? $cityModel->setSettlementType($datum->SettlementType) : null;
        isset($datum->IsBranch) ? $cityModel->setIsBranch($datum->IsBranch) : null;
        isset($datum->PreventEntryNewStreetsUser)
            ? $cityModel->setPreventEntryNewStreetsUser($datum->PreventEntryNewStreetsUser)
            : null;
        isset($datum->Conglomerates) ? $cityModel->setConglomerates($datum->Conglomerates) : null;
        isset($datum->CityID) ? $cityModel->setCityID($datum->CityID) : null;
        isset($datum->SettlementTypeDescription)
            ? $cityModel->setSettlementTypeDescriptionUa($datum->SettlementTypeDescription)
            : null;
        isset($datum->SettlementTypeDescriptionRu)
            ? $cityModel->setSettlementTypeDescriptionRu($datum->SettlementTypeDescriptionRu)
            : null;
        return $cityModel;
    }
}
