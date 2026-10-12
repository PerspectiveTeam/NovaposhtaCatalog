<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Block\Adminhtml\Grid;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Catalog grid button that triggers a sync with a POST request
 *
 * Configured per grid through virtual types in etc/di.xml.
 */
class SyncButton implements ButtonProviderInterface
{
    private const ACL_RESOURCE = 'Perspective_NovaposhtaCatalog::NovaposhtaCatalog';

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var string
     */
    private string $route;

    /**
     * @var string
     */
    private string $label;

    /**
     * @var string
     */
    private string $confirmMessage;

    /**
     * @param UrlInterface $urlBuilder
     * @param string $route
     * @param string $label
     * @param string $confirmMessage
     */
    public function __construct(
        UrlInterface $urlBuilder,
        string $route,
        string $label = 'Sync with Novaposhta',
        string $confirmMessage = 'Run synchronization with Nova Poshta now?'
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->route = $route;
        $this->label = $label;
        $this->confirmMessage = $confirmMessage;
    }

    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        return [
            'label' => __($this->label),
            'class' => 'primary',
            'on_click' => sprintf(
                'deleteConfirm(%s, %s, {"data": {}})',
                json_encode((string)__($this->confirmMessage)),
                json_encode($this->urlBuilder->getUrl($this->route))
            ),
            'aclResource' => self::ACL_RESOURCE,
            'sort_order' => 10,
        ];
    }
}
