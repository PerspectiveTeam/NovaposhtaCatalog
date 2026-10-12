<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DirectorySnapshot
{
    private array $heldLocks = [];

    public function __construct(private LockManagerInterface $lockManager, private SyncPolicy $policy)
    {
    }

    public function getResponseData(mixed $response): array
    {
        if (!is_object($response) || ($response->success ?? null) !== true
            || !is_array($response->data ?? null)
        ) {
            throw new LocalizedException(__('Nova Poshta returned an invalid directory response, sync aborted.'));
        }
        return $response->data;
    }

    /**
     * @return AbstractModel[]
     */
    public function prepare(mixed $data, callable $prepareModel, bool $allowEmpty = false): array
    {
        if (!is_iterable($data)) {
            throw new LocalizedException(__('Nova Poshta returned an invalid directory list, sync aborted.'));
        }
        $models = [];
        foreach ($data as $row) {
            if (!$this->isValidRow($row)) {
                throw new LocalizedException(__('Nova Poshta returned an invalid directory record, sync aborted.'));
            }
            if (isset($models[$row->Ref])) {
                throw new LocalizedException(__('Nova Poshta returned duplicate references, sync aborted.'));
            }
            $models[$row->Ref] = $prepareModel($row);
        }
        if (!$models && !$allowEmpty) {
            throw new LocalizedException(__('Nova Poshta returned an empty directory, sync aborted.'));
        }
        return $models;
    }

    private function isValidRow(mixed $row): bool
    {
        if (!is_object($row)) {
            return false;
        }
        $hasValidRef = is_string($row->Ref ?? null)
            && trim($row->Ref) !== '' && strlen($row->Ref) <= 255;
        $hasValidDescription = is_string($row->Description ?? null) && trim($row->Description) !== '';
        $hasValidTranslation = !isset($row->DescriptionRu) || is_string($row->DescriptionRu);

        return $hasValidRef && $hasValidDescription && $hasValidTranslation;
    }

    /** Keep the lock across fetching and saving; nested saves reuse the owned lock. */
    public function withLock(string $lockName, callable $operation): mixed
    {
        if (isset($this->heldLocks[$lockName])) {
            return $operation();
        }
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new LocalizedException(__('Nova Poshta directory sync is already in progress: %1.', $lockName));
        }
        $this->heldLocks[$lockName] = true;
        try {
            return $operation();
        } finally {
            unset($this->heldLocks[$lockName]);
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * @param AbstractModel[] $models
     * @param callable(): iterable<AbstractModel> $getCollection
     */
    public function save(
        array $models,
        AbstractDb $resource,
        callable $getCollection,
        string $lockName,
        bool $requireKnownRefs = true,
        ?callable $findExisting = null
    ): void
    {
        // The legacy fifth argument is retained for callers; all directories now use the safety policy.
        $this->withLock($lockName, function () use ($models, $resource, $getCollection, $lockName, $findExisting) {
            $existing = [];
            foreach ($getCollection() as $item) {
                $ref = $item->getData('ref');
                if (isset($existing[$ref])) {
                    throw new LocalizedException(__('Duplicate local Nova Poshta reference in %1; resolve before syncing.', $lockName));
                }
                $existing[$ref] = $item;
            }
            $this->policy->assertAcceptable($existing, $models, $lockName);

            $connection = $resource->getConnection();
            $connection->beginTransaction();
            try {
                foreach ($models as $ref => $model) {
                    $stored = $existing[$ref] ?? ($findExisting ? $findExisting($ref) : null);
                    if ($stored !== null) {
                        $id = $stored->getId();
                        $model = $stored->addData($model->getData())->setId($id);
                    }
                    $this->policy->markPresent($model);
                    $resource->save($model);
                }
                foreach (array_diff_key($existing, $models) as $model) {
                    if ($this->policy->markMissing($model)) {
                        $resource->save($model);
                    }
                }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();
                throw $e;
            }
        });
    }

    /** Preserve identity if an API reference moves to another city. */
    public function saveScoped(
        array $models,
        AbstractDb $resource,
        callable $createCollection,
        string $cityRef,
        string $lockPrefix
    ): void {
        $lockName = $lockPrefix . sha1($cityRef);
        $this->withLock($lockName, function () use ($models, $resource, $createCollection, $cityRef, $lockPrefix, $lockName) {
            // Serialize only DB writes across cities, so resolving a moved Ref cannot create duplicates.
            $this->withLock($lockPrefix . 'write', function () use ($models, $resource, $createCollection, $cityRef, $lockName) {
                $known = [];
                if ($models) {
                    foreach ($createCollection()->addFieldToFilter('ref', ['in' => array_keys($models)]) as $item) {
                        $ref = $item->getData('ref');
                        if (isset($known[$ref])) {
                            throw new LocalizedException(__('Duplicate local Nova Poshta reference; resolve before syncing.'));
                        }
                        $known[$ref] = $item;
                    }
                }
                $this->save($models, $resource,
                    fn () => $createCollection()->addFieldToFilter('city_ref', $cityRef),
                    $lockName, true, fn ($ref) => $known[$ref] ?? null);
            });
        });
    }
}
