<?php


namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientInterfaceFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\CloudShipping\Exceptions\InterruptRequestOnBrockenProductException;
use Perspective\CloudShipping\Service\HTTP\AsyncClient\Request;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\Collection;
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
     * @var \Magento\Framework\HTTP\ClientInterfaceFactory
     */
    protected $httpClientFactory;

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
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\Collection
     */
    protected $warehouseTypesResourceModelCollection;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\CollectionFactory
     */
    protected $warehouseTypesResourceModelCollectionFactory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate
     */
    private $cronSyncDateLastUpdate;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    private Post $postService;


    /**
     * @param \Magento\Framework\HTTP\ClientInterfaceFactory $httpClientFactory
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $configHelper
     * @param \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypesFactory $warehouseTypesFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes $warehouseTypesResourceModel
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\Collection $warehouseTypesResourceModelCollection
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\CollectionFactory $warehouseTypesResourceModelCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     */
    public function __construct(
        ClientInterfaceFactory $httpClientFactory,
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializer,
        WarehouseTypesFactory $warehouseTypesFactory,
        WarehouseTypes $warehouseTypesResourceModel,
        Collection $warehouseTypesResourceModelCollection,
        CollectionFactory $warehouseTypesResourceModelCollectionFactory,
        Post $postService
    ) {
        $this->warehouseTypesResourceModelCollectionFactory = $warehouseTypesResourceModelCollectionFactory;
        $this->warehouseTypesResourceModelCollection = $warehouseTypesResourceModelCollection;
        $this->warehouseTypesResourceModel = $warehouseTypesResourceModel;
        $this->warehouseTypesFactory = $warehouseTypesFactory;
        $this->httpClientFactory = $httpClientFactory;
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->serializer = $serializer;
        $this->postService = $postService;
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
            $langArr = ['ua', 'ru'];
            foreach ($langArr as $idx => $lang) {
                $this->lang = $lang;
                /** @var \stdClass $warehouseTypeListJsonDecoded */
                $warehouseTypeListJsonDecoded = $this->getDataFromEndpoint($this->lang);
                if (property_exists($warehouseTypeListJsonDecoded, 'success')
                    && $warehouseTypeListJsonDecoded->success === true) {
                    try {
                        $error = false;
                        $message = 'In Progress..';
                        $this->setDataToDB($warehouseTypeListJsonDecoded->data);
                    } catch (AlreadyExistsException $e) {
                        $error = true;
                        $message = "Key already exist\n" . $e->getMessage();
                    } catch (LocalizedException $e) {
                        $error = true;
                        $message = "General Error\n" . $e->getMessage() . "\n";
                        $message .= $e->getTraceAsString();
                    }
                    if (!$error) {
                        $error = false;
                        $message = "Successfully synced";
                        $this->cronSyncDateLastUpdate
                            ->updateSyncDate($this->cronSyncDateLastUpdate::XML_PATH_LAST_SYNC_WAREHOUSE_TYPES);
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
     * @param array $data
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setDataToDB(...$params)
    {
        $data = $params[0];
        $entireTableColl = $this->warehouseTypesResourceModelCollectionFactory->create();
        $entireIds = $entireTableColl->getAllIds();
        foreach ($data as $idx => $datum) {
            $filledModel = $this->prepareData($datum);
            /**@var $warehouseTypesModel \Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypes */
            $singleItem = $this->warehouseTypesFactory->create();
            $this->warehouseTypesResourceModel->load($singleItem, $filledModel->getRef(), $filledModel::REF);
            switch ($this->lang) {
                case "ru":
                    if ($singleItem->getRef()) {
                        $filledModel->setId($singleItem->getId());
                        $this->warehouseTypesResourceModel
                            ->save($filledModel);
                    } else {
                        $this->warehouseTypesResourceModel
                            ->save($filledModel);
                    }
                    break;
                case "ua":
                    if ($singleItem->getRef()) {
                        $filledModel->setId($singleItem->getId());
                        $this->warehouseTypesResourceModel
                            ->save($filledModel);
                    } else {
                        $this->warehouseTypesResourceModel
                            ->save($filledModel);
                    }
                    break;
            }
            unset($entireIds[array_search($singleItem->getId(), $entireIds)]);
        }
        if (count($entireIds) > 0) {
            foreach ($entireIds as $remIdx => $remItem) {
                $cleanUpModel = $this->warehouseTypesFactory->create();
                $this->warehouseTypesResourceModel->load($cleanUpModel, $remItem, 'id');
                $this->warehouseTypesResourceModel->delete($cleanUpModel);
            }
        }
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
