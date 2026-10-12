<?php


namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Json\Helper\Data;
use Psr\Log\LoggerInterface;
use Perspective\NovaposhtaCatalog\Model\Update\Street;

/**
 * Class SyncAll
 * Syncs all data with api
 */
class SyncAll extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Perspective_NovaposhtaCatalog::settings';

    public function __construct(
        Context $context,
        private readonly Data $jsonHelper,
        private readonly LoggerInterface $logger,
        private readonly Warehouse $warehouse,
        private readonly WarehouseType $warehouseType,
        private readonly City $city,
        private readonly Street $street,
        private readonly Area $area
    )
    {
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $this->area->execute();
            $this->messageManager->addSuccessMessage(
                __('Areas successfully synced')
            );
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
