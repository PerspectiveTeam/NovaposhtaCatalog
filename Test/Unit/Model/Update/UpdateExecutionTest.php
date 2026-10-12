<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\Update\City;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;
use Perspective\NovaposhtaCatalog\Model\Update\Package;
use Perspective\NovaposhtaCatalog\Model\Update\WarehouseType;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypes;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseTypesFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UpdateExecutionTest extends TestCase
{
    #[DataProvider('globalUpdates')]
    public function testGlobalUpdateReportsErrorsAndOnlyDatesSuccessfulWrites(string $class, string $case): void
    {
        $service = $this->getMockBuilder($class)->disableOriginalConstructor()
            ->onlyMethods(['getDataFromEndpoint', 'setDataToDB'])->getMock();
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $this->inject($service, $class, 'configHelper', $config);
        $this->inject($service, $class, 'snapshot', new DirectorySnapshot($this->createStub(LockManagerInterface::class)));
        if ($class === City::class) {
            $this->inject($service, $class, 'logger', $this->createStub(LoggerInterface::class));
        }
        $date = $this->createMock(CronSyncDateLastUpdate::class);
        $date->expects($case === 'success' ? self::once() : self::never())->method('updateSyncDate');
        $this->inject($service, $class, 'cronSyncDateLastUpdate', $date);
        $response = (object)['success' => true, 'data' => [(object)['Ref' => 'a', 'Description' => 'Name']]];
        if ($case === 'transport_failure') {
            $service->expects(self::once())->method('getDataFromEndpoint')->willThrowException(new \RuntimeException('transport failure'));
            $service->expects(self::never())->method('setDataToDB');
        } else {
            if ($case === 'api_failure') { $response->success = false; }
            $service->expects(self::once())->method('getDataFromEndpoint')->willReturn($response);
            $save = $service->expects($case === 'api_failure' ? self::never() : self::once())->method('setDataToDB');
            if ($case === 'save_failure') { $save->willThrowException(new \RuntimeException('save failure')); }
        }
        self::assertSame($case !== 'success', $service->execute()['error']);
    }

    public static function globalUpdates(): array
    {
        $cases = [];
        foreach ([City::class, Package::class] as $class) {
            foreach (['success', 'transport_failure', 'api_failure', 'save_failure'] as $case) {
                $cases[] = [$class, $case];
            }
        }
        return $cases;
    }

    #[DataProvider('translationUpdates')]
    public function testBothWarehouseTypeLanguagesAreValidatedBeforeSingleWrite(string $case): void
    {
        $service = $this->getMockBuilder(WarehouseType::class)->disableOriginalConstructor()
            ->onlyMethods(['getDataFromEndpoint'])->getMock();
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $this->inject($service, WarehouseType::class, 'configHelper', $config);
        $factory = $this->createStub(WarehouseTypesFactory::class);
        $factory->method('create')->willReturnCallback(fn () => (new \ReflectionClass(WarehouseTypes::class))->newInstanceWithoutConstructor());
        $this->inject($service, WarehouseType::class, 'warehouseTypesFactory', $factory);
        $this->inject($service, WarehouseType::class, 'warehouseTypesResourceModel', $this->createStub(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes::class));
        $this->inject($service, WarehouseType::class, 'warehouseTypesResourceModelCollectionFactory', $this->createStub(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Warehouse\WarehouseTypes\CollectionFactory::class));
        $realSnapshot = new DirectorySnapshot($this->createStub(LockManagerInterface::class));
        $snapshot = $this->createMock(DirectorySnapshot::class);
        $snapshot->method('getResponseData')->willReturnCallback($realSnapshot->getResponseData(...));
        $snapshot->method('prepare')->willReturnCallback($realSnapshot->prepare(...));
        $shouldSave = in_array($case, ['success', 'save_failure'], true);
        $save = $snapshot->expects($shouldSave ? self::once() : self::never())->method('save');
        if ($case === 'save_failure') {
            $save->willThrowException(new \RuntimeException('save failure'));
        } elseif ($case === 'success') {
            $save->willReturnCallback(function (array $models) {
                self::assertCount(2, $models);
                self::assertSame('UA a', $models['a']->getDescriptionUa());
                self::assertSame('RU a', $models['a']->getDescriptionRu());
                self::assertSame('UA b', $models['b']->getDescriptionUa());
                self::assertSame('RU b', $models['b']->getDescriptionRu());
            });
        }
        $this->inject($service, WarehouseType::class, 'snapshot', $snapshot);
        $date = $this->createMock(CronSyncDateLastUpdate::class);
        $date->expects($case === 'success' ? self::once() : self::never())->method('updateSyncDate');
        $this->inject($service, WarehouseType::class, 'cronSyncDateLastUpdate', $date);
        $service->expects(self::exactly(2))->method('getDataFromEndpoint')->willReturnCallback(function ($lang) use ($case) {
            $refs = $lang === 'ru' && $case === 'missing_translation' ? ['a'] : ['a', 'b'];
            $data = array_map(fn ($ref) => (object)['Ref' => $ref, 'Description' => strtoupper($lang) . ' ' . $ref], $refs);
            return (object)['success' => !($lang === 'ru' && $case === 'api_failure'),
                'data' => $lang === 'ru' && $case === 'empty_translation' ? [] : $data];
        });
        self::assertSame($case !== 'success', $service->execute()['error']);
    }

    public static function translationUpdates(): array
    {
        return [['success'], ['api_failure'], ['missing_translation'], ['empty_translation'], ['save_failure']];
    }

    private function inject(object $service, string $class, string $property, mixed $value): void
    {
        (new \ReflectionProperty($class, $property))->setValue($service, $value);
    }
}
