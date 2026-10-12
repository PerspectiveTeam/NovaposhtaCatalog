<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Perspective\NovaposhtaCatalog\Api\Data\ConfigSettingsInterface;
use Perspective\NovaposhtaCatalog\Model\Config\Backend\Cron;
use Perspective\NovaposhtaCatalog\Model\Config\Source\Frequency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronTest extends TestCase
{
    public function testDisablingWritesEmptyExpressionWithoutTimeOrFrequency(): void
    {
        $backend = $this->createBackend('');
        $backend->setValue('0');

        self::assertSame($backend, $backend->afterSave());
    }

    #[DataProvider('frequencies')]
    public function testEnablingUsesSelectedTimeAndFrequency(string $frequency, string $days): void
    {
        $backend = $this->createBackend('35 4 ' . $days . ' * *');
        $backend->setValue('1');
        $backend->setData('groups', [
            'schedule' => [
                'fields' => [
                    'time' => ['value' => ['04', '35', '00']],
                    'frequency' => ['value' => $frequency],
                ],
            ],
        ]);

        self::assertSame($backend, $backend->afterSave());
    }

    public static function frequencies(): array
    {
        return [
            'daily' => [Frequency::CRON_EVERY, '*/1'],
            'seven days' => [Frequency::CRON_SEVEN, '*/7'],
            'fifteen days' => [Frequency::CRON_FIFTEEN, '*/15'],
            'thirty days' => [Frequency::CRON_THIRTY, '*/30'],
            'fallback' => ['unknown', '*/7'],
        ];
    }

    private function createBackend(string $expectedExpression): Cron
    {
        $expectedValues = [
            ConfigSettingsInterface::CRON_STRING_PATH => $expectedExpression,
            ConfigSettingsInterface::CRON_MODEL_PATH => '',
        ];
        $writer = $this->createMock(WriterInterface::class);
        $writer->expects(self::exactly(2))->method('save')
            ->willReturnCallback(function ($path, $value, $scope, $scopeId) use (&$expectedValues): void {
                self::assertArrayHasKey($path, $expectedValues);
                self::assertSame($expectedValues[$path], $value);
                self::assertSame(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scope);
                self::assertSame(0, $scopeId);
                unset($expectedValues[$path]);
            });

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $context->method('getCacheManager')->willReturn($this->createStub(CacheInterface::class));

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($expectedExpression === '' ? '0' : '1');
        $cacheTypeList = $this->createMock(TypeListInterface::class);
        $cacheTypeList->expects(self::once())->method('invalidate')->with('config');

        $backend = new Cron(
            $context,
            $this->createStub(Registry::class),
            $scopeConfig,
            $cacheTypeList,
            $writer
        );
        $backend->setPath('novaposhta_catalog/schedule/enabled');
        return $backend;
    }
}
