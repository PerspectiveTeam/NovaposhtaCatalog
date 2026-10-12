<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\Area\Area as AreaModel;
use Perspective\NovaposhtaCatalog\Model\Area\AreaFactory;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area as AreaResource;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\Collection;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Area\Area\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\Update\Area;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AreaTest extends TestCase
{
    use SnapshotTestSupport;
    private const LOCK_NAME = 'perspective_novaposhta_areas_sync';

    private AreaResource&MockObject $resource;
    private AdapterInterface&MockObject $connection;
    private LockManagerInterface&MockObject $lockManager;
    private CollectionFactory $collectionFactory;
    private AreaFactory $areaFactory;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(AreaResource::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->connection->expects(self::never())->method('delete');
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->areaFactory = $this->createStub(AreaFactory::class);
        $this->areaFactory->method('create')->willReturnCallback(fn () => $this->newModel());
        $this->collectionFactory = $this->createStub(CollectionFactory::class);
    }

    #[DataProvider('incompleteSnapshots')]
    public function testRejectsSnapshotsMissingKnownReferences(array $refs): void
    {
        $service = $this->createService(['area-a' => 11, 'area-b' => 12]);
        $this->expectLock();
        $this->resource->expects(self::never())->method('save');
        $this->connection->expects(self::never())->method('beginTransaction');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('active references are missing');
        $service->setDataToDB(array_map(self::row(...), $refs));
    }

    public static function incompleteSnapshots(): array
    {
        return [
            'one of two areas' => [['area-a']],
            'same count but a known area is missing' => [['area-a', 'area-c']],
        ];
    }

    #[DataProvider('invalidSnapshots')]
    public function testRejectsInvalidSnapshotsBeforeDatabaseAccess($snapshot): void
    {
        $service = $this->createService();
        $this->lockManager->expects(self::never())->method('lock');
        $this->resource->expects(self::never())->method('save');
        $this->connection->expects(self::never())->method('beginTransaction');

        $this->expectException(LocalizedException::class);
        $service->setDataToDB($snapshot);
    }

    public static function invalidSnapshots(): array
    {
        return [
            'empty' => [[]],
            'null' => [null],
            'non iterable' => ['invalid'],
            'missing reference' => [[(object)['Description' => 'Name']]],
            'missing name' => [[(object)['Ref' => 'area-a']]],
            'blank name' => [[(object)['Ref' => 'area-a', 'Description' => ' ']]],
            'blank reference' => [[self::row(' ')]],
            'oversized reference' => [[self::row(str_repeat('a', 256))]],
            'array instead of object' => [[['Ref' => 'area-a', 'Description' => 'Name']]],
            'invalid center' => [[(object)['Ref' => 'area-a', 'Description' => 'Name', 'AreasCenter' => []]]],
            'invalid translated name' => [[(object)['Ref' => 'area-a', 'Description' => 'Name', 'DescriptionRu' => []]]],
            'valid row followed by malformed row' => [[self::row('area-a'), (object)[]]],
            'duplicate reference' => [[self::row('area-a'), self::row('area-a')]],
        ];
    }

    #[DataProvider('existingAreas')]
    public function testSavesCompleteSnapshotAndNewAreasKeepingExistingIds(array $existing): void
    {
        $service = $this->createService($existing);
        $this->expectLock();
        $this->connection->expects(self::once())->method('beginTransaction');
        $this->connection->expects(self::once())->method('commit');
        $this->connection->expects(self::never())->method('rollBack');
        $saved = [];
        $this->resource->expects(self::exactly(3))->method('save')
            ->willReturnCallback(function (AreaModel $model) use (&$saved) {
                $saved[$model->getRef()] = [$model->getId(), $model->getDescriptionUa()];
                return $this->resource;
            });

        $service->setDataToDB([self::row('area-a'), self::row('area-b'), self::row('area-c')]);

        self::assertSame([
            'area-a' => [$existing['area-a'] ?? null, 'Name area-a'],
            'area-b' => [$existing['area-b'] ?? null, 'Name area-b'],
            'area-c' => [null, 'Name area-c'],
        ], $saved);
    }

    public static function existingAreas(): array
    {
        return ['initial import' => [[]], 'update and insert' => [['area-a' => 11, 'area-b' => 12]]];
    }

    public function testRollsBackAndReleasesLockWhenSaveFails(): void
    {
        $service = $this->createService(['area-a' => 11]);
        $this->expectLock();
        $this->connection->expects(self::once())->method('beginTransaction');
        $this->connection->expects(self::never())->method('commit');
        $this->connection->expects(self::once())->method('rollBack');
        $calls = 0;
        $this->resource->expects(self::exactly(2))->method('save')
            ->willReturnCallback(function () use (&$calls) {
                if (++$calls === 2) {
                    throw new \RuntimeException('Save failed');
                }
                return $this->resource;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Save failed');
        $service->setDataToDB([self::row('area-a'), self::row('area-b')]);
    }

    public function testBusyLockPreventsWritesAndDoesNotReleaseAnotherRunsLock(): void
    {
        $service = $this->createService();
        $this->lockManager->expects(self::once())->method('lock')->with(self::LOCK_NAME, 0)->willReturn(false);
        $this->lockManager->expects(self::never())->method('unlock');
        $this->resource->expects(self::never())->method('save');
        $this->connection->expects(self::never())->method('beginTransaction');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already in progress');
        $service->setDataToDB([self::row('area-a')]);
    }

    public function testExecuteReportsIncompleteSnapshotWithoutUpdatingSuccessDate(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $date = $this->createMock(CronSyncDateLastUpdate::class);
        $date->expects(self::never())->method('updateSyncDate');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->setExistingAreas(['area-a' => 11, 'area-b' => 12]);
        $service = $this->getMockBuilder(Area::class)
            ->setConstructorArgs($this->dependencies($config, $date, $logger))
            ->onlyMethods(['getDataFromEndpoint'])->getMock();
        $service->expects(self::once())->method('getDataFromEndpoint')
            ->willReturn((object)['success' => true, 'data' => [self::row('area-a')]]);
        $this->expectLock();
        $this->resource->expects(self::never())->method('save');

        $result = $service->execute();

        self::assertTrue($result['error']);
        self::assertStringContainsString('active references are missing', $result['message']);
    }

    private function createService(array $existing = []): Area
    {
        $this->setExistingAreas($existing);
        return new Area(...$this->dependencies(
            $this->createStub(Config::class),
            $this->createStub(CronSyncDateLastUpdate::class),
            $this->createStub(LoggerInterface::class)
        ));
    }

    private function dependencies(Config $config, CronSyncDateLastUpdate $date, LoggerInterface $logger): array
    {
        return [
            $config, $date, $this->createStub(SerializerInterface::class), $this->areaFactory,
            $this->resource, $this->collectionFactory, $this->createStub(Post::class), $logger, new \Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot($this->lockManager, $this->policy()),
        ];
    }

    private function setExistingAreas(array $existing): void
    {
        $items = [];
        foreach ($existing as $ref => $id) {
            $items[] = $this->newModel()->setRef($ref)->setId($id);
        }
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    private function expectLock(): void
    {
        $this->lockManager->expects(self::once())->method('lock')->with(self::LOCK_NAME, 0)->willReturn(true);
        $this->lockManager->expects(self::once())->method('unlock')->with(self::LOCK_NAME)->willReturn(true);
    }

    private function newModel(): AreaModel
    {
        return (new \ReflectionClass(AreaModel::class))->newInstanceWithoutConstructor();
    }

    private static function row(string $ref): \stdClass
    {
        return (object)['Ref' => $ref, 'Description' => 'Name ' . $ref];
    }
}
