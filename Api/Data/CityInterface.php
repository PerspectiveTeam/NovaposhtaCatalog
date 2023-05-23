<?php

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface CityInterface
{
    const CITIES_TABLE = 'perspective_novaposhta_catalog_cities';

    const ID = 'id';
    const DESCRIPTION_UA = 'descriptionua';

    /**
     * @param $data
     * @return mixed
     */
    public function setDescriptionUa($data);

    const DESCRIPTION_RU = 'descriptionru';

    /**
     * @param $data
     * @return mixed
     */
    public function setDescriptionRu($data);

    const REF = 'ref';

    /**
     * @param $data
     * @return mixed
     */
    public function setRef($data);

    const DELIVERY_1 = 'delivery_1';
    const DELIVERY_2 = 'delivery_2';
    const DELIVERY_3 = 'delivery_3';
    const DELIVERY_4 = 'delivery_4';
    const DELIVERY_5 = 'delivery_5';
    const DELIVERY_6 = 'delivery_6';
    const DELIVERY_7 = 'delivery_7';

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery1($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery2($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery3($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery4($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery5($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery6($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDelivery7($data);

    const AREA = 'area';

    /**
     * @param $data
     * @return mixed
     */
    public function setArea($data);

    const SETTLEMENT_TYPE = 'settlement_type';

    /**
     * @param $data
     * @return mixed
     */
    public function setSettlementType($data);

    const IS_BRANCH = 'is_branch';

    /**
     * @param $data
     * @return mixed
     */
    public function setIsBranch($data);

    const PREVENT_ENTRY_NEW_STREETS_USER = 'prevent_entry_new_streets_user';

    /**
     * @param $data
     * @return mixed
     */
    public function setPreventEntryNewStreetsUser($data);

    const CONGLOMERATES = 'conglomerates';

    /**
     * @param $data
     * @return mixed
     */
    public function setConglomerates($data);

    const CITYID = 'city_id';

    /**
     * @param $data
     * @return mixed
     */
    public function setCityID($data);

    const SETTLEMENT_TYPE_DESCRIPTION_RU = 'settlement_type_description_ru';

    /**
     * @param $data
     * @return mixed
     */
    public function setSettlementTypeDescriptionRu($data);

    const SETTLEMENT_TYPE_DESCRIPTION_UA = 'settlement_type_description_ua';

    /**
     * @param $data
     * @return mixed
     */
    public function setSettlementTypeDescriptionUa($data);

    const DISTRICT_CODE = 'district_code';

    /**
     * @param $getDistrictCode
     * @return mixed
     */
    public function setDistrictCode($data);

    /**
     * @return mixed
     */
    public function getDistrictCode();

    /**
     * @return mixed
     */
    public function getDescriptionUa();

    /**
     * @return mixed
     */
    public function getDescriptionRu();

    /**
     * @return mixed
     */
    public function getRef(): ?string;

    /**
     * @return mixed
     */
    public function getDelivery1();

    /**
     * @return mixed
     */
    public function getDelivery2();

    /**
     * @return mixed
     */
    public function getDelivery3();

    /**
     * @return mixed
     */
    public function getDelivery4();

    /**
     * @return mixed
     */
    public function getDelivery5();

    /**
     * @return mixed
     */
    public function getDelivery6();

    /**
     * @return mixed
     */
    public function getDelivery7();

    /**
     * @return mixed
     */
    public function getArea();

    /**
     * @return mixed
     */
    public function getIsBranch();

    /**
     * @return mixed
     */
    public function getPreventEntryNewStreetsUser();

    /**
     * @return mixed
     */
    public function getConglomerates();

    /**
     * @return mixed
     */
    public function getCityID();

    /**
     * @return mixed
     */
    public function getSettlementTypeDescriptionRu();

    /**
     * @return mixed
     */
    public function getSettlementTypeDescriptionUa();
}
