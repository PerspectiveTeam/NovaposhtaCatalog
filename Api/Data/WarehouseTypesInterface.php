<?php

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface WarehouseTypesInterface
{
    const DATABASE_TABLE_NAME = 'perspective_novaposhta_catalog_warehouse_types';

    const DESCRIPTION_UA = 'description_ua';

    public function setDescriptionUa($data);

    const DESCRIPTION_RU = 'description_ru';

    public function setDescriptionRu($data);

    const REF = 'ref';
    const ID = 'id';

    public function setRef($data);

    public function getDescriptionUa();

    public function getDescriptionRu();

    public function getRef();
}
