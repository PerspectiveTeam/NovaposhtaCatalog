<?php


namespace Perspective\NovaposhtaCatalog\Cron\Sync;

use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Model\Update\City as CityUpdate;

/**
 * Class City
 * Sync Types of novaposhta city and sets to db (cron)
 */
class City extends AbstractAsync
{

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Update\City
     */
    private $cityUpdate;

    /**
     * @param \Perspective\NovaposhtaCatalog\Model\Update\City $cityUpdate
     * @param \Magento\Framework\Serialize\SerializerInterface $serialize
     */
    public function __construct(
        CityUpdate $cityUpdate,
        SerializerInterface $serialize,
        State $appState
    ) {
        $this->cityUpdate = $cityUpdate;
        parent::__construct($serialize, $appState);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        ['message' => $message, 'data' => $data, 'error' => $error] = $this->cityUpdate->execute();
        return $this->serialize->serialize([
            'message' => $message,
            'data' => $data,
            'error' => $error
        ]);
    }
}
