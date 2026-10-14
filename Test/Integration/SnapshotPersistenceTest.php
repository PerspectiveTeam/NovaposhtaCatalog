<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Exception\LocalizedException;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street as StreetResource;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Street\Street\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\Street\Street;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;
use Perspective\NovaposhtaCatalog\Model\Update\SyncPolicy;

class SnapshotPersistenceTest extends TemporaryCatalogTestCase
{
    public function testRuntimeDiWrapsAllSixDirectoryExecutionsWithMetrics(): void
    {
        $logged = [];
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::exactly(6))->method('info')->willReturnCallback(function ($message, $context) use (&$logged) {
            $logged[] = [$message, $context];
        });
        $metrics = $this->objects->get(\Perspective\NovaposhtaCatalog\Service\SyncMetrics::class);
        (new \ReflectionProperty($metrics, 'logger'))->setValue($metrics, $logger);
        $config = $this->createStub(\Perspective\NovaposhtaCatalog\Helper\Config::class);
        $config->method('isEnabled')->willReturn(false);
        foreach (['Area', 'City', 'Package', 'WarehouseType', 'Warehouse', 'Street'] as $short) {
            $class = 'Perspective\\NovaposhtaCatalog\\Model\\Update\\' . $short;
            $updater = $this->objects->create($class, ['configHelper' => $config]);
            self::assertTrue($updater->execute()['error']);
        }
        self::assertSame(['areas', 'cities', 'packages', 'warehouse_types', 'warehouses', 'streets'],
            array_column(array_column($logged, 1), 'entity'));
        foreach ($logged as [$message, $context]) {
            self::assertSame('Nova Poshta synchronization metrics.', $message);
            self::assertTrue($context['error']);
            self::assertSame(0, $context['requests']);
        }
    }

    public function testRealPersistencePreservesMovedIdentityAndConfirmsMissingReferences(): void
    {
        $time = 1760000000;
        $snapshot = $this->snapshot($time);
        $resource = $this->objects->get(StreetResource::class);
        $factory = $this->objects->get(CollectionFactory::class);
        $save = function ($models, $city) use ($snapshot, $resource, $factory) {
            $snapshot->saveScoped($models, $resource, fn () => $factory->create(), $city, 'np_streets_');
        };
        $all = [];
        for ($i = 1; $i <= 5; ++$i) {
            $all['ref-' . $i] = $this->street('ref-' . $i, 'old-city');
        }
        $save($all, 'old-city');
        $originalId = $this->rows('street')[0]['id'];
        $save(['ref-1' => $this->street('ref-1', 'new-city')], 'new-city');
        self::assertSame($originalId, $this->rows('street')[0]['id']);
        self::assertSame('new-city', $this->rows('street')[0]['city_ref']);

        // Exactly 20% missing is allowed; repeated runs before 24h do not confirm it again.
        $missing = array_slice($all, 0, 4, true);
        foreach ($missing as $model) { $model->setCityRef('new-city'); }
        $save($missing, 'new-city');
        $save(['ref-5' => $this->street('ref-5', 'new-city')] + $missing, 'new-city');
        $save($missing, 'new-city');
        $save($missing, 'new-city');
        $last = $this->rows('street')[4];
        self::assertSame(1, (int)$last['missing_successful_syncs']);
        self::assertSame(1, (int)$last['is_active']);
        $time += 24 * 3600;
        $save($missing, 'new-city');
        $last = $this->rows('street')[4];
        self::assertSame(2, (int)$last['missing_successful_syncs']);
        self::assertSame(0, (int)$last['is_active']);
        $save(['ref-5' => $this->street('ref-5', 'new-city')] + $missing, 'new-city');
        $last = $this->rows('street')[4];
        self::assertSame(0, (int)$last['missing_successful_syncs']);
        self::assertNull($last['last_missing_at']);
        self::assertSame(1, (int)$last['is_active']);
        $events = $this->events->counts;
        self::assertGreaterThan(0, $events['novaposhta_street_model_save_before'] ?? 0);
        self::assertGreaterThan(0, $events['novaposhta_street_model_save_after'] ?? 0);
        self::assertGreaterThan(0, $events['novaposhta_street_model_save_commit_after'] ?? 0);
    }

    public function testRejectedAndDuplicateSnapshotsDoNotChangePersistedRows(): void
    {
        $time = 1760000000;
        $snapshot = $this->snapshot($time);
        $resource = $this->objects->get(StreetResource::class);
        $factory = $this->objects->get(CollectionFactory::class);
        $save = fn ($models) => $snapshot->saveScoped($models, $resource, fn () => $factory->create(), 'city', 'np_streets_');
        $save(['a' => $this->street('a', 'city'), 'b' => $this->street('b', 'city')]);
        $before = $this->rows('street');
        foreach ([[], ['a' => $this->street('a', 'city')->setDescription('Changed')]] as $models) {
            try {
                $save($models);
                self::fail('Rejected snapshot saved');
            } catch (LocalizedException $e) {
                self::assertStringContainsString('active references are missing', $e->getMessage());
            }
            self::assertSame($before, $this->rows('street'));
        }
        $duplicate = $before[0];
        unset($duplicate['id']);
        $this->db->insert($this->tables['street'], $duplicate);
        $before = $this->rows('street');
        try {
            $save(['a' => $this->street('a', 'city'), 'b' => $this->street('b', 'city')]);
            self::fail('Duplicate local Ref accepted');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Duplicate local', $e->getMessage());
        }
        self::assertSame($before, $this->rows('street'));
        self::assertSame(0, $this->db->getTransactionLevel());
    }

    public function testRealTransactionRollsBackTheWholeCityOnSaveFailure(): void
    {
        $time = 1760000000;
        $snapshot = $this->snapshot($time);
        $resource = $this->objects->get(StreetResource::class);
        $factory = $this->objects->get(CollectionFactory::class);
        $save = fn ($models, $writer) => $snapshot->saveScoped(
            $models, $writer, fn () => $factory->create(), 'city', 'np_streets_'
        );
        $save(['a' => $this->street('a', 'city'), 'b' => $this->street('b', 'city')], $resource);
        $before = $this->rows('street');
        $writer = $this->getMockBuilder(StreetResource::class)->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'save'])->getMock();
        $writer->method('getConnection')->willReturn($this->db);
        $calls = 0;
        $writer->expects(self::exactly(2))->method('save')->willReturnCallback(function ($model) use (&$calls, $resource) {
            if (++$calls === 2) { throw new \RuntimeException('fixture write failure'); }
            return $resource->save($model);
        });
        try {
            $save(['a' => $this->street('a', 'city')->setDescription('Changed'), 'b' => $this->street('b', 'city')], $writer);
            self::fail('Failed save accepted');
        } catch (\RuntimeException $e) {
            self::assertSame('fixture write failure', $e->getMessage());
        }
        self::assertSame($before, $this->rows('street'));
        self::assertSame(0, $this->db->getTransactionLevel());
    }

    public function testAddressSelectionsRespectAvailabilityAndHistoryStillLoadsByReference(): void
    {
        $this->db->insert($this->tables['areas'], ['id' => 1, 'ref' => 'area']);
        $this->db->insert($this->tables['cities'], ['id' => 1, 'ref' => 'city', 'area' => 'area']);
        $this->db->insert($this->tables['warehouse_types'], ['id' => 1, 'ref' => 'type']);
        foreach ([1 => 1, 2 => 0] as $id => $active) {
            $this->db->insert($this->tables['street'], ['id' => $id, 'ref' => 'street-' . $id,
                'city_ref' => 'city', 'description' => 'Street', 'street_type_ref' => 'type',
                'street_type' => 'Street', 'is_active' => $active]);
            $this->db->insert($this->tables['warehouse'], ['id' => $id, 'ref' => 'warehouse-' . $id,
                'city_ref' => 'city', 'type_of_warehouse' => 'type', 'is_active' => $active,
                'warehouse_status' => $active ? 'Working' : 'NotWorking']);
        }
        $streets = $this->objects->get(\Perspective\NovaposhtaCatalog\Api\StreetRepositoryInterface::class);
        $warehouses = $this->objects->get(\Perspective\NovaposhtaCatalog\Api\WarehouseRepositoryInterface::class);
        self::assertCount(1, $streets->getCollectionByCityRef('city')->getItems());
        self::assertCount(1, $warehouses->getCollectionOfWarehousesByCityRef('city')->getItems());
        self::assertSame(2, (int)$streets->getObjectByRef('street-2')->getId());
        self::assertSame(2, (int)$warehouses->getWarehouseByWarehouseRef('warehouse-2')->getId());
        $this->db->update($this->tables['areas'], ['is_active' => 0], ['id = ?' => 1]);
        self::assertCount(0, $streets->getCollectionByCityRef('city')->getItems());
        self::assertCount(0, $warehouses->getCollectionOfWarehousesByCityRef('city')->getItems());
        self::assertSame(1, (int)$streets->getObjectByRef('street-1')->getId());
        self::assertSame(1, (int)$warehouses->getWarehouseByWarehouseRef('warehouse-1')->getId());
    }

    private function street(string $ref, string $city): Street
    {
        return $this->objects->create(Street::class)->setRef($ref)->setCityRef($city)
            ->setDescription('Fixture')->setStreetTypeRef('type')->setStreetType('Street');
    }

    private function snapshot(int &$time): DirectorySnapshot
    {
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturnCallback(function () use (&$time) { return $time; });
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        return new DirectorySnapshot($lock, new SyncPolicy($this->createStub(ScopeConfigInterface::class), $clock));
    }
}
