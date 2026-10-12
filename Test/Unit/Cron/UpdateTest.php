<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Perspective\NovaposhtaCatalog\Cron\Sync\SyncAll;
use Perspective\NovaposhtaCatalog\Cron\Update;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{
    #[DataProvider('scheduleFlags')]
    public function testOnlyRunsWhenScheduleIsEnabled(?string $value, int $expectedCalls): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('novaposhta_catalog/schedule/enabled', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->willReturn((bool)$value);

        $syncAll = $this->createMock(SyncAll::class);
        $syncAll->expects(self::exactly($expectedCalls))->method('execute');

        (new Update($syncAll, $scopeConfig))->execute();
    }

    public static function scheduleFlags(): array
    {
        return [
            'disabled' => ['0', 0],
            'missing setting' => [null, 0],
            'enabled' => ['1', 1],
        ];
    }
}
