<?php


namespace Perspective\NovaposhtaCatalog\Api\Data;

interface ConfigSettingsInterface
{
    const MODULE_GROUP = 'perspective_novaposhta_catalog';

    const MODULE_SECTION_NAME = 'novaposhta_catalog';

    const JOB_CODE_NAME = 'update_novaposhta_catalog';

    const CRON_STRING_PATH = 'crontab/' . self::MODULE_GROUP . '/jobs/' . self::JOB_CODE_NAME . '/schedule/cron_expr';

    const CRON_MODEL_PATH = 'crontab/' . self::MODULE_GROUP . '/jobs/' . self::JOB_CODE_NAME . '/run/model';

    const XML_PATH_SCHEDULE_ENABLED = 'groups/schedule/fields/enabled/value';

    const XML_PATH_SCHEDULE_TIME = 'groups/schedule/fields/time/value';

    const XML_PATH_SCHEDULE_FREQUENCY = 'groups/schedule/fields/frequency/value';

    const CRON_EVERY = 'E';

    const CRON_SEVEN = 'S';

    const CRON_FIFTEEN = 'F';

    const CRON_THIRTY = 'T';

    const XML_PATH_LAST_SYNC_CITY = self::MODULE_SECTION_NAME . '/schedule/last_sync_city';

    const XML_PATH_LAST_SYNC_WAREHOUSE = self::MODULE_SECTION_NAME . '/schedule/last_sync_warehouse';

    const XML_PATH_LAST_SYNC_WAREHOUSE_TYPES = self::MODULE_SECTION_NAME . '/schedule/last_sync_warehouse_types';
    const XML_PATH_LAST_SYNC_PACKAGE_TYPES = self::MODULE_SECTION_NAME . '/schedule/last_sync_package_types';
    const XML_PATH_LAST_SYNC_STREETS = self::MODULE_SECTION_NAME . '/schedule/last_sync_streets';
}
