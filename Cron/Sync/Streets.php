<?php


namespace Perspective\NovaposhtaCatalog\Cron\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\State;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Model\Update\Street as UpdateHelper;

/**
 * Class Streets
 * Sync Types of novaposhta Streets and sets to db (Admin and cron)
 */
class Streets extends AbstractAsync
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var UpdateHelper
     */
    private $updateHelper;

    /**
     * @param \Perspective\NovaposhtaCatalog\Model\Update\Street $updateHelper
     * @param \Magento\Framework\Serialize\SerializerInterface $serialize
     */
    public function __construct(
        UpdateHelper $updateHelper,
        SerializerInterface $serialize,
        State $appState
    ) {
        $this->updateHelper = $updateHelper;
        parent::__construct($serialize, $appState);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        ['message' => $message, 'data' => $data, 'error' => $error] = $this->updateHelper->execute();
        return $this->serialize->serialize([
            'message' => $message,
            'data' => $data,
            'error' => $error
        ]);
    }
}
