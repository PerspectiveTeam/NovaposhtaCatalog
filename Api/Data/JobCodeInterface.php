<?php

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface JobCodeInterface
{
    public const JOB_CODE_NAME = 'update_novaposhta_catalog';
    const MODULE_GROUP = 'perspective_novaposhta_catalog';
    const CRON_STRING_PATH = 'crontab/' . self::MODULE_GROUP . '/jobs/' . self::JOB_CODE_NAME . '/schedule/cron_expr';
    const CRON_MODEL_PATH = 'crontab/' . self::MODULE_GROUP . '/jobs/' . self::JOB_CODE_NAME . '/run/model';
}
