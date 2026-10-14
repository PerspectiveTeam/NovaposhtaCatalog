<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\Data\CityInterface;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterface;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory as CityCollectionFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory as StreetCollectionFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Psr\Log\LoggerInterface;
use Perspective\NovaposhtaCatalog\Service\SyncMetrics;

/**
 * Import Streets from NovaPoshta API
 *
 */
class Street implements UpdateEntityInterface
{
    /**
     * Field names in NP API response
     */
    private const API_FIELD_REF = 'Ref';

    /**
     * @var string
     */
    private const API_FIELD_DESCRIPTION = 'Description';

    /**
     * @var string
     */
    private const API_FIELD_STREETS_TYPE_REF = 'StreetsTypeRef';

    /**
     * @var string
     */
    private const API_FIELD_STREETS_TYPE = 'StreetsType';

    /**
     * @var string
     */
    const PAGE_SIZE = 500;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory
     */
    private $cityCollectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory
     */
    private $streetCollectionFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory
     */
    private $streetFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street
     */
    private $streetResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate
     */
    private CronSyncDateLastUpdate $cronSyncDateLastUpdate;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializerToArray;

    /**
     * @var \Perspective\NovaposhtaCatalog\Service\HTTP\Post
     */
    private Post $postService;

