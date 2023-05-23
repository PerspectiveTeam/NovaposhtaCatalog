<?php
namespace Perspective\NovaposhtaCatalog\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Perspective\NovaposhtaCatalog\Controller\Adminhtml\Sync\Schedule\NewSchedule;

class ScheduleAfterSchema implements DataPatchInterface
{

    private NewSchedule $newSchedule;

    public function __construct(
        NewSchedule $newSchedule
    ) {
        $this->newSchedule = $newSchedule;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->newSchedule->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
