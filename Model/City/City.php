<?php


namespace Perspective\NovaposhtaCatalog\Model\City;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\NovaposhtaCatalog\Api\Data\CityInterface;

class City extends AbstractExtensibleModel implements CityInterface
{
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonSerializer;

    private \ReflectionClassFactory $reflectionClassFactory;

    /**
     * City constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
     * @param \ReflectionClassFactory $reflectionClassFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Json $jsonSerializer,
        \ReflectionClassFactory $reflectionClassFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
        $this->jsonSerializer = $jsonSerializer;
        $this->reflectionClassFactory = $reflectionClassFactory;
    }

    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City::class);
    }

    /**
     * @param $data
     * @return \Magento\Framework\Model\AbstractModel|mixed|\Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function setDescriptionUa($data)
    {
        return $this->setData(self::DESCRIPTION_UA, $data);
    }

    /**
     * @param $data
     * @return \Magento\Framework\Model\AbstractModel|mixed|\Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function setDescriptionRu($data)
    {
        return $this->setData(self::DESCRIPTION_RU, $data);
    }

    /**
     * @param $data
     * @return \Magento\Framework\Model\AbstractModel|mixed|\Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function setRef($data)
    {
        return $this->setData(self::REF, $data);
    }

    /**
     * @return mixed
     */
    public function getDescriptionUa()
    {
        return $this->getData(self::DESCRIPTION_UA);
    }

    /**
     * @return mixed
     */
    public function getDescriptionRu()
    {
        return $this->getData(self::DESCRIPTION_RU);
    }

    /**
     * @return string|null
     */
    public function getRef(): ?string
    {
        return $this->getData(self::REF);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery1($data)
    {
        return $this->setData(self::DELIVERY_1, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery2($data)
    {
        return $this->setData(self::DELIVERY_2, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery3($data)
    {
        return $this->setData(self::DELIVERY_3, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery4($data)
    {
        return $this->setData(self::DELIVERY_4, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery5($data)
    {
        return $this->setData(self::DELIVERY_5, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery6($data)
    {
        return $this->setData(self::DELIVERY_6, $data);
    }

    /**
     * @inheritDoc
     */
    public function setDelivery7($data)
    {
        return $this->setData(self::DELIVERY_7, $data);
    }

    /**
     * @inheritDoc
     */
    public function setArea($data)
    {
        return $this->setData(self::AREA, $data);
    }

    /**
     * @inheritDoc
     */
    public function setSettlementType($data)
    {
        return $this->setData(self::SETTLEMENT_TYPE, $data);
    }

    /**
     * @inheritDoc
     */
    public function setIsBranch($data)
    {
        return $this->setData(self::IS_BRANCH, $data);
    }

    /**
     * @inheritDoc
     */
    public function setPreventEntryNewStreetsUser($data)
    {
        return $this->setData(self::PREVENT_ENTRY_NEW_STREETS_USER, $data);
    }

    /**
     * @inheritDoc
     */
    public function setConglomerates($data)
    {
        return $data ?
            $this->setData(self::CONGLOMERATES, $this->jsonSerializer->serialize($data)) :
            $this->setData(self::CONGLOMERATES, null);
    }

    /**
     * @inheritDoc
     */
    public function setCityID($data)
    {
        return $this->setData(self::CITYID, $data);
    }

    /**
     * @inheritDoc
     */
    public function setSettlementTypeDescriptionRu($data)
    {
        return $this->setData(self::SETTLEMENT_TYPE_DESCRIPTION_RU, $data);
    }

    /**
     * @inheritDoc
     */
    public function setSettlementTypeDescriptionUa($data)
    {
        return $this->setData(self::SETTLEMENT_TYPE_DESCRIPTION_UA, $data);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery1()
    {
        return $this->getData(self::DELIVERY_1);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery2()
    {
        return $this->getData(self::DELIVERY_2);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery3()
    {
        return $this->getData(self::DELIVERY_3);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery4()
    {
        return $this->getData(self::DELIVERY_4);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery5()
    {
        return $this->getData(self::DELIVERY_5);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery6()
    {
        return $this->getData(self::DELIVERY_6);
    }

    /**
     * @inheritDoc
     */
    public function getDelivery7()
    {
        return $this->getData(self::DELIVERY_7);
    }

    /**
     * @inheritDoc
     */
    public function getArea()
    {
        return $this->getData(self::AREA);
    }

    /**
     * @inheritDoc
     */
    public function getSettlementType()
    {
        return $this->getData(self::SETTLEMENT_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function getIsBranch()
    {
        return $this->getData(self::IS_BRANCH);
    }

    /**
     * @inheritDoc
     */
    public function getPreventEntryNewStreetsUser()
    {
        return $this->getData(self::PREVENT_ENTRY_NEW_STREETS_USER);
    }

    /**
     * @inheritDoc
     */
    public function getConglomerates()
    {
        return $this->jsonSerializer->unserialize($this->getData(self::CONGLOMERATES));
    }

    /**
     * @inheritDoc
     */
    public function getCityID()
    {
        return $this->getData(self::CITYID);
    }

    /**
     * @inheritDoc
     */
    public function getSettlementTypeDescriptionRu()
    {
        return $this->getData(self::SETTLEMENT_TYPE_DESCRIPTION_RU);
    }

    /**
     * @inheritDoc
     */
    public function getSettlementTypeDescriptionUa()
    {
        return $this->getData(self::SETTLEMENT_TYPE_DESCRIPTION_UA);
    }

    /**
     * @param $data
     * @return \Magento\Framework\Model\AbstractModel|mixed|\Perspective\NovaposhtaCatalog\Model\City\City
     */
    public function setDistrictCode($data)
    {
        return $this->setData(self::DISTRICT_CODE, $data);
    }

    /**
     * @return mixed
     */
    public function getDistrictCode()
    {
        return $this->getData(self::DISTRICT_CODE);
    }

    public function getCustomAttributesCodes()
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
            $this->reflectionClass = $this->reflectionClassFactory->create(['argument' => self::class]);
        }else{
            $this->reflectionClass = $this->reflectionClassFactory->create(['objectOrClass' => self::class]);
        }

        $constants = $this->reflectionClass->getConstants();
        array_push($constants, self::DISTRICT_CODE);
        return $constants;
    }
}
