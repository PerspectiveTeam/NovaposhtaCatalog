<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Service\DirectorySyncMapper;
use PHPUnit\Framework\TestCase;

class DirectorySyncMapperTest extends TestCase
{
    public function testDirectoryMapUsesSelectedStoreAndSeparatesItsCache(): void
    {
        $config = $this->createMock(Config::class);
        $config->expects(self::exactly(2))->method('getDirectoryMap')
            ->willReturnCallback(fn (?int $storeId) => ['UA-46' => 'area-store-' . $storeId]);
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchAll')->willReturn([['region_id' => 100, 'code' => 'UA-46']]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('directory_country_region');
        $mapper = new DirectorySyncMapper($config, $resource);
        self::assertSame([100 => 'area-store-1'], $mapper->getRegionIdToAreaRefMap(1));
        self::assertSame([100 => 'area-store-2'], $mapper->getRegionIdToAreaRefMap(2));
        self::assertSame([100 => 'area-store-1'], $mapper->getRegionIdToAreaRefMap(1));
    }
}
