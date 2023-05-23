<?php


namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Perspective\NovaposhtaCatalog\Model\Update\Package as UpdateHelper;

/**
 * Class Package
 * Sync Types of novaposhta packages and sets to db (Admin)
 */
class Package extends Action
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
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\ResultFactory $resultFactory
     * @param \Perspective\NovaposhtaCatalog\Model\Update\Package $updateHelper
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        UpdateHelper $updateHelper
    ) {
        $this->resultFactory = $resultFactory;
        $this->updateHelper = $updateHelper;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        ['message' => $message, 'data' => $data, 'error' => $error] = $this->updateHelper->execute();
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData([
            'message' => $message,
            'data' => $data,
            'error' => $error
        ]);
    }
}
