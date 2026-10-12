<?php

namespace Perspective\NovaposhtaCatalog\Cron\Sync;

use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Model\Update\Area as AreaUpdate;

/**
 * Sync Nova Poshta areas via cron
 */
class Area extends AbstractAsync
{
    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Update\Area
     */
    private $areaUpdate;

    public function __construct(
        AreaUpdate $areaUpdate,
        SerializerInterface $serialize,
        State $appState
    ) {
        $this->areaUpdate = $areaUpdate;
        parent::__construct($serialize, $appState);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        ['message' => $message, 'data' => $data, 'error' => $error] = $this->areaUpdate->execute();
        return $this->serialize->serialize([
            'message' => $message,
            'data' => $data,
            'error' => $error
        ]);
    }
}
