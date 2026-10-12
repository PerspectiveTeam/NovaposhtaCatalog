<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DirectorySnapshot
{
    public function __construct(private LockManagerInterface $lockManager)
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

    /**
     * @param AbstractModel[] $models
     * @param callable(): iterable<AbstractModel> $getCollection
     */
    public function save(
        array $models,
        AbstractDb $resource,
        callable $getCollection,
        string $lockName,
        bool $requireKnownRefs = true
    ): void
    {
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new LocalizedException(__('Nova Poshta directory sync is already in progress.'));
        }
        try {
            $existing = [];
            foreach ($getCollection() as $item) {
                $existing[$item->getData('ref')] = $item;
            }
            if ($requireKnownRefs && array_diff_key($existing, $models)) {
                throw new LocalizedException(__('Nova Poshta returned an incomplete directory, sync aborted.'));
            }

            $connection = $resource->getConnection();
            $connection->beginTransaction();
            try {
                foreach ($models as $ref => $model) {
                    if (isset($existing[$ref])) {
                        $id = $existing[$ref]->getId();
                        $model = $existing[$ref]->addData($model->getData())->setId($id);
                    }
                    $resource->save($model);
                }
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();
                throw $e;
            }
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
