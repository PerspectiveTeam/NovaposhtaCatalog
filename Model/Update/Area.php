<?php

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
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
    private const LOCK_NAME = 'perspective_novaposhta_areas_sync';

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

    private LockManagerInterface $lockManager;

    public function __construct(
        Config $configHelper,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializer,
        AreaFactory $areaFactory,
        AreaResource $areaResourceModel,
        CollectionFactory $areaResourceModelCollectionFactory,
        Post $postService,
        LoggerInterface $logger,
        LockManagerInterface $lockManager
    ) {
        $this->configHelper = $configHelper;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->serializer = $serializer;
        $this->areaFactory = $areaFactory;
        $this->areaResourceModel = $areaResourceModel;
        $this->areaResourceModelCollectionFactory = $areaResourceModelCollectionFactory;
        $this->postService = $postService;
        $this->logger = $logger;
        $this->lockManager = $lockManager;
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
        $data = $params[0] ?? null;

        $models = [];
        foreach (is_iterable($data) ? $data : [] as $datum) {
            if (!$this->isValidArea($datum)) {
                throw new LocalizedException(__('Nova Poshta returned an invalid area, sync aborted.'));
            }

            if (isset($models[$datum->Ref])) {
                throw new LocalizedException(__('Nova Poshta returned duplicate area references, sync aborted.'));
            }

            $models[$datum->Ref] = $this->prepareData($datum);
        }
        if (empty($models)) {
            throw new LocalizedException(__('Nova Poshta returned no areas, sync aborted.'));
        }

        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            throw new LocalizedException(__('Nova Poshta areas sync is already in progress.'));
        }

        try {
            $this->saveAreas($models);
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    private function isValidArea(mixed $datum): bool
    {
        if (!is_object($datum)) {
            return false;
        }

        $hasValidRef = $this->isNonEmptyString($datum->Ref ?? null)
            && strlen($datum->Ref) <= 255;
        $hasValidDescription = $this->isNonEmptyString($datum->Description ?? null);
        $hasValidCenter = !isset($datum->AreasCenter)
            || (is_string($datum->AreasCenter) && strlen($datum->AreasCenter) <= 255);
        $hasValidRussianDescription = !isset($datum->DescriptionRu)
            || is_string($datum->DescriptionRu);

        return $hasValidRef
            && $hasValidDescription
            && $hasValidCenter
            && $hasValidRussianDescription;
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Update known areas and insert new ones; missing references never authorize deletion.
     *
     * @param \Perspective\NovaposhtaCatalog\Model\Area\Area[] $models
     * @return void
     */
    private function saveAreas(array $models): void
    {
        $collection = $this->areaResourceModelCollectionFactory->create();
        $existingByRef = [];
        foreach ($collection as $item) {
            $existingByRef[$item->getRef()] = $item->getId();
        }

        if (array_diff_key($existingByRef, $models)) {
            throw new LocalizedException(__('Nova Poshta returned an incomplete areas list, sync aborted.'));
        }

        $connection = $this->areaResourceModel->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($models as $model) {
                $ref = $model->getRef();

                if (isset($existingByRef[$ref])) {
                    $model->setId($existingByRef[$ref]);
                }

                $this->areaResourceModel->save($model);
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
