<?php


namespace Perspective\NovaposhtaCatalog\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    const XML_PATH_MYMODULE = 'novaposhta_catalog/';

    /**
     * @param null $storeId
     * @return string '1' - if enabled, '0' otherwise
     */
    public function isEnabled($storeId = null)
    {
        return $this->getCatalogConfig('active', $storeId);
    }

    /**
     * @param $code
     * @param null $storeId
     * @return mixed
     */
    public function getCatalogConfig($code, $storeId = null)
    {

        return $this->getConfigValue(self::XML_PATH_MYMODULE . 'catalog/' . $code, $storeId);
    }

    /**
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    protected function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return api key from system.xml or so
     * @param null $storeId
     * @return mixed
     */
    public function getApiKey($storeId = null)
    {
        return $this->getCatalogConfig('apikey', $storeId);
    }
}
