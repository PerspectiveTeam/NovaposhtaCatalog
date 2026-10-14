<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Model\Update;

use Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package\Collection;
use Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package\CollectionFactory;
use Perspective\NovaposhtaCatalog\Model\Update\DirectorySnapshot;
use Perspective\NovaposhtaCatalog\Model\Update\Package;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    public function testSnapshotOnlyLoadsApiPackagesForPresenceAndMissingChecks(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addFieldToFilter')->with('source', 'api')->willReturnSelf();
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($collection);
        $snapshot = $this->createMock(DirectorySnapshot::class);
        $snapshot->expects(self::once())->method('prepare')->willReturn([]);
        $snapshot->expects(self::once())->method('save')->willReturnCallback(function ($models, $resource, $getCollection, $lock) use ($collection) {
            self::assertSame('perspective_novaposhta_packages_sync', $lock);
            self::assertSame($collection, $getCollection());
        });
        $updater = new Package(
            $this->createStub(\Perspective\NovaposhtaCatalog\Helper\Config::class),
            $this->createStub(\Perspective\NovaposhtaCatalog\Helper\CronSyncDateLastUpdate::class),
            $collections,
            $this->createStub(\Perspective\NovaposhtaCatalog\Model\ResourceModel\Package\Package::class),
            $this->createStub(\Perspective\NovaposhtaCatalog\Model\Package\PackageFactory::class),
            $this->createStub(\Magento\Framework\Serialize\SerializerInterface::class),
            $this->createStub(\Perspective\NovaposhtaCatalog\Service\HTTP\Post::class),
            $snapshot,
            $this->createStub(\Psr\Log\LoggerInterface::class)
        );
        $updater->setDataToDB([]);
    }
}