    /**
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    private Config $configHelper;

    private DirectorySnapshot $snapshot;

    private ?SyncMetrics $metrics = null;

    /**
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $configHelper
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory $cityCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory $streetCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory $streetFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street $streetResource
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate $cronSyncDateLastUpdate
     * @param \Magento\Framework\Serialize\SerializerInterface $serializerToArray
     * @param \Perspective\NovaposhtaCatalog\Service\HTTP\Post $postService
     */
    public function __construct(
        Config $configHelper,
        CityCollectionFactory $cityCollectionFactory,
        StreetCollectionFactory $streetCollectionFactory,
        StreetInterfaceFactory $streetFactory,
        \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street $streetResource,
        SerializerInterface $serializer,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate,
        SerializerInterface $serializerToArray,
        Post $postService,
        DirectorySnapshot $snapshot,
        LoggerInterface $logger,
        private PagedDirectory $pages,
        ?SyncMetrics $metrics = null,
    ) {

        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->streetCollectionFactory = $streetCollectionFactory;
        $this->streetFactory = $streetFactory;
        $this->streetResource = $streetResource;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->serializer = $serializer;
        $this->serializerToArray = $serializerToArray;
        $this->postService = $postService;
        $this->configHelper = $configHelper;
        $this->snapshot = $snapshot;
        $this->logger = $logger;
        $this->metrics = $metrics;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute()
    {
        $this->log(__('Start import streets'));
        $message = "Error has been occur";
        $error = true;
        $data = [];
        if ($this->configHelper->isEnabled()) {
            try {
                $synced = false;
                foreach ($this->getCityCollection() as $city) {
                    if ($city->getRef() === null) {
                        continue;
                    }
                    $this->log('Importing for city ' . $city->getRef());
                    $this->snapshot->withLock('np_streets_' . sha1($city->getRef()), function () use ($city) {
                        $streetsFromNP = $this->getDataFromEndpoint($city->getRef());
                        $models = $this->prepareDataFromNP($streetsFromNP, $city->getRef());
                        $this->setDataToDB($models, null, $city->getRef());
                    });
                    $synced = true;
                }
                if ($synced) {
                    $this->cronSyncDateLastUpdate->updateSyncDate(CronSyncDateLastUpdate::XML_PATH_LAST_SYNC_STREETS);
                    $error = false;
                    $message = 'Successfully synced';
                }
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->logger->error('Nova Poshta streets sync failed: ' . $message);
            }
        }
        return [
            'message' => $message,
            'data' => $data,
            'error' => $error
        ];
    }

    /**
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\Collection<\Perspective\NovaposhtaCatalog\Model\City\City>
     */
    private function getCityCollection(): \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\Collection
    {
        $collection = $this->cityCollectionFactory
            ->create()
            ->removeAllFieldsFromSelect()
            ->addFieldToSelect(CityInterface::REF)
            ->addFieldToSelect(CityInterface::CITYID)
            ->addFieldToFilter('is_active', 1)
            ->addOrder(CityInterface::CITYID, 'ASC');
        return $this->metrics !== null
            ? $this->metrics->measure('db_read', fn () => $collection->load())
            : $collection;
    }

    /**
     * @param mixed ...$params
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function setDataToDB(...$params)
    {
        $data = $params[0] ?? null;
        $cityRef = $params[2] ?? null;
        if (!is_iterable($data) || !is_string($cityRef) || trim($cityRef) === '') {
            throw new LocalizedException(__('Invalid streets sync scope.'));
        }
        $models = [];
        foreach ($data as $street) {
            if (!$street instanceof StreetInterface || $street->getCityRef() !== $cityRef) {
                throw new LocalizedException(__('Nova Poshta returned a street outside the requested city.'));
            }
            $this->validateStreetFields((object)[
                'Ref' => $street->getRef(), 'Description' => $street->getDescription(),
                'StreetsTypeRef' => $street->getStreetTypeRef(), 'StreetsType' => $street->getStreetType(),
            ]);
            if (isset($models[$street->getRef()])) {
                throw new LocalizedException(__('Nova Poshta returned duplicate street references.'));
            }
            $models[$street->getRef()] = $street;
        }
        $this->snapshot->saveScoped(
            $models,
            $this->streetResource,
            fn () => $this->streetCollectionFactory->create(),
            $cityRef,
            'np_streets_'
        );
    }

    /**
     * @param ...$params
     * @return \stdClass
     * @throws \Throwable
     */
    public function getDataFromEndpoint(...$params)
    {
        $cityRef = $params[0];
        $this->postService->setTimeout(60);
        $streets = $this->pages->read(function ($page) use ($cityRef) {
            return $this->serializerToArray->unserialize(
                $this->getServicePromise($this->prepareRequestParams($cityRef, $page))->getBody()
            );
        }, self::PAGE_SIZE);
        $response = $this->serializer->unserialize($this->serializer->serialize(array_merge($streets, ['success' => true])));
        $this->snapshot->validate($this->getSnapshotRows($response), $this->validateStreetFields(...), true);
        return $response;
    }

    /**
     * @param string $cityRef
     * @return array<mixed>
     */
    private function prepareRequestParams(string $cityRef, int $page = 1): array
    {
        $params = [
            'modelName' => 'Address',
            'calledMethod' => 'getStreet',
            "methodProperties" => [
                "CityRef" => $cityRef,
                "Page" => $page,
                "Limit" => self::PAGE_SIZE
            ]
        ];
        return $params;
    }

    /**
     * @param string|\Magento\Framework\Phrase $message
     * @param array $context
     */
    private function log($message, $context = []): void
    {
        $this->logger->debug((string)$message, $context);
    }

    /**
     * @param object $dataFromEndpoint
     * @param $cityRef
     * @return array
     */
    private function prepareDataFromNP($dataFromEndpoint, $cityRef)
    {
        return $this->snapshot->prepare($this->getSnapshotRows($dataFromEndpoint), function ($datum) use ($cityRef) {
            $this->validateStreetFields($datum);
            return $this->streetFactory->create()
                ->setRef($datum->{self::API_FIELD_REF})
                ->setDescription($datum->{self::API_FIELD_DESCRIPTION})
                ->setStreetTypeRef($datum->{self::API_FIELD_STREETS_TYPE_REF})
                ->setStreetType($datum->{self::API_FIELD_STREETS_TYPE})
                ->setCityRef($cityRef);
        }, true);
    }

    private function getSnapshotRows(mixed $response): array
    {
        if (!is_object($response) || ($response->success ?? null) !== true) {
            throw new LocalizedException(__('Nova Poshta streets request failed, sync aborted.'));
        }
        $rows = (array)$response;
        unset($rows['success']);
        return $rows;
    }

    private function validateStreetFields(object $row): void
    {
        foreach (['Ref', 'Description', 'StreetsTypeRef', 'StreetsType'] as $field) {
            $value = $row->$field ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > 255) {
                throw new LocalizedException(__('Nova Poshta returned an invalid street record, sync aborted.'));
            }
        }
    }

    /**
     * @param array $paramsForRequest
     * @return \Magento\Framework\HTTP\AsyncClient\Response
     * @throws \Throwable
     */
    protected function getServicePromise(array $paramsForRequest): \Magento\Framework\HTTP\AsyncClient\Response
    {
        return $this->postService->getResponse('Address', 'getStreet', $paramsForRequest);
    }
}
