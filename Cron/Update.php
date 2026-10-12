<?php

namespace Perspective\NovaposhtaCatalog\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Perspective\NovaposhtaCatalog\Cron\Sync\SyncAll;

class Update
{
    private const CONFIG_PATH_SCHEDULE_ENABLED = 'novaposhta_catalog/schedule/enabled';

    private SyncAll $syncAll;

    private ScopeConfigInterface $scopeConfig;

    /**
     * Update constructor.
     *
     * @param \Perspective\NovaposhtaCatalog\Cron\Sync\SyncAll $syncAll
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        SyncAll $syncAll,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->syncAll = $syncAll;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        if (!$this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_SCHEDULE_ENABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        )) {
            return;
        }

        $this->syncAll->execute();
    }
}
