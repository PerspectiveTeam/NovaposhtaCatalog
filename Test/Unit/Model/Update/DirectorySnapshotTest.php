<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Perspective\NovaposhtaCatalog\Model\Area\Area;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DirectorySnapshotTest extends TestCase
{
    use SnapshotTestSupport;
    #[DataProvider('invalidLists')]
    public function testRejectsInvalidListsBeforePreparingAnyInvalidRecord(mixed $data): void
    {
        $service = new DirectorySnapshot($this->availableLock(), $this->policy());
        $this->expectException(LocalizedException::class);
        $service->prepare($data, fn ($row) => $this->model($row->Ref));
    }

    public static function invalidLists(): array
    {
        $row = (object)['Ref' => 'a', 'Description' => 'Name'];
        return [
            [null], [false], ['invalid'], [[]], [[null]], [[[]]], [[(object)[]]],
            [[(object)['Ref' => 'a']]], [[(object)['Ref' => ' ', 'Description' => 'Name']]],
            [[(object)['Ref' => str_repeat('a', 256), 'Description' => 'Name']]],
            [[(object)['Ref' => 'a', 'Description' => ' ']]],
            [[(object)['Ref' => 'a', 'Description' => []]]],
            [[(object)['Ref' => 'a', 'Description' => 'Name', 'DescriptionRu' => []]]],
            [[$row, $row]], [[$row, (object)[]]],
        ];
    }

    #[DataProvider('invalidLists')]
    public function testValidationRejectsTheSameInvalidListsWithoutModels(mixed $data): void
    {
        $service = new DirectorySnapshot($this->availableLock(), $this->policy());
        $this->expectException(LocalizedException::class);
        $service->validate($data);
    }

    public function testValidationIncludesEntityRulesAndSupportsExplicitEmptyScopes(): void
    {
        $service = new DirectorySnapshot($this->availableLock(), $this->policy());
        $rows = [(object)['Ref' => 'a', 'Description' => 'Name']];
        $validated = [];
        $service->validate($rows, function ($row) use (&$validated) { $validated[] = $row->Ref; });
        self::assertSame(['a'], $validated);
        $service->validate([], null, true);
        $this->expectExceptionMessage('entity validation failed');
        $service->validate($rows, function () { throw new \RuntimeException('entity validation failed'); });
    }

    #[DataProvider('invalidResponses')]
    public function testRejectsInvalidApiResponses(mixed $response): void
    {
        $service = new DirectorySnapshot($this->availableLock(), $this->policy());
        $this->expectException(LocalizedException::class);
        $service->getResponseData($response);
    }

    public static function invalidResponses(): array
    {
        return [[null], [[]], [(object)[]], [(object)['success' => false, 'data' => []]],
            [(object)['success' => 1, 'data' => []]], [(object)['success' => true]],
            [(object)['success' => true, 'data' => (object)[]]]];
    }

    public function testEmptyListIsAllowedOnlyExplicitlyForStreets(): void
    {
        $service = new DirectorySnapshot($this->availableLock(), $this->policy());
        self::assertSame([], $service->prepare([], fn () => null, true));
    }

    #[DataProvider('missingReferences')]
    public function testRejectsMissingKnownReferencesWithoutBeginningTransaction(array $refs): void
    {
        [$service, $resource, $connection] = $this->writer();
        $connection->expects(self::never())->method('beginTransaction');
        $resource->expects(self::never())->method('save');
        $models = [];
        foreach ($refs as $ref) { $models[$ref] = $this->model($ref); }
        $this->expectException(LocalizedException::class);
        $service->save($models, $resource, fn () => [$this->model('a', 11), $this->model('b', 12)], 'test');
    }

    public static function missingReferences(): array
    {
        return [[['a']], [['a', 'c']], [[]]];
    }

    public function testPreservesIdsAndOtherFieldsWhileUpdatingAndInserting(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $existing = $this->model('a', 11)->setData('description_ru', 'Existing translation');
        $incoming = $this->model('a')->setDescriptionUa('Updated');
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $saved = [];
        $resource->expects(self::exactly(2))->method('save')->willReturnCallback(function ($model) use (&$saved, $resource) {
            $saved[$model->getData('ref')] = $model->getData();
            return $resource;
        });
        $service->save(['a' => $incoming, 'b' => $this->model('b')], $resource, fn () => [$existing], 'test');
        self::assertSame(11, $saved['a']['id']);
        self::assertSame('Updated', $saved['a']['description_ua']);
        self::assertSame('Existing translation', $saved['a']['description_ru']);
        self::assertArrayNotHasKey('id', $saved['b']);
    }

    public function testInitialImport(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $connection->expects(self::once())->method('commit');
        $resource->expects(self::once())->method('save');
        $service->save(['a' => $this->model('a')], $resource, fn () => [], 'test');
    }

    public function testLegacyFlagCannotBypassTheMissingReferenceGuard(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $resource->expects(self::never())->method('save');
        $connection->expects(self::never())->method('beginTransaction');
        $this->expectException(LocalizedException::class);
        $service->save(['a' => $this->model('a')], $resource, fn () => [$this->model('a', 11), $this->model('b', 12)], 'test', false);
    }

    public function testSaveFailureRollsBackWholeSnapshotAndReleasesLock(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $connection->expects(self::once())->method('rollBack');
        $connection->expects(self::never())->method('commit');
        $calls = 0;
        $resource->expects(self::exactly(2))->method('save')->willReturnCallback(function () use (&$calls, $resource) {
            if (++$calls === 2) { throw new \RuntimeException('save failure'); }
            return $resource;
        });
        $this->expectExceptionMessage('save failure');
        $service->save(['a' => $this->model('a'), 'b' => $this->model('b')], $resource, fn () => [], 'test');
    }

    public function testBusyLockDoesNotReadOrWriteAndIsNotReleased(): void
    {
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())->method('lock')->with('test', 0)->willReturn(false);
        $lock->expects(self::never())->method('unlock');
        $resource = $this->createMock(AbstractDb::class);
        $resource->expects(self::never())->method('save');
        $this->expectException(LocalizedException::class);
        (new DirectorySnapshot($lock, $this->policy()))->save([], $resource, function () { self::fail('Collection read while lock is busy'); }, 'test');
    }

    private function writer(): array
    {
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())->method('lock')->with('test', 0)->willReturn(true);
        $lock->expects(self::once())->method('unlock')->with('test')->willReturn(true);
        $resource = $this->createMock(AbstractDb::class);
        $resource->expects(self::never())->method('delete');
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('delete');
        $resource->method('getConnection')->willReturn($connection);
        return [new DirectorySnapshot($lock, $this->policy()), $resource, $connection];
    }

    public function testFetchAndSaveReuseOneLockAndReleaseItAfterCommit(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $connection->expects(self::once())->method('commit');
        $resource->expects(self::once())->method('save');
        $service->withLock('test', function () use ($service, $resource) {
            $service->save(['a' => $this->model('a')], $resource, fn () => [], 'test');
        });
    }

    public function testNetworkExceptionReleasesLockWithoutStartingATransaction(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $connection->expects(self::never())->method('beginTransaction');
        $resource->expects(self::never())->method('save');
        $this->expectExceptionMessage('Network failure');
        $service->withLock('test', function () { throw new \RuntimeException('Network failure'); });
    }

    public function testDuplicateStoredReferencesCannotBeSilentlyCollapsed(): void
    {
        [$service, $resource, $connection] = $this->writer();
        $connection->expects(self::never())->method('beginTransaction');
        $resource->expects(self::never())->method('save');
        $this->expectException(LocalizedException::class);
        $service->save(['a' => $this->model('a')], $resource,
            fn () => [$this->model('a', 11), $this->model('a', 12)], 'test');
    }

    public function testScopedSavePreservesIdentityWhenAReferenceMovesToAnotherCity(): void
    {
        $stored = $this->model('a', 11)->setData('city_ref', 'old-city')->setData('description_ru', 'Preserved');
        $incoming = $this->model('a')->setData('city_ref', 'new-city');
        $resource = $this->createMock(AbstractDb::class);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $resource->method('getConnection')->willReturn($connection);
        $resource->expects(self::once())->method('save')->willReturnCallback(function ($model) use ($resource) {
            self::assertSame(11, $model->getId());
            self::assertSame('new-city', $model->getData('city_ref'));
            self::assertSame('Preserved', $model->getData('description_ru'));
            self::assertSame(1, $model->getData('is_active'));
            return $resource;
        });
        $known = $this->scopedCollection('ref', ['in' => ['a']], [$stored]);
        $local = $this->scopedCollection('city_ref', 'new-city', []);
        $collections = [$known, $local];
        $service = new DirectorySnapshot($this->availableLock(), $this->policy());
        $service->saveScoped(['a' => $incoming], $resource,
            function () use (&$collections) { return array_shift($collections); }, 'new-city', 'np_streets_');
    }

    public function testScopedDuplicateGlobalReferencesAbortBeforeTransaction(): void
    {
        $resource = $this->createMock(AbstractDb::class);
        $resource->expects(self::never())->method('getConnection');
        $resource->expects(self::never())->method('save');
        $known = $this->scopedCollection('ref', ['in' => ['a']], [$this->model('a', 11), $this->model('a', 12)]);
        $this->expectException(LocalizedException::class);
        (new DirectorySnapshot($this->availableLock(), $this->policy()))->saveScoped(
            ['a' => $this->model('a')], $resource, fn () => $known, 'city', 'np_streets_'
        );
    }

    public function testBusyCrossCityWriteLockPreventsReadsAndReleasesOnlyOwnedLock(): void
    {
        $cityLock = 'np_streets_' . sha1('city');
        $lock = $this->createMock(LockManagerInterface::class);
        $names = [];
        $lock->expects(self::exactly(2))->method('lock')->willReturnCallback(function ($name, $timeout) use (&$names, $cityLock) {
            self::assertSame(0, $timeout);
            $names[] = $name;
            return $name === $cityLock;
        });
        $lock->expects(self::once())->method('unlock')->with($cityLock)->willReturn(true);
        $resource = $this->createMock(AbstractDb::class);
        $resource->expects(self::never())->method('save');
        try {
            (new DirectorySnapshot($lock, $this->policy()))->saveScoped(
                [], $resource, function () { self::fail('Read while write lock busy'); }, 'city', 'np_streets_'
            );
            self::fail('Busy lock was accepted');
        } catch (LocalizedException) {
            self::assertSame([$cityLock, 'np_streets_write'], $names);
        }
    }

    private function scopedCollection(string $field, mixed $condition, array $models): object
    {
        $collection = $this->createMock(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\Collection::class);
        $collection->expects(self::once())->method('addFieldToFilter')->with($field, $condition)->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($models));
        return $collection;
    }

    private function model(string $ref, ?int $id = null): AbstractModel
    {
        $model = (new \ReflectionClass(Area::class))->newInstanceWithoutConstructor()->setRef($ref);
        if ($id !== null) { $model->setId($id); }
        return $model;
    }
}
