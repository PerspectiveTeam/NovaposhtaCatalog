<?php
declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\NovaposhtaCatalog\Api\Data\StreetInterfaceFactory;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate;
use Perspective\NovaposhtaCatalog\Model\City\City;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\Collection;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\City\City\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\Street\Street as StreetModel;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;
use Perspective\NovaposhtaCatalog\Model\Update\Street;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Perspective\NovaposhtaCatalog\Service\JsonAsObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StreetTest extends TestCase
{
    use SnapshotTestSupport;
    #[DataProvider('pageScenarios')]
    public function testRequiresAllPagesWithConsistentCountsAndUniqueValidRows(string $case): void
    {
        $service = $this->service(['getServicePromise']);
        $factory = $this->createMock(StreetInterfaceFactory::class);
        $factory->expects(self::never())->method('create');
        $this->inject($service, 'streetFactory', $factory);
        $first = array_map(self::row(...), range(0, 499));
        $second = [self::row(500)];
        $total = 501;
        if ($case === 'empty') { $first = []; $total = 0; }
        if ($case === 'duplicate') { $second = [self::row(0)]; }
        if ($case === 'invalid_row') { $second[0]->StreetsTypeRef = null; }
        if ($case === 'empty_second') { $second = []; }
        $pages = [
            1 => ['success' => $case !== 'first_failure', 'data' => $first, 'info' => ['totalCount' => $total]],
            2 => ['success' => $case !== 'second_failure', 'data' => $second, 'info' => ['totalCount' => $case === 'changed_total' ? 502 : $total]],
        ];
        if ($case === 'missing_count') { unset($pages[1]['info']); }
        $service->expects(self::atLeastOnce())->method('getServicePromise')->willReturnCallback(fn ($params) => new Response(200, [], json_encode($pages[$params['methodProperties']['Page']])));
        if (!in_array($case, ['complete', 'empty'], true)) {
            $this->expectException(LocalizedException::class);
        }
        $result = $service->getDataFromEndpoint('city-a');
        self::assertTrue($result->success);
        self::assertCount($total, array_filter((array)$result, 'is_object'));
    }

    public static function pageScenarios(): array
    {
        return array_map(fn ($case) => [$case], ['complete', 'empty', 'first_failure', 'second_failure',
            'empty_second', 'changed_total', 'duplicate', 'invalid_row', 'missing_count']);
    }

    #[DataProvider('executionScenarios')]
    public function testExecuteOnlyReportsAndDatesFullySuccessfulRuns(string $case): void
    {
        $service = $this->service(['getDataFromEndpoint', 'setDataToDB']);
        $city = (new \ReflectionClass(City::class))->newInstanceWithoutConstructor()->setRef('city-a');
        $collection = $this->createStub(Collection::class);
        $collection->method('removeAllFieldsFromSelect')->willReturnSelf();
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addOrder')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$city]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $this->inject($service, 'cityCollectionFactory', $factory);
        $date = $this->createMock(CronSyncDateLastUpdate::class);
        $date->expects(in_array($case, ['success', 'empty'], true) ? self::once() : self::never())->method('updateSyncDate');
        $this->inject($service, 'cronSyncDateLastUpdate', $date);
        $response = (object)array_merge($case === 'empty' ? [] : [self::row(0)], ['success' => $case !== 'api_failure']);
        $service->expects(self::once())->method('getDataFromEndpoint')->willReturn($response);
        $save = $service->expects($case === 'api_failure' ? self::never() : self::once())->method('setDataToDB');
        if ($case === 'save_failure') { $save->willThrowException(new \RuntimeException('save failure')); }
        self::assertSame(!in_array($case, ['success', 'empty'], true), $service->execute()['error']);
    }

    public static function executionScenarios(): array
    {
        return [['success'], ['empty'], ['api_failure'], ['save_failure']];
    }

    public function testFetchDoesNotCreateModelsAndExecuteCreatesEachModelOnce(): void
    {
        $service = $this->service(['getServicePromise', 'setDataToDB']);
        $city = (new \ReflectionClass(City::class))->newInstanceWithoutConstructor()->setRef('city-a');
        $collection = $this->createStub(Collection::class);
        foreach (['removeAllFieldsFromSelect', 'addFieldToSelect', 'addOrder', 'addFieldToFilter'] as $method) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$city]));
        $cityFactory = $this->createStub(CollectionFactory::class);
        $cityFactory->method('create')->willReturn($collection);
        $this->inject($service, 'cityCollectionFactory', $cityFactory);
        $this->inject($service, 'cronSyncDateLastUpdate', $this->createStub(CronSyncDateLastUpdate::class));
        $service->method('getServicePromise')->willReturn(new Response(200, [], json_encode([
            'success' => true, 'data' => [self::row(1), self::row(2)], 'info' => ['totalCount' => 2],
        ])));
        $factory = $this->createMock(StreetInterfaceFactory::class);
        $created = 0;
        $factory->expects(self::exactly(2))->method('create')->willReturnCallback(function () use (&$created) {
            ++$created;
            return (new \ReflectionClass(StreetModel::class))->newInstanceWithoutConstructor();
        });
        $this->inject($service, 'streetFactory', $factory);
        $service->getDataFromEndpoint('city-a');
        self::assertSame(0, $created);
        $service->expects(self::once())->method('setDataToDB')->willReturnCallback(function ($models, $unused, $cityRef) {
            self::assertSame(['street-1', 'street-2'], array_keys($models));
            self::assertSame('city-a', $cityRef);
        });
        self::assertFalse($service->execute()['error']);
        self::assertSame(2, $created);
    }

    #[DataProvider('invalidPreparedStreets')]
    public function testRejectsForeignCityAndDuplicatePreparedStreets(string $case): void
    {
        $service = $this->service(['getDataFromEndpoint']);
        $service->expects(self::never())->method('getDataFromEndpoint');
        $snapshot = $this->createMock(DirectorySnapshot::class);
        $snapshot->expects(self::never())->method('save');
        $this->inject($service, 'snapshot', $snapshot);
        $street = (new \ReflectionClass(StreetModel::class))->newInstanceWithoutConstructor()
            ->setRef('street-a')->setDescription('Name')->setStreetTypeRef('type-a')->setStreetType('Street')
            ->setCityRef($case === 'foreign_city' ? 'city-b' : 'city-a');
        $this->expectException(LocalizedException::class);
        $service->setDataToDB($case === 'duplicate' ? [$street, $street] : [$street], null, 'city-a');
    }

    public static function invalidPreparedStreets(): array
    {
        return [['foreign_city'], ['duplicate']];
    }

    private function service(array $methods): Street
    {
        $service = $methods ? $this->getMockBuilder(Street::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock()
            : (new \ReflectionClass(Street::class))->newInstanceWithoutConstructor();
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $factory = $this->createStub(StreetInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn () => (new \ReflectionClass(StreetModel::class))->newInstanceWithoutConstructor());
        foreach (['configHelper' => $config, 'streetFactory' => $factory,
            'snapshot' => new DirectorySnapshot($this->availableLock(), $this->policy()),
            'pages' => new \Perspective\NovaposhtaCatalog\Model\Update\PagedDirectory(),
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
        (new \ReflectionProperty(Street::class, $property))->setValue($service, $value);
    }

    private static function row(int $index): \stdClass
    {
        return (object)['Ref' => 'street-' . $index, 'Description' => 'Name ' . $index,
            'StreetsTypeRef' => 'type-a', 'StreetsType' => 'Street'];
    }
}
