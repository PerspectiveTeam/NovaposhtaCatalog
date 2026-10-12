<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Controller\Adminhtml\MassAction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;

class Deactivate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Perspective_NovaposhtaCatalog::settings';

    public function __construct(
        Context $context,
        private Filter $filter,
        private DirectorySnapshot $snapshot,
        private array $collectionFactories,
        private array $resources,
        private string $defaultDirectory = 'warehouses'
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $directory = $this->getRequest()->getParam('directory', $this->defaultDirectory);
            if (!is_string($directory) || !isset($this->collectionFactories[$directory], $this->resources[$directory])) {
                throw new LocalizedException(__('Invalid Nova Poshta directory.'));
            }
            $collection = $this->filter->getCollection($this->collectionFactories[$directory]->create());
            $groups = [];
            foreach ($collection as $item) {
                $lock = match ($directory) {
                    'warehouses' => 'np_warehouses_' . sha1($item->getData('city_ref')),
                    'streets' => 'np_streets_' . sha1($item->getData('city_ref')),
                    default => 'perspective_novaposhta_' . $directory . '_sync',
                };
                $groups[$lock][] = $item;
            }
            $resource = $this->resources[$directory];
            $count = 0;
            foreach ($groups as $lock => $items) {
                $this->snapshot->withLock($lock, function () use ($resource, $items) {
                    $db = $resource->getConnection();
                    $db->beginTransaction();
                    try {
                        foreach ($items as $item) {
                            // Update only availability, never overwrite data loaded before acquiring the lock.
                            $db->update($resource->getMainTable(), ['is_active' => 0], ['id = ?' => $item->getId()]);
                        }
                        $db->commit();
                    } catch (\Throwable $e) {
                        $db->rollBack();
                        throw $e;
                    }
                });
                $count += count($items);
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deactivated. A successful API reappearance can reactivate them.', $count));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Deactivation failed. Refresh the grid to review the current state.'));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setUrl($this->_redirect->getRefererUrl());
    }
}
