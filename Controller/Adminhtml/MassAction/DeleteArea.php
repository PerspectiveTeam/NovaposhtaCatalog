<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\MassAction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\CollectionFactory;

class DeleteArea extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Perspective_NovaposhtaCatalog::NovaposhtaCatalog';

    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected Filter $filter;

    /**
     * @var \Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $item->delete();
        }

        $this->messageManager->addSuccess(__('A total of %1 element(s) have been deleted.', $collectionSize));

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
