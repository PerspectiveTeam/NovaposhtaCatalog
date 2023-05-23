<?php

namespace Perspective\NovaposhtaCatalog\Cron;

use Perspective\NovaposhtaCatalog\Cron\Sync\SyncAll;

class Update
{
    /**
     * @var \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\SyncAll
     */
    private $syncAll;

    /**
     * Update constructor.
     *
     * @param \Perspective\NovaposhtaCatalog\Cron\Sync\SyncAll $syncAll
     */
    public function __construct(
        SyncAll $syncAll
    ) {
        $this->syncAll = $syncAll;
    }

    public function execute()
    {
        $this->syncAll->execute();
    }
}
