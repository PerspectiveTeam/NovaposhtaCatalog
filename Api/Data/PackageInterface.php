<?php


namespace Perspective\NovaposhtaCatalog\Api\Data;

interface PackageInterface
{
    const DB_ID = 'id';

    const DATABASE_TABLE_NAME = 'perspective_novaposhta_catalog_package_types';

    const REF = 'ref';

    const DESCRIPTION_UA = 'description_ua';

    const DESCRIPTION_RU = 'description_ru';

    const LENGTH = 'length';

    const WIDTH = 'width';

    const HEIGHT = 'height';

    const VOLUMETRIC_WEIGHT = 'volumetric_weight';

    const PACKAGING_FOR_PLACE = 'packaging_for_place';

    const SOURCE = 'source';

    const BOXPACKER_ENABLED = 'boxpacker_enabled';

    const MAX_WEIGHT = 'max_weight';

    const INNER_LENGTH = 'inner_length';

    const INNER_WIDTH = 'inner_width';

    const INNER_HEIGHT = 'inner_height';

    const EMPTY_WEIGHT = 'empty_weight';

    /*
     * Setters
     */
    public function setDescriptionUa($data);

    public function setDescriptionRu($data);

    public function setRef($data);

    public function setLength($data);

    public function setWidth($data);

    public function setHeight($data);
    public function setVolumetricWeight($data);
    public function setPackagingForPlace($data);

    /*
     * Getters
     */
    public function getDescriptionUa();

    public function getDescriptionRu();

    public function getRef();

    public function getLength();

    public function getWidth();

    public function getHeight();
    public function getVolumetricWeight();
    public function getPackagingForPlace();

    public function getSource();

    public function setSource($data);

    public function getBoxpackerEnabled();

    public function setBoxpackerEnabled($data);

    public function getMaxWeight();

    public function setMaxWeight($data);

    public function getInnerLength();

    public function setInnerLength($data);

    public function getInnerWidth();

    public function setInnerWidth($data);

    public function getInnerHeight();

    public function setInnerHeight($data);

    public function getEmptyWeight();

    public function setEmptyWeight($data);
}
