<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\Update;
use Perspective\NovaposhtaCatalog\Service\SyncMetrics;
use Psr\Log\NullLogger;

class ImportBenchmarkTest extends TemporaryCatalogTestCase
{
    private const STREET_REF_INDEX = 'PERSPECTIVE_NOVAPOSHTA_CATALOG_STREET_REF';
    private array $responses = [];

    public function testOfflineImportsMatchBaselineAcrossFiveRepetitions(): void
    {
        $baseline = getenv('NOVAPOSHTA_BASELINE_PATH');
        if (!$baseline) {
            self::markTestSkipped('Set NOVAPOSHTA_BASELINE_PATH to a saved pre-change module directory.');
        }
        $this->loadBaseline($baseline);
        $runs = ['before' => [], 'after' => []];
        $metrics = new SyncMetrics(new NullLogger());
        $before = $this->updaters('Perspective\\NovaposhtaCatalog\\Model\\UpdateBaseline', $metrics);
        $after = $this->updaters('Perspective\\NovaposhtaCatalog\\Model\\Update', $metrics);
        $profiler = $this->db->getProfiler();
        for ($iteration = 0; $iteration < 6; ++$iteration) {
            $expected = null;
            foreach (['before' => $before, 'after' => $after] as $version => $updaters) {
                $this->resetFixtures($version === 'after');
                $profiler->clear();
                $profiler->setEnabled(true);
                gc_collect_cycles();
                memory_reset_peak_usage();
                $memory = memory_get_usage();
                $cpu = getrusage();
                $start = hrtime(true);
                foreach ($updaters as $entity => $updater) {
                    $result = $metrics->run($entity, $updater->execute(...));
                    self::assertFalse($result['error'], $version . '/' . $entity . ': ' . $result['message']);
                }
                $elapsed = (hrtime(true) - $start) / 1e6;
                $usage = getrusage();
                $sample = [
                    'duration_ms' => round($elapsed, 3),
                    'cpu_ms' => round($this->cpuMillis($usage) - $this->cpuMillis($cpu), 3),
                    'peak_extra_bytes' => max(0, memory_get_peak_usage() - $memory),
                    'sql_queries' => $profiler->getTotalNumQueries(),
                ];
                $profiler->setEnabled(false);
                $state = [];
                foreach (array_keys($this->tables) as $entity) {
                    $state[$entity] = hash('sha256', json_encode($this->rows($entity), JSON_THROW_ON_ERROR));
                }
                if ($expected === null) {
                    $expected = $state;
                } else {
                    self::assertSame($expected, $state, 'Table contents differ from the baseline.');
                }
                if ($iteration > 0) { $runs[$version][] = $sample; }
            }
        }
        foreach ($runs as $version => $samples) {
            $medians = [];
            foreach (array_keys($samples[0]) as $metric) {
                $values = array_column($samples, $metric);
                sort($values);
                $medians[$metric] = $values[2];
            }
            fwrite(STDOUT, PHP_EOL . json_encode([
                'offline_benchmark' => $version, 'medians' => $medians, 'samples' => $samples,
                'fixtures' => ['cities' => 20, 'imported_per_entity' => 420, 'background_streets' => 20000],
            ], JSON_THROW_ON_ERROR) . PHP_EOL);
        }
    }

    private function loadBaseline(string $directory): void
    {
        foreach (['DirectorySnapshot', 'Warehouse', 'Street'] as $class) {
            $file = $directory . '/Model/Update/' . $class . '.php';
            self::assertFileExists($file);
            $source = file_get_contents($file);
            $source = str_replace(
                'namespace Perspective\\NovaposhtaCatalog\\Model\\Update;',
                'namespace Perspective\\NovaposhtaCatalog\\Model\\UpdateBaseline;'
                    . '\nuse Perspective\\NovaposhtaCatalog\\Model\\Update\\SyncPolicy;'
                    . '\nuse Perspective\\NovaposhtaCatalog\\Model\\Update\\PagedDirectory;',
                $source
            );
            $source = str_replace('\\nuse ', "\nuse ", $source);
            // Only a caller-supplied, trusted local baseline is loaded; it is not added to runtime DI.
            eval(substr($source, strpos($source, '<?php') + 5));
        }
    }

