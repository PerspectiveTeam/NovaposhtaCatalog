<?php


namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Perspective\NovaposhtaCatalog\Model\Update\City as CityUpdate;

/**
 * Class City
 * Sync Types of novaposhta city and sets to db (Admin)
 */
class City extends Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\Update\City
     */
    private $cityUpdate;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\ResultFactory $resultFactory
     * @param \Perspective\NovaposhtaCatalog\Model\Update\City $cityUpdate
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        CityUpdate $cityUpdate
    ) {
        $this->cityUpdate = $cityUpdate;
        $this->resultFactory = $resultFactory;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        ['message' => $message, 'data' => $data, 'error' => $error] = $this->cityUpdate->execute();
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData([
            'message' => $message,
            'data' => $data,
            'error' => $error
        ]);
    }
}
