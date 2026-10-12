<?php

namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Perspective\NovaposhtaCatalog\Model\Update\Area as AreaUpdate;

/**
 * Sync Nova Poshta areas from admin panel
 */
class Area extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Perspective_NovaposhtaCatalog::NovaposhtaCatalog';

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Update\Area
     */
    private $areaUpdate;

    public function __construct(
        Context $context,
        AreaUpdate $areaUpdate
    ) {
        $this->areaUpdate = $areaUpdate;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        ['message' => $message, 'data' => $data, 'error' => $error] = $this->areaUpdate->execute();
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData([
            'message' => $message,
            'data' => $data,
            'error' => $error
        ]);
    }
}
