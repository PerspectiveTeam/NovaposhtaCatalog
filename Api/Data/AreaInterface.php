<?php

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface AreaInterface
{
    const AREAS_TABLE = 'perspective_novaposhta_catalog_areas';

    const ID = 'id';
    const REF = 'ref';
    const AREAS_CENTER = 'areas_center';
    const DESCRIPTION_UA = 'description_ua';
    const DESCRIPTION_RU = 'description_ru';

    /**
     * @param $data
     * @return mixed
     */
    public function setRef($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setAreasCenter($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDescriptionUa($data);

    /**
     * @param $data
     * @return mixed
     */
    public function setDescriptionRu($data);

    /**
     * @return string|null
     */
    public function getRef(): ?string;

    /**
     * @return mixed
     */
    public function getAreasCenter();

    /**
     * @return mixed
     */
    public function getDescriptionUa();

    /**
     * @return mixed
     */
    public function getDescriptionRu();
}
