<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseStatuses;

class SyncPolicy
{
    public function __construct(private ScopeConfigInterface $config, private DateTime $clock)
    {
    }

    public function getSetting(string $name): int
    {
        [$default, $min, $max] = match ($name) {
            'confirmations' => [2, 2, 100],
            'confirmation_hours' => [24, 1, 8760],
            'max_missing_percent' => [20, 0, 100],
        };
        $value = $this->config->getValue('novaposhta_catalog/sync_safety/' . $name);
        if ($value === null) {
            return $default;
        }
        $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($valid === false) {
            throw new LocalizedException(__('Invalid Nova Poshta sync safety setting: %1.', $name));
        }
        return $valid;
    }

    /** Reject the whole snapshot before writing, including confirmation counters. */
    public function assertAcceptable(array $existing, array $incoming, string $scope): void
    {
        $active = array_filter($existing, fn ($model) => (int)($model->getData('is_active') ?? 1) === 1);
        $missing = count(array_diff_key($active, $incoming));
        if ($active && $missing * 100 > count($active) * $this->getSetting('max_missing_percent')) {
            throw new LocalizedException(__(
                'Nova Poshta sync %1 rejected: %2 of %3 active references are missing. Review the snapshot before changing the safety threshold.',
                $scope, $missing, count($active)
            ));
        }
    }

    public function markPresent(AbstractModel $model): void
    {
        $status = $model->getData('warehouse_status');
        $model->addData([
            'is_active' => $status === null || $status === WarehouseStatuses::WORKING ? 1 : 0,
            'last_seen_at' => gmdate('Y-m-d H:i:s', $this->clock->gmtTimestamp()),
            'missing_successful_syncs' => 0,
            'last_missing_at' => null,
        ]);
    }

    /** Returns whether a new, sufficiently separated confirmation needs saving. */
    public function markMissing(AbstractModel $model): bool
    {
        if ((int)($model->getData('is_active') ?? 1) !== 1) {
            return false;
        }
        $now = $this->clock->gmtTimestamp();
        $last = $model->getData('last_missing_at');
        if ($last !== null && $now - strtotime($last . ' UTC') < $this->getSetting('confirmation_hours') * 3600) {
            return false;
        }
        $count = (int)$model->getData('missing_successful_syncs') + 1;
        $model->addData([
            'missing_successful_syncs' => $count,
            'last_missing_at' => gmdate('Y-m-d H:i:s', $now),
            'is_active' => $count >= $this->getSetting('confirmations') ? 0 : 1,
        ]);
        return true;
    }
}
