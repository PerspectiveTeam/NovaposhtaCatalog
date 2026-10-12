<?php

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area as AreaResource;
use Perspective\NovaposhtaCatalog\Model\Area\AreaFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\CollectionFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Psr\Log\LoggerInterface;

/**
 * Sync Nova Poshta areas and store to DB
 */
class Area implements UpdateEntityInterface
{
    /**
     * @var Config
     */
    protected $configHelper;

    /**
     * @var AreaFactory
     */
    protected $areaFactory;

    /**
     * @var AreaResource
     */
    protected $areaResourceModel;

    /**
     * @var CollectionFactory
     */
    protected $areaResourceModelCollectionFactory;

    /**
     * @var CronSyncDateLastUpdate
     */
    private $cronSyncDateLastUpdate;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var Post
     */
    private Post $postService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializer,
        AreaFactory $areaFactory,
        AreaResource $areaResourceModel,
        CollectionFactory $areaResourceModelCollectionFactory,
        Post $postService,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->serializer = $serializer;
        $this->areaFactory = $areaFactory;
        $this->areaResourceModel = $areaResourceModel;
        $this->areaResourceModelCollectionFactory = $areaResourceModelCollectionFactory;
        $this->postService = $postService;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $message = "Error has been occur";
        $error = true;
        if ($this->configHelper->isEnabled()) {
            try {
                $areasListFromApiEndpoint = $this->getDataFromEndpoint();
                if (is_object($areasListFromApiEndpoint)
                    && property_exists($areasListFromApiEndpoint, 'success')
                    && $areasListFromApiEndpoint->success === true
                ) {
                    $message = 'In Progress..';
                    $this->setDataToDB($areasListFromApiEndpoint->data ?? null);
                    $error = false;
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->logger->critical($e->getMessage(), ['exception' => $e]);
            }

            if (!$error) {
                $message = "Successfully synced";
                $this->cronSyncDateLastUpdate
                    ->updateSyncDate(CronSyncDateLastUpdate::XML_PATH_LAST_SYNC_AREAS);
            }
        }
        return [
            'message' => $message,
            'data' => [],
            'error' => $error
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDataFromEndpoint(...$params)
    {
        $paramsForRequest = [
            'modelName' => 'AddressGeneral',
            'calledMethod' => 'getAreas',
            'apiKey' => $this->configHelper->getApiKey(),
        ];
        $this->postService->setTimeout(60);
        $resultFormApi = $this->serializer->unserialize(
            $this->postService
                ->execute('AddressGeneral', 'getAreas', $paramsForRequest)
                ->get()
                ->getBody()
        );
        return $resultFormApi;
    }

    /**
     * @inheritDoc
     */
    public function setDataToDB(...$params)
    {
        $data = $params[0];

        $models = [];
        foreach (is_iterable($data) ? $data : [] as $datum) {
            if (is_object($datum) && !empty($datum->Ref)) {
                $models[] = $this->prepareData($datum);
            }
        }
        // An empty snapshot would delete every stored area below, so refuse to reconcile against it
        if (empty($models)) {
            throw new LocalizedException(__('Nova Poshta returned no areas, sync aborted.'));
        }

        $collection = $this->areaResourceModelCollectionFactory->create();
        $existingByRef = [];
        foreach ($collection as $item) {
            $existingByRef[$item->getRef()] = $item->getId();
        }

        $connection = $this->areaResourceModel->getConnection();
        $connection->beginTransaction();
        try {
            $touchedIds = [];
            foreach ($models as $model) {
                $ref = $model->getRef();

                if (isset($existingByRef[$ref])) {
                    $model->setId($existingByRef[$ref]);
                    $touchedIds[] = $existingByRef[$ref];
                }

                $this->areaResourceModel->save($model);

                if (!isset($existingByRef[$ref])) {
                    $touchedIds[] = (int)$model->getId();
                }
            }

            $idsToDelete = array_diff(array_values($existingByRef), $touchedIds);
            if (!empty($idsToDelete)) {
                $connection->delete(
                    $this->areaResourceModel->getMainTable(),
                    ['id IN (?)' => $idsToDelete]
                );
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param $datum
     * @return \Perspective\NovaposhtaCatalog\Model\Area\Area
     */
    public function prepareData($datum)
    {
        /** @var \Perspective\NovaposhtaCatalog\Model\Area\Area $areaModel */
        $areaModel = $this->areaFactory->create();
        isset($datum->Ref) ? $areaModel->setRef($datum->Ref) : null;
        isset($datum->AreasCenter) ? $areaModel->setAreasCenter($datum->AreasCenter) : null;
        isset($datum->Description) ? $areaModel->setDescriptionUa($datum->Description) : null;
        isset($datum->DescriptionRu) ? $areaModel->setDescriptionRu($datum->DescriptionRu) : null;
        return $areaModel;
    }
}
