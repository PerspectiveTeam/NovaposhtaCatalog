<?php


namespace Perspective\NovaposhtaCatalog\Helper;

use Magento\Framework\App\Helper\Context;
use Perspective\NovaposhtaCatalog\Api\Data\ConfigSettingsInterface;

class CronSyncDateLastUpdate extends \Magento\Framework\App\Helper\AbstractHelper implements ConfigSettingsInterface
{

    /**
     * @var \Magento\Framework\App\Config\Storage\WriterInterface
     */
    protected $configWriter;

    /** @var \Magento\Framework\Stdlib\DateTime\DateTime */
    protected $date;

    public function __construct(
        Context $context,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        parent::__construct($context);
        $this->date = $date;
        $this->configWriter = $configWriter;
    }

    public function updateSyncDate($xmlPath)
    {
        $date = $this->date->gmtDate();
        $this->configWriter->save($xmlPath, $date);
    }
}
