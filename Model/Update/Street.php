<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Api\Data\CityInterface;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterface;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory;
use Perspective\NovaposhtaCatalog\Api\Data\UpdateEntityInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory as CityCollectionFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory as StreetCollectionFactory;

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

    private const API_FIELD_DESCRIPTION = 'Description';

    private const API_FIELD_STREETS_TYPE_REF = 'StreetsTypeRef';

    private const API_FIELD_STREETS_TYPE = 'StreetsType';

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
     * @var \Perspective\NovaposhtaCatalog\Helper\Config
     */
    private $configHelper;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    private $httpClientFactory;


    protected bool $requestNotAllowed = false;

    /**
     * @var \Closure|mixed|\Psr\Log\LoggerInterface|null
     */
    private $logger;

    private CronSyncDateLastUpdate $cronSyncDateLastUpdate;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory $cityCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory $streetCollectionFactory
     * @param \Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory $streetFactory
     * @param \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street $streetResource
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $configHelper
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate $cronSyncDateLastUpdate
     */
    public function __construct(
        CityCollectionFactory $cityCollectionFactory,
        StreetCollectionFactory $streetCollectionFactory,
        StreetInterfaceFactory $streetFactory,
        \Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street $streetResource,
        Config $configHelper,
        ZendClientFactory $httpClientFactory,
        SerializerInterface $serializer,
        CronSyncDateLastUpdate $cronSyncDateLastUpdate
    ) {

        $this->cityCollectionFactory = $cityCollectionFactory;
        $this->streetCollectionFactory = $streetCollectionFactory;
        $this->streetFactory = $streetFactory;
        $this->streetResource = $streetResource;
        $this->configHelper = $configHelper;
        $this->httpClientFactory = $httpClientFactory;
        $this->cronSyncDateLastUpdate = $cronSyncDateLastUpdate;
        $this->serializer = $serializer;
    }

    /**
     * @param \Closure|null $cl
     */
    public function execute()
    {
        $this->log(__('Start import streets'));
        $message = "Error has been occur";
        $error = true;
        $data = [];

        foreach ($this->getCityCollection() as $city) {
            if ($city->getRef() === null) {
                continue;
            }
            $this->log('Importing for city ' . $city->getRef());
            $this->setDataToDB($city->getRef());
            $error = false;
            $message = "Successfully synced";
        }
        if ($error === false) {
            $this->cronSyncDateLastUpdate->updateSyncDate($this->cronSyncDateLastUpdate::XML_PATH_LAST_SYNC_STREETS);
        }
        $this->log(__($message));
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
     * @param string $cityRef
     */
    public function setDataToDB(...$params)
    {
        $cityRef = $params[0];
        $streetsFromNP = $this->importStreets($cityRef);
        if (empty($streetsFromNP)) {
            $this->log(__('Street Request error. City has no Streets OR Check API key.'));
            return;
        }

        $streets = $this->getStreetsFromDb($cityRef);

        foreach ($streetsFromNP as $streetFromNP) {
            /** @var StreetInterface|null $street */
            $street = $streets->getItemByColumnValue(StreetInterface::REF, $streetFromNP->getRef());
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
     * @param string $cityRef
     * @return array<StreetInterface>
     */
    private function importStreets(string $cityRef): array
    {
        $params = $this->prepareRequestParams($cityRef);

        $streetsData = [];
        try {
            $dataFromEndpoint = $this->serializer->unserialize($this->getDataFromEndpoint($params));
        } catch (\InvalidArgumentException $invalidArgumentException) {
            return $streetsData;
        }

        $data = $dataFromEndpoint['data'] ?? null;
        if (!$data) {
            return $streetsData;
        }

        foreach ($data as $datum) {
            /** @var StreetInterface $street */
            $street = $this->streetFactory->create();
            $street->setRef($datum[self::API_FIELD_REF])
                ->setDescription($datum[self::API_FIELD_DESCRIPTION])
                ->setStreetTypeRef($datum[self::API_FIELD_STREETS_TYPE_REF])
                ->setStreetType($datum[self::API_FIELD_STREETS_TYPE])
                ->setCityRef($cityRef);
            $streetsData[] = $street;
        }

        return $streetsData;
    }

    /**
     * @param ...$params
     * @return bool|mixed|string|null
     * @throws \Zend_Http_Client_Exception
     */
    public function getDataFromEndpoint(...$params)
    {
        $params = $params[0];
        $result = $this->serializer->serialize(['no_street_data' => 1]);
        $this->requestNotAllowed = false;
        $apiKey = $this->configHelper->getApiKey();
        $request = $this->httpClientFactory->create();
        $request->setUri('https://api.novaposhta.ua/v2.0/json/Address/getStreet');
        $params['apiKey'] = $apiKey;
        $request->setConfig(['maxredirects' => 0, 'timeout' => 10]);
        $request->setRawData(utf8_encode($this->serializer->serialize($params)));

        //Новая почта иногда может ответить пустым запросом - спрашиваем 3 раза и пропускаем эту улицу
        while (!$this->requestNotAllowed) {
            if (!isset($count)) {
                $count = 0;
            }
            $count++;
            if ($count > 1) {
                sleep(3);
                $this->log(__('Attempt to get street data number %1', $count), $params);
            }
            try {
                $result = $request->request(\Zend_Http_Client::POST)->getBody();
            } catch (\Exception $exception) {
                //do nothing
                $this->log(__($exception->getMessage()), $params);
            }
            if ($count > 2 || ($result && ($this->serializer->unserialize($result)['success'] ?? false === true))) {
                unset($count);
                $this->requestNotAllowed = true;
            }
        }
        return $result;
    }

    /**
     * @param string $cityRef
     * @return array<mixed>
     */
    private function prepareRequestParams(string $cityRef): array
    {
        $params = [
            'modelName' => 'Address',
            'calledMethod' => 'getStreet',
            "methodProperties" => [
                "CityRef" => $cityRef,
                "Limit" => 100000
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
     * @param int|string|null $streetId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function saveStreet(StreetInterface $street, $streetId = null): void
    {
        if ($streetId !== null) {
            $street->setStreetId((int)$streetId);
        }
        try {
            //TODO: replace with repository
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
}
