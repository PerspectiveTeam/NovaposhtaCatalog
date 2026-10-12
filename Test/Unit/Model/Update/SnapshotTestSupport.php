<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Model\Update\SyncPolicy;

trait SnapshotTestSupport
{
    private function policy(): SyncPolicy
    {
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(1760000000);
        return new SyncPolicy($this->createStub(ScopeConfigInterface::class), $clock);
    }

    private function availableLock(): LockManagerInterface
    {
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        return $lock;
    }
}
