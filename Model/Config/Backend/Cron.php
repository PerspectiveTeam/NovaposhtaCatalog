<?php


namespace Perspective\NovaposhtaCatalog\Model\Config\Backend;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Perspective\NovaposhtaCatalog\Api\Data\ConfigSettingsInterface;

class Cron extends \Magento\Framework\App\Config\Value implements ConfigSettingsInterface
{
    private WriterInterface $configWriter;

    /**
     * @var string
     */
    protected $_runModelPath = '';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param string $runModelPath
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Cron settings after save
     *
     * @return \Magento\Framework\App\Config\Value
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterSave()
    {
        $enabled = (int)$this->getValue();

        if ($enabled) {
            $time = $this->getData(self::XML_PATH_SCHEDULE_TIME);
            $frequency = $this->getData(self::XML_PATH_SCHEDULE_FREQUENCY);

            switch ($frequency) {
                case \Perspective\NovaposhtaCatalog\Model\Config\Source\Frequency::CRON_SEVEN:
                    $days = '*/7';
                    break;
                case \Perspective\NovaposhtaCatalog\Model\Config\Source\Frequency::CRON_FIFTEEN:
                    $days = '*/15';
                    break;
                case \Perspective\NovaposhtaCatalog\Model\Config\Source\Frequency::CRON_THIRTY:
                    $days = '*/30';
                    break;
                case \Perspective\NovaposhtaCatalog\Model\Config\Source\Frequency::CRON_EVERY:
                    $days = '*/1';
                    break;
                default:
                    $days = '*/7';
            }

            // @codingStandardsIgnoreStart
            $cronExprArray = [
                intval($time[1]),                                 # Minute
                intval($time[0]),                                 # Hour
                $days,                                            # Day of the Month
                '*',                                              # Month of the Year
                '*',                                              # Day of the Week
            ];
            // @codingStandardsIgnoreEnd
            $cronExprString = join(' ', $cronExprArray);
        } else {
            $cronExprString = '';
        }

        try {
            $this->configWriter->save(
                self::CRON_STRING_PATH,
                $cronExprString,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );

            $this->configWriter->save(
                self::CRON_MODEL_PATH,
                $this->_runModelPath,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
            $this->cacheTypeList->invalidate(Config::TYPE_IDENTIFIER);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t save the Cron expression.'));
        }
        return parent::afterSave();
    }
}
