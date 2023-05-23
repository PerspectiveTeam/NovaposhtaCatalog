<?php


namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\View\Result\PageFactory;
use Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\Schedule\NewSchedule;
use Psr\Log\LoggerInterface;

/**
 * Class SyncAll
 * Syncs all data with api
 */
class Schedule extends Action
{

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\Schedule\NewSchedule
     */
    private Schedule\NewSchedule $newSchedule;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\Schedule\NewSchedule $newSchedule
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $jsonHelper,
        LoggerInterface $logger,
        NewSchedule $newSchedule
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->newSchedule = $newSchedule;
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $result = $this->newSchedule->execute();
            if (!$result['error']) {
                $this->messageManager->addSuccessMessage(
                    __('Scheduled new update')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Cannot schedule new update due to error: ' . $result['error'])
                );
            }
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('adminhtml/system_config/edit/section/novaposhta_catalog');
        } catch (LocalizedException $e) {
            return $this->jsonResponse($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addError(
                __('Cannot schedule new update')
            );
            $this->logger->critical($e);
            return $this->jsonResponse($e->getMessage());
        }
    }

    /**
     * Create json response
     *
     * @param string $response
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function jsonResponse($response = '')
    {
        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($response)
        );
    }
}
