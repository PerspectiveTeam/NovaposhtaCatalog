<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Test\Unit\Service\HTTP;

use Magento\Framework\HTTP\AsyncClient\HttpResponseDeferredInterface;
use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\HTTP\AsyncClientInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Service\HTTP\Post;
use Perspective\NovaposhtaCatalog\Service\HTTP\RateLimitRetry;
use Perspective\NovaposhtaCatalog\Service\HTTP\RetryDelay;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PostTest extends TestCase
{
    public function testRetriesIdenticalPageWithoutLeakingApiKeyIntoLogs(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('getApiKey')->willReturn('unit-test-api-key');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning')->willReturnCallback(function ($message, $context) {
            self::assertStringNotContainsString('unit-test-api-key', json_encode([$message, $context]));
            self::assertSame(['attempt', 'delay_seconds', 'http_status'], array_keys($context));
        });
        $delay = $this->createMock(RetryDelay::class);
        $delay->expects(self::exactly(2))->method('wait');
        $retry = new RateLimitRetry($delay, $this->createStub(DateTime::class), $logger);
        $success = new Response(200, [], '{"success":true,"data":[]}');
        $responses = [new Response(429, [], ''), new Response(200, [], '{"success":false,"errors":["To many requests"]}'), $success];
        $bodies = [];
        $client = $this->createMock(AsyncClientInterface::class);
        $client->expects(self::exactly(3))->method('request')->willReturnCallback(function ($request) use (&$responses, &$bodies) {
            $bodies[] = $request->getBody();
            $deferred = $this->createStub(HttpResponseDeferredInterface::class);
            $deferred->method('get')->willReturn(array_shift($responses));
            return $deferred;
        });
        $post = new Post($logger, new Json(), $config, $client, $retry);
        self::assertSame($success, $post->getResponse('Address', 'getWarehouses', [
            'methodProperties' => ['CityRef' => 'city-a', 'Page' => 3, 'Limit' => 100],
        ]));
        self::assertCount(1, array_unique($bodies));
        self::assertSame(3, json_decode($bodies[0], true)['methodProperties']['Page']);
    }
}
