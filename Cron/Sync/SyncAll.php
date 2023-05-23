<?php


namespace Perspective\NovaposhtaCatalog\Cron\Sync;

use Exception;
use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class SyncAll
 * Syncs all data with api
 */
class SyncAll extends AbstractAsync
{

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;

    private array $entityToUpdate;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Serialize\SerializerInterface $serialize
     * @param array $entityToUpdate
     */
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serialize,
        State $appState,
        array $entityToUpdate = []
    ) {
        $this->logger = $logger;
        $this->entityToUpdate = $entityToUpdate;
        parent::__construct($serialize, $appState);
    }

    public function execute()
    {

        foreach ($this->entityToUpdate as $key => $entity) {
            $this->logger->info('Start update ' . $key . PHP_EOL);
            echo('Start update ' . $key . PHP_EOL);
            try {
                $entity->execute();
            } catch (Exception $e) {
                echo(
                __('Novaposhta does not respond or respond has been incorrect' . PHP_EOL)
                );
                echo $e->getMessage();
                $this->logger->critical($e->getMessage());
                $this->logger->critical($e->getTraceAsString());
                if ($this->appState->getMode() === State::MODE_DEVELOPER) {
                    echo $e->getTraceAsString();
                }
            }
            $this->logger->info('End update ' . $key . PHP_EOL);
            echo('End update ' . $key . PHP_EOL);
        }

    }
}
