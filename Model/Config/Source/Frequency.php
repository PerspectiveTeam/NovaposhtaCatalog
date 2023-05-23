<?php

namespace Perspective\NovaposhtaCatalog\Model\Config\Source;

use Perspective\NovaposhtaCatalog\Api\Data\ConfigSettingsInterface;

class Frequency implements \Magento\Framework\Option\ArrayInterface, ConfigSettingsInterface
{
    /**
     * @var array
     */
    protected static $_options;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = [
                ['label' => __('Every day'), 'value' => self::CRON_EVERY],
                ['label' => __('7 days'), 'value' => self::CRON_SEVEN],
                ['label' => __('15 days'), 'value' => self::CRON_FIFTEEN],
                ['label' => __('30 days'), 'value' => self::CRON_THIRTY],
            ];
        }
        return self::$_options;
    }
}
