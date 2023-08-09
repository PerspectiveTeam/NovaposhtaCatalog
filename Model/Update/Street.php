<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

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
     * @var \Closure|mixed|\Psr\Log\LoggerInterface|null
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
            foreach ($this->getCityCollection() as $city) {
            if ($city->getRef() === null) {
                continue;
            }
            $this->log('Importing for city ' . $city->getRef());

                $streetsFromNP = $this->getDataFromEndpoint($city->getRef());
                $preparedStreetsDataFromNP = $this->prepareDataFromNP($streetsFromNP, $city->getRef());
                if (empty($streetsFromNP)) {
                    $this->log(__('Street Request error. City has no Streets OR Check API key.'));
                    continue;
                }

                $streetsFromDB = $this->getStreetsFromDb($city->getRef());

                $this->setDataToDB($preparedStreetsDataFromNP, $streetsFromDB, $city->getRef());
            $error = false;
            $message = "Successfully synced";
        }
            $this->log(__($message));
        }
        if ($error === false) {
            $this->cronSyncDateLastUpdate->updateSyncDate($this->cronSyncDateLastUpdate::XML_PATH_LAST_SYNC_STREETS);
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
        return $this->cityCollectionFactory
            ->create()
            ->removeAllFieldsFromSelect()
            ->addFieldToSelect(CityInterface::REF)
            ->addFieldToSelect(CityInterface::CITYID)
            ->addOrder(CityInterface::CITYID, 'ASC');
    }

    /**
     * @param mixed ...$params
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function setDataToDB(...$params)
    {
        $streetsFromNP = $params[0];
        $streetsFromDB = $params[1];
        $cityRef = $params[2];

        foreach ($streetsFromNP as $streetFromNP) {
            /** @var StreetInterface|null $street */
            $street = $streetsFromDB->getItemByColumnValue(StreetInterface::REF, $streetFromNP->getRef());
            if ($street === null) {
                $this->saveStreet($streetFromNP);
            } else {
                if ($this->isStreetChanged($street, $streetFromNP)) {
                    $streetId = $street->getStreetId();
                    $this->saveStreet($streetFromNP, $streetId);
                }
            }
        }
    }

    /**
     * @param ...$params
     * @return \stdClass
     * @throws \Throwable
     */
    public function getDataFromEndpoint(...$params)
    {
        $cityRef = $params[0];
        $paramsForRequest = $this->prepareRequestParams($cityRef);
        $resultFormApi = $this->serializerToArray->unserialize(
            $this->postService
                ->execute('Address', 'getStreet', $paramsForRequest)
                ->get()
                ->getBody()
        );
        if (isset($resultFormApi['success']) && $resultFormApi['success'] === true) {
            $cityStreetsArray = $resultFormApi['data'];
            if ($resultFormApi['info']['totalCount'] > self::PAGE_SIZE) {
                $pages = ceil($resultFormApi['info']['totalCount'] / self::PAGE_SIZE);
                for ($i = 2; $i <= $pages; $i++) {
                    $paramsForRequest = $this->prepareRequestParams($cityRef, $i);
                    $resultFormApi = $this->serializerToArray->unserialize(
                        $this->postService
                            ->execute('Address', 'getStreet', $paramsForRequest)
                            ->get()
                            ->getBody()
                    );
                    if (isset($resultFormApi['success']) && $resultFormApi['success'] === true) {
                        $cityStreetsArray = array_merge($cityStreetsArray, $resultFormApi['data']);
                    }
                }
            }
            $cityStreetsArray['success'] = true;
        }

        return $this->serializer->unserialize($this->serializer->serialize($cityStreetsArray));
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
     * @param string $cityRef
     * @return \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection<StreetInterface>
     */
    private function getStreetsFromDb(string $cityRef): \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection
    {
        /** @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection $collection */
        $collection = $this->streetCollectionFactory->create();
        return $collection->addCityRefToFilter($cityRef);
    }

    /**
     * @param StreetInterface $street
     * @param null $streetId
     */
    private function saveStreet(StreetInterface $street, $streetId = null): void
    {
        if ($streetId !== null) {
            $street->setStreetId((int)$streetId);
        }
        try {
            $this->streetResource->save($street); // @phpstan-ignore-line
        } catch (\Exception $exception) {
            $this->log($exception->getMessage());
        }
    }

    /**
     * @param StreetInterface $street
     * @param StreetInterface $streetFromNP
     * @return bool
     */
    private function isStreetChanged(StreetInterface $street, StreetInterface $streetFromNP): bool
    {
        return ($street->getRef() !== $streetFromNP->getRef()) ||
            ($street->getDescription() !== $streetFromNP->getDescription()) ||
            ($street->getStreetTypeRef() !== $streetFromNP->getStreetTypeRef()) ||
            ($street->getStreetType() !== $streetFromNP->getStreetType());
    }

    /**
     * @param string|\Magento\Framework\Phrase $message
     * @param array $context
     */
    private function log($message, $context = []): void
    {
        if (is_callable($this->logger)) {
            ($this->logger)($message);
        } else {
            $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
            $this->logger->debug($message, $context);
        }
    }

    /**
     * @param object $dataFromEndpoint
     * @param $cityRef
     * @return array
     */
    private function prepareDataFromNP($dataFromEndpoint, $cityRef)
    {
        $streetsData = [];
        if (is_object($dataFromEndpoint) && property_exists($dataFromEndpoint, 'success')) {
            foreach ($dataFromEndpoint as $datum) {
                if (!is_object($datum)) {
                    continue;
                }
                /** @var StreetInterface $street */
                $street = $this->streetFactory->create();
                $street->setRef($datum->{self::API_FIELD_REF})
                    ->setDescription($datum->{self::API_FIELD_DESCRIPTION})
                    ->setStreetTypeRef($datum->{self::API_FIELD_STREETS_TYPE_REF})
                    ->setStreetType($datum->{self::API_FIELD_STREETS_TYPE})
                    ->setCityRef($cityRef);
                $streetsData[] = $street;
            }
            return $streetsData;
        } else {
            return [
                $this->streetFactory->create()
            ];
        }
    }
}
