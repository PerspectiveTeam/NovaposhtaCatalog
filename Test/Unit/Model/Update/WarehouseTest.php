<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\NovaposhtaCatalog\Api\CityRepositoryInterface;
use Perspective\NovaposhtaCatalog\Api\Data\CitySearchResultsInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\City\City;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;
use Perspective\NovaposhtaCatalog\Model\Update\PagedDirectory;
use Perspective\NovaposhtaCatalog\Model\Update\Warehouse;
use Perspective\NovaposhtaCatalog\Model\Warehouse\Warehouse as WarehouseModel;
use Perspective\NovaposhtaCatalog\Model\Warehouse\WarehouseFactory;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Perspective\NovaposhtaCatalog\Service\JsonAsObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WarehouseTest extends TestCase
{
    use SnapshotTestSupport;

    #[DataProvider('pageCases')]
    public function testOnlyAcceptsCompleteConsistentCitySnapshots(string $case): void
    {
        $service = $this->service(['getServicePromise']);
        $first = array_map(self::row(...), range(1, 100));
        $second = [self::row(101)];
        $total = $case === 'empty' ? 0 : 101;
        if ($case === 'empty') { $first = []; }
        if ($case === 'duplicate') { $second = [self::row(1)]; }
        if ($case === 'foreign_city') { $second[0]->CityRef = 'city-b'; }
        if ($case === 'unknown_status') { $second[0]->WarehouseStatus = 'Unexpected'; }
        if ($case === 'invalid_row') { unset($second[0]->TypeOfWarehouse); }
        if ($case === 'short_page') { $second = []; }
        $pages = [
            1 => ['success' => $case !== 'first_failure', 'data' => $first, 'info' => ['totalCount' => $total]],
            2 => ['success' => $case !== 'second_failure', 'data' => $second,
                'info' => ['totalCount' => $case === 'changed_count' ? 102 : $total]],
        ];
        if ($case === 'missing_count') { unset($pages[1]['info']); }
        if ($case === 'invalid_count') { $pages[1]['info']['totalCount'] = true; }
        $service->expects(self::atLeastOnce())->method('getServicePromise')->willReturnCallback(fn ($params) => new Response(
            200, [], json_encode($pages[$params['methodProperties']['Page']])
        ));
        if (!in_array($case, ['complete', 'empty'], true)) {
            $this->expectException(LocalizedException::class);
        }
        $result = $service->getDataFromEndpoint('city-a');
        self::assertTrue($result->success);
        self::assertCount($total, array_filter((array)$result, 'is_object'));
    }

    public static function pageCases(): array
    {
        return array_map(fn ($case) => [$case], ['complete', 'empty', 'first_failure', 'second_failure',
            'short_page', 'changed_count', 'duplicate', 'foreign_city', 'invalid_row', 'unknown_status',
            'missing_count', 'invalid_count']);
    }

    #[DataProvider('executionCases')]
    public function testLocksBeforeFetchingAndOnlyDatesAnEntireSuccessfulRun(string $case): void
    {
        $service = $this->service(['getDataFromEndpoint', 'setDataToDb']);
        $city = (new \ReflectionClass(City::class))->newInstanceWithoutConstructor()->setRef('city-a');
        $result = $this->createStub(CitySearchResultsInterface::class);
        $result->method('getItems')->willReturn([$city, clone $city->setRef('city-b')]);
        $city->setRef('city-a');
        $repo = $this->createStub(CityRepositoryInterface::class);
        $repo->method('getList')->willReturn($result);
        $this->inject($service, 'cityRepository', $repo);
        $criteria = $this->createStub(SearchCriteriaBuilder::class);
        $criteria->method('addFilter')->willReturnSelf();
        $criteria->method('create')->willReturn(new SearchCriteria());
        $this->inject($service, 'searchCriteriaBuilder', $criteria);
        $date = $this->createMock(CronSyncDateLastUpdate::class);
        $date->expects($case === 'success' ? self::once() : self::never())->method('updateSyncDate');
        $this->inject($service, 'cronSyncDateLastUpdate', $date);
        $held = null;
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::exactly(2))->method('lock')->willReturnCallback(function ($name) use (&$held) { $held = $name; return true; });
        $lock->method('unlock')->willReturnCallback(function ($name) use (&$held) {
            self::assertSame($name, $held); $held = null; return true;
        });
        $this->inject($service, 'snapshot', new DirectorySnapshot($lock, $this->policy()));
        $service->expects(self::atLeastOnce())->method('getDataFromEndpoint')->willReturnCallback(function ($ref) use ($case, &$held) {
            self::assertSame('np_warehouses_' . sha1($ref), $held);
            if ($ref === 'city-b' && $case === 'api_failure') { throw new LocalizedException(__('API failure')); }
            return (object)['success' => true];
        });
        $service->method('setDataToDb')->willReturnCallback(function ($data, $ref) use ($case, &$held) {
            self::assertSame('np_warehouses_' . sha1($ref), $held);
            if ($ref === 'city-b' && $case === 'save_failure') { throw new \RuntimeException('Save failure'); }
        });
        self::assertSame($case !== 'success', $service->execute()['error']);
        self::assertNull($held);
    }

    public static function executionCases(): array
    {
        return [['success'], ['api_failure'], ['save_failure']];
    }

    private function service(array $methods): Warehouse
    {
        $service = $methods ? $this->getMockBuilder(Warehouse::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock()
            : (new \ReflectionClass(Warehouse::class))->newInstanceWithoutConstructor();
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $factory = $this->createStub(WarehouseFactory::class);
        $factory->method('create')->willReturnCallback(fn () => (new \ReflectionClass(WarehouseModel::class))->newInstanceWithoutConstructor());
        foreach (['configHelper' => $config, 'warehouseFactory' => $factory,
            'snapshot' => new DirectorySnapshot($this->availableLock(), $this->policy()), 'pages' => new PagedDirectory(),
            'postService' => $this->createStub(Post::class), 'serializer' => new JsonAsObject(),
            'serializerToArray' => new Json(), 'logger' => $this->createStub(LoggerInterface::class)] as $name => $value) {
            $this->inject($service, $name, $value);
        }
        return $service;
    }

    public function testExhaustedRateLimitIsNotRetriedAgainAsATransportFailure(): void
    {
        $service = $this->service([]);
        $post = $this->createMock(Post::class);
        $post->expects(self::once())->method('getResponse')->willThrowException(
            new \Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitException(__('Rate limit exhausted'))
        );
        $this->inject($service, 'postService', $post);
        $this->expectException(\Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitException::class);
        $service->getDataFromEndpoint('city-a');
    }

    private function inject(object $service, string $property, mixed $value): void
    {
        (new \ReflectionProperty(Warehouse::class, $property))->setValue($service, $value);
    }

    private static function row(int $id): object
    {
        return (object)['Ref' => 'warehouse-' . $id, 'Description' => 'Warehouse ' . $id,
            'CityRef' => 'city-a', 'TypeOfWarehouse' => 'type-a', 'WarehouseStatus' => 'Working',
            'SiteKey' => $id, 'Number' => (string)$id];
    }
}
