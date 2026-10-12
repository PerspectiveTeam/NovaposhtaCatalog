<?php


namespace Perspective\NovaposhtaCatalog\Model\Update;

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
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    protected $configHelper;

    /**
     * @var string
     */
    protected $lang = 'ua';

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate
     */
    private $cronSyncDateLastUpdate;

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

    private DirectorySnapshot $snapshot;

    /**
     * Package constructor.
     *
     * @param Config $configHelper
     * @param CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package\CollectionFactory $packageTypesResourceModelCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package $packageResourceModel
     * @param \Perspective\NovaposhtaCatalog\Model\Package\PackageFactory $packageFactory
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     */
    public function __construct(
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        CollectionFactory $packageTypesResourceModelCollectionFactory,
        PackageResourceModel $packageResourceModel,
        PackageFactory $packageFactory,
        SerializerInterface $serializer,
        Post $postService,
        DirectorySnapshot $snapshot
    ) {
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->packageTypesResourceModelCollectionFactory = $packageTypesResourceModelCollectionFactory;
        $this->packageFactory = $packageFactory;
        $this->packageResourceModel = $packageResourceModel;
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
                $this->setDataToDB($this->snapshot->getResponseData($this->getDataFromEndpoint()));
                $this->cronSyncDateLastUpdate->updateSyncDate(CronSyncDateLastUpdate::XML_PATH_LAST_SYNC_PACKAGE_TYPES);
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
        $models = $this->snapshot->prepare($params[0] ?? null, fn ($row) => $this->prepareData($row));
        $this->snapshot->save(
            $models,
            $this->packageResourceModel,
            fn () => $this->packageTypesResourceModelCollectionFactory->create(),
            'perspective_novaposhta_packages_sync'
        );
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
        isset($datum->VolumetricWeight) ? $packageTypesModel->setVolumetricWeight($datum->VolumetricWeight) : null;
        isset($datum->PackagingForPlace) ? $packageTypesModel->setPackagingForPlace($datum->PackagingForPlace) : null;
        return $packageTypesModel;
    }
}
