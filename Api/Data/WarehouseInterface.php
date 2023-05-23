<?php

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface WarehouseInterface
{
    const DATABASE_TABLE_NAME = 'perspective_novaposhta_catalog_warehouse';

    const ID = 'id';
    const SITE_KEY = 'site_key';

    public function setSiteKey($data);

    const DESCRIPTION_UA = 'description_ua';

    public function setDescriptionUa($data);

    const DESCRIPTION_RU = 'description_ru';

    public function setDescriptionRu($data);

    const SHORT_ADDRESS_UA = 'short_address_ua';

    public function setShortAddressUa($data);

    const SHORT_ADDRESS_RU = 'short_address_ru';

    public function setShortAddressRu($data);

    const PHONE = 'phone';

    public function setPhone($data);

    const TYPE_OF_WAREHOUSE = 'type_of_warehouse';

    public function setTypeOfWarehouse($data);

    const REF = 'ref';

    public function setRef($data);

    const NUMBER_IN_CITY = 'number_in_city';

    public function setNumberInCity($data);

    const CITY_REF = 'city_ref';

    public function setCityRef($data);

    const CITY_DESCRIPTION_UA = 'city_description_ua';

    public function setCityDescriptionUa($data);

    const CITY_DESCRIPTION_RU = 'city_description_ru';

    public function setCityDescriptionRu($data);

    const SETTLEMENT_REF = 'settlement_ref';

    public function setSettlementRef($data);

    const SETTLEMENT_DESCRIPTION = 'settlement_description';

    public function setSettlementDescription($data);

    const SETTLEMENT_AREA_DESCRIPTION = 'settlement_area_description';

    public function setSettlementAreaDescription($data);

    const SETTLEMENT_REGION_DESCRIPTION = 'settlement_region_description';

    public function setSettlementRegionDescription($data);

    const SETTLEMENT_TYPE_DESCRIPTION = 'settlement_type_description';

    public function setSettlementTypeDescription($data);

    const LONGITUDE = 'longitude';

    public function setLongitude($data);

    const LATITUDE = 'latitude';

    public function setLatitude($data);

    const POST_FINANCE = 'post_finance';

    public function setPostFinance($data);

    const BICYCLE_PARKING = 'bicycle_parking';

    public function setBicycleParking($data);

    const PAYMENT_ACCESS = 'payment_access';

    public function setPaymentAccess($data);

    const POS_TERMINAL = 'pos_terminal';

    public function setPOSTerminal($data);

    const INTERNATIONAL_SHIPPING = 'international_shipping';

    public function setInternationalShipping($data);

    const TOTAL_MAX_WEIGHT_ALLOWED = 'total_max_weigh_tallowed';

    public function setTotalMaxWeightAllowed($data);

    const PLACE_MAX_WEIGHT_ALLOWED = 'place_max_weight_allowed';

    public function setPlaceMaxWeightAllowed($data);

    const RECEPTION = 'reception';

    public function setReception($data);

    const DELIVERY = 'delivery';

    public function setDelivery($data);

    const SCHEDULE = 'schedule';

    public function setSchedule($data);

    const DISTRICT_CODE = 'district_code';

    public function setDistrictCode($data);

    const WAREHOUSE_STATUS = 'warehouse_status';

    public function setWarehouseStatus($data);

    const CATEGORY_OF_WAREHOUSE = 'category_of_warehouse';

    public function setCategoryOfWarehouse($data);

    public function getSiteKey();

    public function getDescriptionUa();

    public function getDescriptionRu();

    public function getShortAddressUa();

    public function getShortAddressRu();

    public function getPhone();

    public function getTypeOfWarehouse();

    public function getRef();

    public function getNumberInCity();

    public function getCityRef();

    public function getCityDescriptionUa();

    public function getCityDescriptionRu();

    public function getSettlementRef();

    public function getSettlementDescription();

    public function getSettlementAreaDescription();

    public function getSettlementRegionDescription();

    public function getSettlementTypeDescription();

    public function getLongitude();

    public function getLatitude();

    public function getPostFinance();

    public function getBicycleParking();

    public function getPaymentAccess();

    public function getPOSTerminal();

    public function getInternationalShipping();

    public function getTotalMaxWeightAllowed();

    public function getPlaceMaxWeightAllowed();

    public function getReception();

    public function getDelivery();

    public function getSchedule();

    public function getDistrictCode();

    public function getWarehouseStatus();

    public function getCategoryOfWarehouse();
}
