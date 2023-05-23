<?php


namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\Package\PackageFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package as PackageResourceModel;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package\CollectionFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use stdClassFactory;

/**
 * Class Package
 * Sync Types of novaposhta Packages and sets to db (Admin and cron)
 */
class Package implements UpdateEntityInterface
{
    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Package\PackageFactory
     */
    public $packageFactory;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $httpClientFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    protected $configHelper;

    /**
     * @var string
     */
    protected $lang = 'ua';

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate
     */
    private $cronSyncDateLastUpdate;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $curl;

    /**
     * @var \stdClass
     */
    private $jsonParams;

    /**
     * @var \stdClassFactory
     */
    private $stdClassFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package\CollectionFactory
     */
    private $packageTypesResourceModelCollectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package
     */
    private $packageResourceModel;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var \Perspective\NovaposhtaCatalog\Service\HTTP\Post
     */
    private Post $postService;

    /**
     * Package constructor.
     *
     * @param ZendClientFactory $httpClientFactory
     * @param \stdClassFactory $stdClassFactory
     * @param Config $configHelper
     * @param CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package\CollectionFactory $packageTypesResourceModelCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package $packageResourceModel
     * @param \Perspective\NovaposhtaCatalog\Model\Package\PackageFactory $packageFactory
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     */
    public function __construct(
        ZendClientFactory $httpClientFactory,
        stdClassFactory $stdClassFactory,
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        Curl $curl,
        CollectionFactory $packageTypesResourceModelCollectionFactory,
        PackageResourceModel $packageResourceModel,
        PackageFactory $packageFactory,
        SerializerInterface $serializer,
        Post $postService
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->curl = $curl;
        $this->stdClassFactory = $stdClassFactory;
        $this->packageTypesResourceModelCollectionFactory = $packageTypesResourceModelCollectionFactory;
        $this->packageFactory = $packageFactory;
        $this->packageResourceModel = $packageResourceModel;
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
            /** @var \stdClass $packageTypeListJsonDecoded */
            $packageTypeListJsonDecoded = $this->getDataFromEndpoint();
            if (property_exists($packageTypeListJsonDecoded, 'success')
                && $packageTypeListJsonDecoded->success === true) {
                try {
                    $error = false;
                    $message = 'In Progress..';
                    $this->setDataToDB($packageTypeListJsonDecoded->data);
                } catch (AlreadyExistsException $e) {
                    $error = true;
                    $message = "Key already exist\n" . $e->getMessage();
                }
                if (!$error) {
                    $error = false;
                    $message = "Successfully synced";
                    $this->cronSyncDateLastUpdate
                        ->updateSyncDate($this->cronSyncDateLastUpdate::XML_PATH_LAST_SYNC_PACKAGE_TYPES);
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
     * @param $lang
     * @return mixed
     */
    public function getDataFromEndpoint(...$params)
    {
        $paramsForRequest = [
            'modelName' => 'Common', 'calledMethod' => 'getPackList', 'apiKey' => $this->configHelper->getApiKey(),
            'methodProperties' => [
                'Length' => null,
                'Width' => null,
                'Height' => null,
            ]
        ];
        $resultFormApi = $this->serializer->unserialize(
            $this->postService
                ->execute('Common', 'getPackList', $paramsForRequest)
                ->get()
                ->getBody()
        );
        return $resultFormApi;
    }

    /**
     * @param array $data
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setDataToDB(...$params)
    {
        $data = $params[0];
        $entireTableColl = $this->packageTypesResourceModelCollectionFactory->create();
        $entireIds = $entireTableColl->getAllIds();
        foreach ($data as $idx => $datum) {
            $filledModel = $this->prepareData($datum);
            /**@var $singleItem \Perspective\NovaposhtaCatalog\Model\Package\Package */
            $singleItem = $this->packageFactory->create();
            $this->packageResourceModel->load($singleItem, $filledModel->getRef(), $filledModel::REF);
            if ($singleItem->getRef()) {
                $filledModel->setId($singleItem->getId());
                $this->packageResourceModel
                    ->save($filledModel);
            } else {
                $this->packageResourceModel
                    ->save($filledModel);
            }

            unset($entireIds[array_search($singleItem->getId(), $entireIds)]);
        }
        if (count($entireIds) > 0) {
            foreach ($entireIds as $remIdx => $remItem) {
                $cleanUpModel = $this->packageFactory->create();
                $this->packageResourceModel->load($cleanUpModel, $remItem, 'id');
                $this->packageResourceModel->delete($cleanUpModel);
            }
        }
    }

    /**
     * @param $datum
     * @return \Perspective\NovaposhtaCatalog\Model\Package\Package
     */
    public function prepareData($datum)
    {
        /**@var $packageTypesModel \Perspective\NovaposhtaCatalog\Model\Package\Package */
        $packageTypesModel = $this->packageFactory->create();
        isset($datum->Description) ? $packageTypesModel->setDescriptionUa($datum->Description) : null;
        isset($datum->DescriptionRu) ? $packageTypesModel->setDescriptionRu($datum->DescriptionRu) : null;
        isset($datum->Ref) ? $packageTypesModel->setRef($datum->Ref) : null;
        isset($datum->Length) ? $packageTypesModel->setLength($datum->Length) : null;
        isset($datum->Width) ? $packageTypesModel->setWidth($datum->Width) : null;
        isset($datum->Height) ? $packageTypesModel->setHeight($datum->Height) : null;
        return $packageTypesModel;
    }
}
