<?php


namespace Perspective\NovaposhtaCatalog\Model\Warehouse;

use Magento\Framework\Data\OptionSourceInterface;

class WarehouseStatuses implements OptionSourceInterface
{
    const WORKING = 'Working';
    const IN_PROCESS_OPENING = 'InProcessOpening';
    const NOT_WORKING = 'NotWorking';
    const NOT_WORKING_TEMPORARY = 'NotWorkingTemporary';

    /**
     * Get options for select
     *
     * @return array
     */

    public function toOptionArray()
    {
        return [
            ['value' => self::WORKING, 'label' => __('Working')],
            ['value' => self::IN_PROCESS_OPENING, 'label' => __('In Process Opening')],
            ['value' => self::NOT_WORKING, 'label' => __('Not Working')],
            ['value' => self::NOT_WORKING_TEMPORARY, 'label' => __('Not Working Temporary')],
        ];
    }
}
