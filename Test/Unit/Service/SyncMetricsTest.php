<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Service;

use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Model\Update\Warehouse;
use Perspective\NovaposhtaCatalog\Plugin\Update\SyncMetricsPlugin;
use Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitException;
use Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitRetry;
use Perspective\NovaposhtaCatalog\Service\HTTP\RetryDelay;
use Perspective\NovaposhtaCatalog\Service\SyncMetrics;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SyncMetricsTest extends TestCase
{
    public function testMeasuresFailuresAndResetsCountersForTheNextImport(): void
    {
        $logs = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')->willReturnCallback(function ($message, $context) use (&$logs) {
            self::assertSame('Nova Poshta synchronization metrics.', $message);
            $logs[] = $context;
        });
        $metrics = new SyncMetrics($logger);
        $metrics->increment('requests');
        try {
            $metrics->run('streets', function () use ($metrics) {
                $metrics->increment('requests');
                $metrics->measure('db_write', function () { throw new \RuntimeException('private failure detail'); });
            });
            self::fail('Exception was swallowed');
        } catch (\RuntimeException $e) {
            self::assertSame('private failure detail', $e->getMessage());
        }
        $result = ['error' => false, 'message' => 'private message', 'data' => ['apiKey' => 'secret']];
        self::assertSame($result, $metrics->run('warehouses', fn () => $result));
        self::assertTrue($logs[0]['error']);
        self::assertSame(1, $logs[0]['requests']);
        self::assertGreaterThanOrEqual(0, $logs[0]['phase_ms']['db_write']);
        self::assertFalse($logs[1]['error']);
        self::assertSame(0, $logs[1]['requests']);
        self::assertSame(['api', 'prepare', 'db_read', 'db_write', 'retry_wait'], array_keys($logs[1]['phase_ms']));
        self::assertStringNotContainsString('secret', json_encode($logs));
        self::assertStringNotContainsString('private', json_encode($logs));
    }

    public function testTelemetryFailureCannotChangeImportResultOrMaskAnException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info')->willThrowException(new \RuntimeException('logger failed'));
        $metrics = new SyncMetrics($logger);
        self::assertSame(['error' => false], $metrics->run('cities', fn () => ['error' => false]));
        $this->expectExceptionMessage('import failed');
        $metrics->run('cities', function () { throw new \RuntimeException('import failed'); });
    }

    public function testRetryCountersIncludeTheFinalFailedAttemptAndBothRateLimitFormats(): void
    {
        $logged = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willReturnCallback(function ($message, $context) use (&$logged) {
            $logged = $context;
        });
        $metrics = new SyncMetrics($logger);
        $delay = $this->createMock(RetryDelay::class);
        $waits = [];
        $delay->expects(self::exactly(5))->method('wait')->willReturnCallback(function ($seconds) use (&$waits) { $waits[] = $seconds; });
        $retry = new RateLimitRetry($delay, $this->createStub(DateTime::class), $this->createStub(LoggerInterface::class), $metrics);
        $calls = 0;
        try {
            $metrics->run('warehouses', fn () => $metrics->measure('api', function () use ($retry, &$calls) {
                return $retry->execute(function () use (&$calls) {
                    return ++$calls % 2 === 1 ? new Response(429, [], '')
                        : new Response(200, [], '{"success":false,"errors":["Too many requests"]}');
                });
            }));
            self::fail('Rate limit not reported');
        } catch (RateLimitException) {
            self::assertSame([5, 10, 20, 40, 60], $waits);
        }
        self::assertTrue($logged['error']);
        self::assertSame(6, $logged['requests']);
        self::assertSame(5, $logged['retries']);
        self::assertSame(3, $logged['http_429']);
        self::assertSame(6, $logged['rate_limits']);
    }

    public function testPluginPreservesThePublicResultAndReportsReturnedErrors(): void
    {
        $logged = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willReturnCallback(function ($message, $context) use (&$logged) {
            $logged = $context;
        });
        $subject = (new \ReflectionClass(Warehouse::class))->newInstanceWithoutConstructor();
        $result = ['error' => true, 'message' => 'failed', 'data' => []];
        self::assertSame($result, (new SyncMetricsPlugin(new SyncMetrics($logger)))->aroundExecute($subject, fn () => $result));
        self::assertSame('warehouses', $logged['entity']);
        self::assertTrue($logged['error']);
    }
}