    private function updaters(string $namespace, SyncMetrics $metrics): array
    {
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(1760000000);
        $policy = new Update\SyncPolicy($this->createStub(ScopeConfigInterface::class), $clock);
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        $snapshotClass = $namespace . '\\DirectorySnapshot';
        $snapshot = is_a($snapshotClass, Update\DirectorySnapshot::class, true)
            ? new $snapshotClass($lock, $policy, $metrics) : new $snapshotClass($lock, $policy);
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getApiKey')->willReturn('offline-fixture');
        $date = $this->createStub(CronSyncDateLastUpdate::class);
        $updaters = [];
        foreach (['Warehouse' => 'warehouses', 'Street' => 'streets'] as $short => $entity) {
            $class = $namespace . '\\' . $short;
            $overrides = ['configHelper' => $config, 'cronSyncDateLastUpdate' => $date,
                'snapshot' => $snapshot, 'metrics' => $metrics, 'logger' => new NullLogger(),
                'serializer' => new \Perspective\NovaposhtaCatalog\Service\JsonAsObject(),
                'serializerToArray' => new \Magento\Framework\Serialize\Serializer\Json()];
            $arguments = [];
            foreach ((new \ReflectionClass($class))->getConstructor()->getParameters() as $parameter) {
                $arguments[] = $overrides[$parameter->getName()]
                    ?? $this->objects->get($parameter->getType()->getName());
            }
            $updater = $this->getMockBuilder($class)->setConstructorArgs($arguments)
                ->onlyMethods(['getServicePromise'])->getMock();
            $updater->expects(self::atLeastOnce())->method('getServicePromise')->willReturnCallback(function ($params) use ($entity) {
                $properties = $params['methodProperties'];
                $rows = $this->responses[$entity][$properties['CityRef']];
                return new Response(200, [], json_encode([
                    'success' => true, 'data' => array_slice($rows,
                        ($properties['Page'] - 1) * $properties['Limit'], $properties['Limit']),
                    'info' => ['totalCount' => count($rows)],
                ], JSON_THROW_ON_ERROR));
            });
            $updaters[$entity] = $updater;
        }
        return $updaters;
    }

    private function resetFixtures(bool $indexed): void
    {
        foreach ($this->tables as $table) {
            $this->db->query('TRUNCATE TABLE ' . $this->db->quoteIdentifier($table));
        }
        $hasIndex = isset($this->db->getIndexList($this->tables['street'])[self::STREET_REF_INDEX]);
        if ($indexed && !$hasIndex) {
            $this->db->addIndex($this->tables['street'], self::STREET_REF_INDEX, ['ref'], AdapterInterface::INDEX_TYPE_INDEX);
        } elseif (!$indexed && $hasIndex) {
            $this->db->dropIndex($this->tables['street'], self::STREET_REF_INDEX);
        }
        $this->db->insert($this->tables['areas'], ['id' => 1, 'ref' => 'area']);
        $this->db->insert($this->tables['warehouse_types'], ['id' => 1, 'ref' => 'type']);
        $this->db->insert($this->tables['package_types'], ['id' => 1, 'ref' => 'custom', 'source' => 'custom', 'max_weight' => '12.5']);
        $this->responses = ['warehouses' => [], 'streets' => []];
        for ($city = 1; $city <= 20; ++$city) {
            $cityRef = 'city-' . $city;
            $this->db->insert($this->tables['cities'], [
                'id' => $city, 'city_id' => $city, 'ref' => $cityRef, 'area' => 'area',
                'descriptionua' => 'Місто ' . $city, 'descriptionru' => 'Город ' . $city,
            ]);
            for ($row = 1; $row <= 21; ++$row) {
                $ref = 'ref-' . $city . '-' . $row;
                $warehouse = (object)[
                    'Ref' => $ref, 'Description' => 'Відділення ' . $row, 'CityRef' => $cityRef,
                    'TypeOfWarehouse' => 'type', 'WarehouseStatus' => $row === 1 ? 'NotWorking' : 'Working',
                    'SiteKey' => $row, 'Number' => (string)$row, 'Schedule' => (object)['Monday' => '09:00-18:00'],
                ];
                $street = (object)['Ref' => $ref, 'Description' => 'Вулиця ' . $row,
                    'StreetsTypeRef' => 'type', 'StreetsType' => 'вул.'];
                $this->responses['warehouses'][$cityRef][] = $warehouse;
                $this->responses['streets'][$cityRef][] = $street;
                if ($row <= 20) {
                    $id = ($city - 1) * 20 + $row;
                    $this->db->insert($this->tables['warehouse'], [
                        'id' => $id, 'ref' => $ref, 'city_ref' => $cityRef,
                        'description_ru' => 'Preserved optional translation', 'warehouse_status' => 'Working',
                    ]);
                    $this->db->insert($this->tables['street'], [
                        'id' => $id, 'ref' => $ref, 'city_ref' => $cityRef,
                        'description' => 'Old', 'street_type_ref' => 'type', 'street_type' => 'Street',
                    ]);
                }
            }
        }
        $background = [];
        for ($i = 1; $i <= 20000; ++$i) {
            $background[] = ['id' => 10000 + $i, 'ref' => 'background-' . $i, 'city_ref' => 'unused',
                'description' => 'Historical', 'street_type_ref' => 'type', 'street_type' => 'Street', 'is_active' => 0];
            if (count($background) === 500) {
                $this->db->insertMultiple($this->tables['street'], $background);
                $background = [];
            }
        }
    }

    private function cpuMillis(array $usage): float
    {
        return ($usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec']) * 1000
            + ($usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec']) / 1000;
    }
}
