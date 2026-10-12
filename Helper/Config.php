<?php


namespace Perspective\NovaposhtaCatalog\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    const XML_PATH_MYMODULE = 'novaposhta_catalog/';
    const XML_PATH_DIRECTORY_MAP = 'novaposhta_catalog/directory_map/sync';

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

    /**
     * Returns flat map of [regionCode => areaRef] from admin directory mapping config.
     *
     * @param null $storeId
     * @return array<string, string>
     */
    public function getDirectoryMap($storeId = null): array
    {
        $raw = $this->getConfigValue(self::XML_PATH_DIRECTORY_MAP, $storeId);

        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $item) {
            if (empty($item['magento']) || empty($item['novaposhta'])) {
                continue;
            }
            $map[(string)$item['magento']] = (string)$item['novaposhta'];
        }

        return $map;
    }
}
