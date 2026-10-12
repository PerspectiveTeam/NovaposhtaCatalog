<?php

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypesFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;

/**
 * Class WarehouseType
 * Sync Types of novaposhta warehouse and sets to db (Admin and cron)
 */
class WarehouseType implements UpdateEntityInterface
{

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    protected $configHelper;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory
     */
    protected $warehouseTypesFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse
     */
    protected $warehouseTypesResourceModel;

    /**
     * @var string
     */
    protected $lang = 'ua';

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\CollectionFactory
     */
    protected $warehouseTypesResourceModelCollectionFactory;

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

    private DirectorySnapshot $snapshot;


    /**
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $configHelper
     * @param \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypesFactory $warehouseTypesFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes $warehouseTypesResourceModel
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\CollectionFactory $warehouseTypesResourceModelCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     */
    public function __construct(
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializer,
        WarehouseTypesFactory $warehouseTypesFactory,
        WarehouseTypes $warehouseTypesResourceModel,
        CollectionFactory $warehouseTypesResourceModelCollectionFactory,
        Post $postService,
        DirectorySnapshot $snapshot
    ) {
        $this->warehouseTypesResourceModelCollectionFactory = $warehouseTypesResourceModelCollectionFactory;
        $this->warehouseTypesResourceModel = $warehouseTypesResourceModel;
        $this->warehouseTypesFactory = $warehouseTypesFactory;
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
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
                $translations = [];
                foreach (['ua', 'ru'] as $lang) {
                    $this->lang = $lang;
                    $rows = $this->snapshot->getResponseData($this->getDataFromEndpoint($lang));
                    $translations[$lang] = $this->snapshot->prepare($rows, fn ($row) => $this->prepareData($row));
                }
                if (array_diff_key($translations['ua'], $translations['ru'])
                    || array_diff_key($translations['ru'], $translations['ua'])
                ) {
                    throw new LocalizedException(__('Nova Poshta returned inconsistent warehouse type translations.'));
                }
                foreach ($translations['ua'] as $ref => $model) {
                    $model->setDescriptionRu($translations['ru'][$ref]->getDescriptionRu());
                }
                $this->saveSnapshot($translations['ua']);
                $this->cronSyncDateLastUpdate->updateSyncDate(CronSyncDateLastUpdate::XML_PATH_LAST_SYNC_WAREHOUSE_TYPES);
                $error = false;
                $message = 'Successfully synced';
            } catch (\Throwable $e) {
                $message = $e->getMessage();
            }
        }
        return [
            'message' => $message,
            'data' => $data,
            'error' => $error
        ];
    }

    /**
     * @param mixed ...$params
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function setDataToDB(...$params)
    {
        $this->saveSnapshot($this->snapshot->prepare($params[0] ?? null, fn ($row) => $this->prepareData($row)));
    }

    private function saveSnapshot(array $models): void
    {
        $this->snapshot->save(
            $models,
            $this->warehouseTypesResourceModel,
            fn () => $this->warehouseTypesResourceModelCollectionFactory->create(),
            'perspective_novaposhta_warehouse_types_sync'
        );
    }

    /**
     * @param $datum
     * @return \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypes
     */
    protected function prepareData($datum)
    {
        /**@var $warehouseTypesModel \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypes */
        $warehouseTypesModel = $this->warehouseTypesFactory->create();
        if ($this->lang === 'ua') {
            isset($datum->Description) ? $warehouseTypesModel->setDescriptionUa($datum->Description) : null;
        } else {
            isset($datum->Description) ? $warehouseTypesModel->setDescriptionRu($datum->Description) : null;
        }
        isset($datum->Ref) ? $warehouseTypesModel->setRef($datum->Ref) : null;
        return $warehouseTypesModel;
    }

    /**
     * @param $lang
     * @return mixed
     */
    public function getDataFromEndpoint(...$params)
    {
        $lang = $params[0];
        $paramsForRequest = [
            'modelName' => 'Address', 'calledMethod' => 'getWarehouseTypes', 'apiKey' => $this->configHelper->getApiKey(),
            'methodProperties' => ['Language' => $lang]
        ];
        $resultFormApi = $this->serializer->unserialize(
            $this->postService
                ->execute('Address', 'getWarehouseTypes', $paramsForRequest)
                ->get()
                ->getBody()
        );
        return $resultFormApi;
    }
}
