<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class SyncSafety extends Value
{
    public function beforeSave()
    {
        $field = basename($this->getPath());
        [$min, $max] = match ($field) {
            'confirmations' => [2, 100],
            'confirmation_hours' => [1, 8760],
            'max_missing_percent' => [0, 100],
        };
        if (filter_var($this->getValue(), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $min, 'max_range' => $max]]) === false
        ) {
            throw new LocalizedException(__('Enter an integer between %1 and %2 for %3.', $min, $max, $field));
        }
        return parent::beforeSave();
    }
}
