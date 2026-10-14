<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Integration;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

abstract class TemporaryCatalogTestCase extends TestCase
{
    protected ObjectManagerInterface $objects;
    protected AdapterInterface $db;
    protected array $tables = [];
    protected FixtureEventManager $events;

    protected function setUp(): void
    {
        $this->objects = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $this->objects->get(State::class)->setAreaCode('adminhtml');
        // Exercise model/resource lifecycle without dispatching observers into shared services.
        $this->events = new FixtureEventManager();
        $context = $this->objects->get(\Magento\Framework\Model\Context::class);
        (new \ReflectionProperty($context, '_eventDispatcher'))->setValue($context, $this->events);
        $resources = $this->objects->get(ResourceConnection::class);
        $this->db = $resources->getConnection();
        $prefix = 'np_sync_' . bin2hex(random_bytes(3)) . '_';
        foreach (['areas', 'cities', 'warehouse_types', 'package_types', 'warehouse', 'street'] as $entity) {
            $logicalName = 'perspective_novaposhta_catalog_' . $entity;
            $source = $resources->getTableName($logicalName);
            $table = $prefix . $entity;
            $identifier = $this->db->quoteIdentifier($source);
            // Unique temporary names remain safe even if the adapter reconnects.
            $definition = $this->db->fetchRow('SHOW CREATE TABLE ' . $identifier)['Create Table'];
            $this->db->query(preg_replace('/^CREATE TABLE `[^`]+`/',
                'CREATE TEMPORARY TABLE ' . $this->db->quoteIdentifier($table), $definition, 1));
            $this->tables[$entity] = $table;
            $resources->setMappedTableName($logicalName, $table);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            while ($this->db->getTransactionLevel() > 0) {
                $this->db->rollBack();
            }
            foreach ($this->tables as $table) {
                $this->db->query('DROP TEMPORARY TABLE ' . $this->db->quoteIdentifier($table));
            }
        }
    }

    protected function rows(string $entity): array
    {
        return $this->db->fetchAll($this->db->select()->from($this->tables[$entity])->order('id ASC'));
    }
}

class FixtureEventManager implements ManagerInterface
{
    public array $counts = [];

    public function dispatch($eventName, array $data = [])
    {
        $this->counts[$eventName] = ($this->counts[$eventName] ?? 0) + 1;
    }
}
