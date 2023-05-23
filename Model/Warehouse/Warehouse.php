<?php


namespace Perspective\NovaposhtaCatalog\Model\Warehouse;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Perspective\NovaposhtaCatalog\Api\Data\WarehouseInterface;

class Warehouse extends \Magento\Framework\Model\AbstractExtensibleModel implements WarehouseInterface
{
    /**
     * @var \ReflectionClassFactory
     */
    private $reflectionClassFactory;

    private \Magento\Framework\Serialize\Serializer\Json $jsonSerializer;

    protected function _construct()
    {
        $this->_init(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\Warehouse::class);
    }

    /**
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
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer,
        \ReflectionClassFactory $reflectionClassFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
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
        $this->reflectionClassFactory = $reflectionClassFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function getCustomAttributesCodes()
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
            $this->reflectionClass = $this->reflectionClassFactory->create(['argument' => self::class]);
        }else{
            $this->reflectionClass = $this->reflectionClassFactory->create(['objectOrClass' => self::class]);
        }

        $constants = $this->reflectionClass->getConstants();
        array_push($constants, self::ID);
        return $constants;
    }

    public function setSiteKey($data)
    {
        return $this->setData(self::SITE_KEY, $data);
    }

    public function setDescriptionUa($data)
    {
        return $this->setData(self::DESCRIPTION_UA, $data);
    }

    public function setDescriptionRu($data)
    {
        return $this->setData(self::DESCRIPTION_RU, $data);
    }

    public function setShortAddressUa($data)
    {
        return $this->setData(self::SHORT_ADDRESS_UA, $data);
    }

    public function setShortAddressRu($data)
    {
        return $this->setData(self::SHORT_ADDRESS_RU, $data);
    }

    public function setPhone($data)
    {
        return $this->setData(self::PHONE, $data);
    }

    public function setTypeOfWarehouse($data)
    {
        return $this->setData(self::TYPE_OF_WAREHOUSE, $data);
    }

    public function setRef($data)
    {
        return $this->setData(self::REF, $data);
    }

    public function setNumberInCity($data)
    {
        return $this->setData(self::NUMBER_IN_CITY, $data);
    }

    public function setCityRef($data)
    {
        return $this->setData(self::CITY_REF, $data);
    }

    public function setCityDescriptionUa($data)
    {
        return $this->setData(self::CITY_DESCRIPTION_UA, $data);
    }

    public function setCityDescriptionRu($data)
    {
        return $this->setData(self::CITY_DESCRIPTION_RU, $data);
    }

    public function setSettlementRef($data)
    {
        return $this->setData(self::SETTLEMENT_REF, $data);
    }

    public function setSettlementDescription($data)
    {
        return $this->setData(self::SETTLEMENT_DESCRIPTION, $data);
    }

    public function setSettlementAreaDescription($data)
    {
        return $this->setData(self::SETTLEMENT_AREA_DESCRIPTION, $data);
    }

    public function setSettlementRegionDescription($data)
    {
        return $this->setData(self::SETTLEMENT_REGION_DESCRIPTION, $data);
    }

    public function setSettlementTypeDescription($data)
    {
        return $this->setData(self::SETTLEMENT_TYPE_DESCRIPTION, $data);
    }

    public function setLongitude($data)
    {
        return $this->setData(self::LONGITUDE, $data);
    }

    public function setLatitude($data)
    {
        return $this->setData(self::LATITUDE, $data);
    }

    public function setPostFinance($data)
    {
        return $this->setData(self::POST_FINANCE, $data);
    }

    public function setBicycleParking($data)
    {
        return $this->setData(self::BICYCLE_PARKING, $data);
    }

    public function setPaymentAccess($data)
    {
        return $this->setData(self::PAYMENT_ACCESS, $data);
    }

    public function setPOSTerminal($data)
    {
        return $this->setData(self::POS_TERMINAL, $data);
    }

    public function setInternationalShipping($data)
    {
        return $this->setData(self::INTERNATIONAL_SHIPPING, $data);
    }

    public function setTotalMaxWeightAllowed($data)
    {
        return $this->setData(self::TOTAL_MAX_WEIGHT_ALLOWED, $data);
    }

    public function setPlaceMaxWeightAllowed($data)
    {
        return $this->setData(self::PLACE_MAX_WEIGHT_ALLOWED, $data);
    }

    public function setReception($data)
    {
        return $this->setData(self::RECEPTION, $this->jsonSerializer->serialize($data));
        // return $this->setData(self::RECEPTION, json_encode($data));
    }

    public function setDelivery($data)
    {
        return $this->setData(self::DELIVERY, $this->jsonSerializer->serialize($data));
//        return $this->setData(self::DELIVERY, json_encode($data));
    }

    public function setSchedule($data)
    {
        return $this->setData(self::SCHEDULE, $this->jsonSerializer->serialize($data));
//        return $this->setData(self::SCHEDULE, json_encode($data));
    }

    public function setDistrictCode($data)
    {
        return $this->setData(self::DISTRICT_CODE, $data);
    }

    public function setWarehouseStatus($data)
    {
        return $this->setData(self::WAREHOUSE_STATUS, $data);
    }

    public function setCategoryOfWarehouse($data)
    {
        return $this->setData(self::CATEGORY_OF_WAREHOUSE, $data);
    }

    public function getSiteKey()
    {
        return $this->getData(self::SITE_KEY);
    }

    public function getDescriptionUa()
    {
        return $this->getData(self::DESCRIPTION_UA);
    }

    public function getDescriptionRu()
    {
        return $this->getData(self::DESCRIPTION_RU);
    }

    public function getShortAddressUa()
    {
        return $this->getData(self::SHORT_ADDRESS_UA);
    }

    public function getShortAddressRu()
    {
        return $this->getData(self::SHORT_ADDRESS_RU);
    }

    public function getPhone()
    {
        return $this->getData(self::PHONE);
    }

    public function getTypeOfWarehouse()
    {
        return $this->getData(self::TYPE_OF_WAREHOUSE);
    }

    public function getRef()
    {
        return $this->getData(self::REF);
    }

    public function getNumberInCity()
    {
        return $this->getData(self::NUMBER_IN_CITY);
    }

    public function getCityRef()
    {
        return $this->getData(self::CITY_REF);
    }

    public function getCityDescriptionUa()
    {
        return $this->getData(self::CITY_DESCRIPTION_UA);
    }

    public function getCityDescriptionRu()
    {
        return $this->getData(self::CITY_DESCRIPTION_RU);
    }

    public function getSettlementRef()
    {
        return $this->getData(self::SETTLEMENT_REF);
    }

    public function getSettlementDescription()
    {
        return $this->getData(self::SETTLEMENT_DESCRIPTION);
    }

    public function getSettlementAreaDescription()
    {
        return $this->getData(self::SETTLEMENT_AREA_DESCRIPTION);
    }

    public function getSettlementRegionDescription()
    {
        return $this->getData(self::SETTLEMENT_REGION_DESCRIPTION);
    }

    public function getSettlementTypeDescription()
    {
        return $this->getData(self::SETTLEMENT_TYPE_DESCRIPTION);
    }

    public function getLongitude()
    {
        return $this->getData(self::LONGITUDE);
    }

    public function getLatitude()
    {
        return $this->getData(self::LATITUDE);
    }

    public function getPostFinance()
    {
        return $this->getData(self::POST_FINANCE);
    }

    public function getBicycleParking()
    {
        return $this->getData(self::BICYCLE_PARKING);
    }

    public function getPaymentAccess()
    {
        return $this->getData(self::PAYMENT_ACCESS);
    }

    public function getPOSTerminal()
    {
        return $this->getData(self::POS_TERMINAL);
    }

    public function getInternationalShipping()
    {
        return $this->getData(self::INTERNATIONAL_SHIPPING);
    }

    public function getTotalMaxWeightAllowed()
    {
        return $this->getData(self::TOTAL_MAX_WEIGHT_ALLOWED);
    }

    public function getPlaceMaxWeightAllowed()
    {
        return $this->getData(self::PLACE_MAX_WEIGHT_ALLOWED);
    }

    public function getReception()
    {
        return $this->jsonSerializer->unserialize($this->getData(self::RECEPTION));
//        return json_decode($this->getData(self::RECEPTION));
    }

    public function getDelivery()
    {
        return $this->jsonSerializer->unserialize($this->getData(self::DELIVERY));
//        return json_decode($this->getData(self::DELIVERY));
    }

    public function getSchedule()
    {
        return $this->jsonSerializer->unserialize($this->getData(self::SCHEDULE));
//        return json_decode($this->getData(self::SCHEDULE));
    }

    public function getDistrictCode()
    {
        return $this->getData(self::DISTRICT_CODE);
    }

    public function getWarehouseStatus()
    {
        return $this->getData(self::WAREHOUSE_STATUS);
    }

    public function getCategoryOfWarehouse()
    {
        return $this->getData(self::CATEGORY_OF_WAREHOUSE);
    }
}
