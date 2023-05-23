<?php


namespace Perspective\NovaposhtaCatalog\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Collect extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Perspective_NovaposhtaCatalog::system/config/collect.phtml';
    /**
     * @var string
     */
    protected $buttonComment;
    /**
     * @var string
     */
    protected $buttonName = 'Button';
    /**
     * @var string
     */
    protected $actionUrl = "admin/*/*";
    /**
     * @var string
     */
    protected $transtlitedName;

    /**
     * @param Context $context
     * @param string $actionUrl
     * @param string $buttonName
     * @param string $buttonComment
     * @param string $template
     * @param array $data
     */
    public function __construct(
        Context $context,
        $actionUrl,
        $buttonName,
        $buttonComment,
        $template,
        array $data = []
    ) {
        $this->actionUrl = $actionUrl ? $actionUrl : $this->actionUrl;
        $this->buttonName = $buttonName ? $buttonName : $this->buttonName;
        $this->buttonComment = $buttonComment;
        $this->_template = $template ? $template : $this->_template;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @return string
     */
    public function getButtonTemplate()
    {
        return $this->_template;
    }

    /**
     * @param string $template
     */
    public function setButtonTemplate(string $template)
    {
        $this->_template = $template;
    }

    /**
     * @return string
     */
    public function getTranstlitedName()
    {
        return $this->filterManager->translitUrl($this->getButtonName());
    }

    /**
     * @param $transtlitedName
     */
    public function setTranstlitedName($transtlitedName)
    {
        $this->transtlitedName = $transtlitedName;
    }

    /**
     * @return string
     */
    public function getButtonComment()
    {
        return $this->buttonComment;
    }

    /**
     * @param string $buttonComment
     */
    public function setButtonComment(string $buttonComment)
    {
        $this->buttonComment = $buttonComment;
    }

    /**
     * @return string
     */
    public function getButtonName()
    {
        return $this->buttonName;
    }

    /**
     * @param string $buttonName
     */
    public function setButtonName(string $buttonName)
    {
        $this->buttonName = $buttonName;
    }

    /**
     * @return string
     */
    public function getActionUrl()
    {
        return $this->actionUrl;
    }

    /**
     * @param string $actionUrl
     */
    public function setActionUrl(string $actionUrl)
    {
        $this->actionUrl = $actionUrl;
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for collect button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl($this->getActionUrl());
    }

    /**
     * Generate collect button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => $this->filterManager->translitUrl($this->getButtonName()),
                'label' => __($this->getButtonName()),
            ]
        );

        return $button->toHtml();
    }
}
