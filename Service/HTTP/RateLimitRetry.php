<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Service\HTTP;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\AsyncClient\Response;
use Magento\Framework\HTTP\AsyncClient\HttpException;
use GuzzleHttp\Exception\TransferException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Perspective\NovaposhtaCatalog\Service\SyncMetrics;

class RateLimitRetry
{
    private const MAX_ATTEMPTS = 6;
    private const MAX_WAIT_SECONDS = 180;

    public function __construct(
        private RetryDelay $delay,
        private DateTime $clock,
        private LoggerInterface $logger,
        private ?SyncMetrics $metrics = null
    ) {
    }

    /** Retry the same read request/page; attempts and cumulative sleeping are bounded. */
    public function execute(callable $request): Response
    {
        $waited = 0;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $response = null;
            $failure = null;
            try {
                $this->metrics?->increment('requests');
                $response = $request();
            } catch (HttpException | TransferException $e) {
                $failure = $e;
            }
            if ($response !== null && !$this->isRateLimited($response)) {
                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    throw new LocalizedException(__('Nova Poshta HTTP request failed with status %1.', $response->getStatusCode()));
                }
                return $response;
            }
            if ($response !== null) {
                $this->metrics?->increment('rate_limits');
                if ($response->getStatusCode() === 429) {
                    $this->metrics?->increment('http_429');
                }
            }
            if ($attempt === self::MAX_ATTEMPTS) {
                if ($failure !== null) {
                    throw new LocalizedException(__('Nova Poshta request failed after %1 attempts.', $attempt), $failure);
                }
                throw new RateLimitException(__('Nova Poshta rate limit persisted after %1 attempts; retry the synchronization later.', $attempt));
            }
            $seconds = $response !== null ? $this->getDelay($response, $attempt) : $this->getBackoff($attempt);
            if ($seconds > self::MAX_WAIT_SECONDS - $waited) {
                throw new RateLimitException(__('Nova Poshta Retry-After exceeds the remaining retry budget; retry the synchronization later.'));
            }
            $this->logger->warning($response !== null ? 'Nova Poshta rate limit; retrying the same request.'
                : 'Nova Poshta transport failure; retrying the same request.', [
                'attempt' => $attempt, 'delay_seconds' => $seconds, 'http_status' => $response?->getStatusCode(),
            ]);
            $this->metrics?->increment('retries');
            if ($this->metrics !== null) {
                $this->metrics->measure('retry_wait', fn () => $this->delay->wait($seconds));
            } else {
                $this->delay->wait($seconds);
            }
            $waited += $seconds;
        }
        throw new \LogicException('Unreachable retry state.');
    }

    private function isRateLimited(Response $response): bool
    {
        if ($response->getStatusCode() === 429) {
            return true;
        }
        $body = json_decode($response->getBody(), true);
        if (!is_array($body) || ($body['success'] ?? null) !== false || !is_array($body['errors'] ?? null)) {
            return false;
        }
        foreach ($body['errors'] as $error) {
            if (is_string($error) && preg_match('/\btoo?\s+many\s+requests\b/i', $error)) {
                return true;
            }
        }
        return false;
    }

    private function getDelay(Response $response, int $attempt): int
    {
        $retryAfter = $response->getHeaders()['retry-after'] ?? null;
        if (is_string($retryAfter)) {
            $retryAfter = trim($retryAfter);
            if (ctype_digit($retryAfter)) {
                $seconds = filter_var(ltrim($retryAfter, '0') ?: '0', FILTER_VALIDATE_INT);
                if ($seconds === false || $seconds > self::MAX_WAIT_SECONDS) {
                    throw new RateLimitException(__('Nova Poshta Retry-After exceeds the retry budget; retry the synchronization later.'));
                }
                return max(1, $seconds);
            }
            $timestamp = strtotime($retryAfter, $this->clock->gmtTimestamp());
            if ($timestamp !== false) {
                return max(1, $timestamp - $this->clock->gmtTimestamp());
            }
        }
        return $this->getBackoff($attempt);
    }

    private function getBackoff(int $attempt): int
    {
        return min(60, 5 * (2 ** ($attempt - 1)));
    }
}
