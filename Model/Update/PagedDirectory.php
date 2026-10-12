<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Model\Update;

use Magento\Framework\Exception\LocalizedException;

class PagedDirectory
{
    /** Fetch and validate all pages before allowing any database write. */
    public function read(callable $fetch, int $pageSize): array
    {
        $rows = [];
        $total = null;
        $pages = 1;
        for ($page = 1; $page <= $pages; ++$page) {
            $response = $fetch($page);
            $count = is_array($response) ? ($response['info']['totalCount'] ?? null) : null;
            $pageTotal = is_int($count) || (is_string($count) && ctype_digit($count))
                ? filter_var($count, FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 0]])
                : false;
            if (!is_array($response) || ($response['success'] ?? null) !== true
                || !is_array($response['data'] ?? null) || !array_is_list($response['data'])
                || $pageTotal === false
            ) {
                throw new LocalizedException(__('Nova Poshta returned an invalid directory page, sync aborted.'));
            }
            if ($total !== null && $total !== $pageTotal) {
                throw new LocalizedException(__('Nova Poshta directory count changed during sync; retry the import.'));
            }
            $total = $pageTotal;
            $pages = max(1, (int)ceil($total / $pageSize));
            $expected = min($pageSize, max(0, $total - ($page - 1) * $pageSize));
            if (count($response['data']) !== $expected) {
                throw new LocalizedException(__('Nova Poshta returned an incomplete directory page, sync aborted.'));
            }
            array_push($rows, ...$response['data']);
        }
        return $rows;
    }
}
