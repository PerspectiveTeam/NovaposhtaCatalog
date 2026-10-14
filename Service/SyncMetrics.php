<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Service;

use Psr\Log\LoggerInterface;

class SyncMetrics
{
    private ?array $current = null;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function run(string $entity, callable $operation): mixed
    {
        $previous = $this->current;
        $this->current = [
            'entity' => $entity,
            'phase_ms' => array_fill_keys(['api', 'prepare', 'db_read', 'db_write', 'retry_wait'], 0),
            'requests' => 0,
            'retries' => 0,
            'http_429' => 0,
            'rate_limits' => 0,
        ];
        $start = hrtime(true);
        $error = true;
        try {
            $result = $operation();
            $error = is_array($result) && !empty($result['error']);
            return $result;
        } finally {
            $context = $this->current;
            $this->current = $previous;
            $context['error'] = $error;
            $context['duration_ms'] = round((hrtime(true) - $start) / 1e6, 3);
            $context['process_peak_memory_bytes'] = memory_get_peak_usage(true);
            $context['phase_ms'] = array_map(fn ($time) => round($time, 3), $context['phase_ms']);
            try {
                $this->logger->info('Nova Poshta synchronization metrics.', $context);
            } catch (\Throwable) {
                // Telemetry must not turn a committed import into a failed one.
            }
        }
    }

    public function measure(string $phase, callable $operation): mixed
    {
        if ($this->current === null) {
            return $operation();
        }
        $start = hrtime(true);
        try {
            return $operation();
        } finally {
            $this->current['phase_ms'][$phase] += (hrtime(true) - $start) / 1e6;
        }
    }

    public function increment(string $counter): void
    {
        if ($this->current !== null) {
            ++$this->current[$counter];
        }
    }
}
