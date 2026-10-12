<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Service\HTTP;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitException;
use Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitRetry;
use Perspective\NovaposhtaCatalog\Service\HTTP\RetryDelay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RateLimitRetryTest extends TestCase
{
    #[DataProvider('throttledResponses')]
    public function testRetriesHttpAndApiRateLimitsUntilSuccessful(Response $limited): void
    {
        $delays = [];
        $service = $this->service($delays);
        $success = new Response(200, [], '{"success":true,"data":[]}');
        $calls = 0;
        self::assertSame($success, $service->execute(function () use (&$calls, $limited, $success) {
            return ++$calls < 3 ? $limited : $success;
        }));
        self::assertSame(3, $calls);
        self::assertCount(2, $delays);
        self::assertGreaterThanOrEqual(10, $delays[0]);
        self::assertLessThanOrEqual(13, $delays[0]);
        self::assertGreaterThanOrEqual(20, $delays[1]);
        self::assertLessThanOrEqual(23, $delays[1]);
    }

    public static function throttledResponses(): array
    {
        return [[new Response(429, [], '<html>Too many requests</html>')],
            [new Response(200, [], '{"success":false,"errors":["To many requests"]}')],
            [new Response(200, [], '{"success":false,"errors":["Too Many Requests, try later"]}')]];
    }

    #[DataProvider('retryAfterHeaders')]
    public function testHonorsRetryAfterWithoutSleepingInTests(string $header, int $expected): void
    {
        $delays = [];
        $calls = 0;
        $service = $this->service($delays);
        $service->execute(function () use (&$calls, $header) {
            return ++$calls === 1 ? new Response(429, ['Retry-After' => $header], '') : new Response(200, [], '{}');
        });
        self::assertSame([$expected], $delays);
    }

    public static function retryAfterHeaders(): array
    {
        return [['7', 7], ['0007', 7], ['0', 1], [gmdate('D, d M Y H:i:s', 1760000030) . ' GMT', 30],
            [gmdate('D, d M Y H:i:s', 1759999990) . ' GMT', 1]];
    }

    public function testStopsAfterFiveAttemptsWithBoundedExponentialBackoff(): void
    {
        $delays = [];
        $calls = 0;
        try {
            $this->service($delays)->execute(function () use (&$calls) { ++$calls; return new Response(429, [], ''); });
            self::fail('Rate limit was not reported');
        } catch (RateLimitException $e) {
            self::assertStringContainsString('5 attempts', $e->getMessage());
        }
        self::assertSame(5, $calls);
        self::assertCount(4, $delays);
        self::assertLessThanOrEqual(180, array_sum($delays));
        self::assertSame(60, $delays[3]);
    }

    public function testExcessiveRetryAfterAbortsWithoutRetryingPrematurely(): void
    {
        $delays = [];
        $calls = 0;
        try {
            $this->service($delays)->execute(function () use (&$calls) {
                ++$calls; return new Response(429, ['Retry-After' => '3600'], '');
            });
            self::fail('Excessive delay was not rejected');
        } catch (RateLimitException $e) {
            self::assertStringContainsString('budget', $e->getMessage());
        }
        self::assertSame(1, $calls);
        self::assertSame([], $delays);
    }

    public function testCumulativeRetryAfterBudgetIsBounded(): void
    {
        $delays = [];
        $calls = 0;
        try {
            $this->service($delays)->execute(function () use (&$calls) {
                ++$calls; return new Response(429, ['Retry-After' => '100'], '');
            });
            self::fail('Cumulative delay was not rejected');
        } catch (RateLimitException $e) {}
        self::assertSame(2, $calls);
        self::assertSame([100], $delays);
    }

    public function testBusinessApiErrorsAreReturnedWithoutRetry(): void
    {
        $delays = [];
        $calls = 0;
        $response = new Response(200, [], '{"success":false,"errors":["Invalid CityRef"]}');
        self::assertSame($response, $this->service($delays)->execute(function () use (&$calls, $response) { ++$calls; return $response; }));
        self::assertSame(1, $calls);
        self::assertSame([], $delays);
    }

    public function testOtherHttpErrorsCannotBeAcceptedAsSuccessfulSnapshots(): void
    {
        $delays = [];
        $this->expectException(LocalizedException::class);
        $this->service($delays)->execute(fn () => new Response(500, [], '{"success":true,"data":[]}'));
    }

    public function testMixedTransportErrorsAndRateLimitsShareOneAttemptBudget(): void
    {
        $delays = [];
        $calls = 0;
        try {
            $this->service($delays)->execute(function () use (&$calls) {
                if (++$calls % 2 === 0) { throw new \Magento\Framework\HTTP\AsyncClient\HttpException('transport failure'); }
                return new Response(429, [], '');
            });
            self::fail('Mixed failures exceeded their attempt budget');
        } catch (RateLimitException $e) {}
        self::assertSame(5, $calls);
        self::assertCount(4, $delays);
    }

    public function testTransportFailureCanRecoverWithinTheSameBudget(): void
    {
        $delays = [];
        $calls = 0;
        $success = new Response(200, [], '{}');
        self::assertSame($success, $this->service($delays)->execute(function () use (&$calls, $success) {
            if (++$calls === 1) { throw new \Magento\Framework\HTTP\AsyncClient\HttpException('temporary failure'); }
            return $success;
        }));
        self::assertSame(2, $calls);
        self::assertCount(1, $delays);
    }

    private function service(array &$delays): RateLimitRetry
    {
        $delay = $this->createStub(RetryDelay::class);
        $delay->method('wait')->willReturnCallback(function ($seconds) use (&$delays) { $delays[] = $seconds; });
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(1760000000);
        return new RateLimitRetry($delay, $clock, $this->createStub(LoggerInterface::class));
    }
}
