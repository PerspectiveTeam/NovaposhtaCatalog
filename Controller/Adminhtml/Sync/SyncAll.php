<?php


namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Perspective\NovaposhtaCatalog\Model\Update\Street;

/**
 * Class SyncAll
 * Syncs all data with api
 */
class SyncAll extends \Magento\Backend\App\Action
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
     * @var \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\Warehouse
     */
    private $warehouse;
    /**
     * @var \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\WarehouseType
     */
    private $warehouseType;
    /**
     * @var \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\City
     */
    private $city;

    /**
     * @var Street
     */
    private $street;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\Warehouse $warehouse
     * @param \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\WarehouseType $warehouseType
     * @param \Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\City $city
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $jsonHelper,
        LoggerInterface $logger,
        Warehouse $warehouse,
        WarehouseType $warehouseType,
        City $city,
        Street $street
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->warehouse = $warehouse;
        $this->warehouseType = $warehouseType;
        $this->city = $city;
        $this->street = $street;
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
             $this->warehouseType->execute();
            $this->messageManager->addSuccessMessage(
                __('Warehouse types successfully synced')
            );
            $this->warehouse->execute();
            $this->messageManager->addSuccessMessage(
                __('Warehouses successfully synced')
            );
            $this->city->execute();
            $this->messageManager->addSuccessMessage(
                __('Cities successfully synced')
            );
            $this->street->execute();
            $this->messageManager->addSuccessMessage(
                __('Cities streets successfully synced')
            );
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('novaposhtacatalog/view/data');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->jsonResponse($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addError(
                __('Novaposhta does not respond or respond has been incorrect')
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
