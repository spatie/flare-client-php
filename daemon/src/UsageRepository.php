<?php

namespace Spatie\FlareDaemon;

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\TimerInterface;
use Spatie\FlareDaemon\Contracts\Browser;
use Spatie\FlareDaemon\Contracts\Clock;
use Spatie\FlareDaemon\Contracts\LoopContract;

class UsageRepository
{
    private const TYPES = ['errors', 'traces', 'logs'];

    private const DAILY_REFRESH_INTERVAL = 86400.0;

    private const RETRY_INTERVAL_AFTER_SUCCESS = 300.0;

    private const RETRY_INTERVAL_AFTER_MANY_FAILURES = 3600.0;

    private const MAX_CONSECUTIVE_FAILURES_BEFORE_SLOWDOWN = 13;

    /** @var array<int, float> */
    private const INITIAL_BACKOFF_SCHEDULE = [2.5, 5.0, 10.0, 15.0, 30.0, 60.0, 120.0, 240.0];

    private ?Usage $usage = null;

    private bool $hasSucceeded = false;

    private int $consecutiveFailures = 0;

    private int $initialBackoffIndex = 0;

    private int $initialBackoffRepeatCount = 0;

    /** @var array<string, int> */
    private array $localCounters = [
        'errors' => 0,
        'traces' => 0,
        'logs' => 0,
    ];

    public function __construct(
        private LoopContract $loop,
        private Browser $browser,
        private OutputWriter $output,
        private Ingest $ingest,
        private Clock $clock,
        private string $apiKey,
        private string $baseUrl,
    ) {
    }

    public function start(): void
    {
        $this->fetch();

        $this->loop->addPeriodicTimer(self::DAILY_REFRESH_INTERVAL, function (): void {
            $this->fetch();
        });

        $this->ingest->onUsageIncrement(function (string $type, int $count): void {
            $this->incrementLocal($type, $count);
        });

        $this->ingest->onQuotaExceeded(function (string $type): void {
            $this->onQuotaExceeded($type);
        });
    }

    public function incrementLocal(string $type, int $count): void
    {
        $this->localCounters[$type] += $count;

        if ($this->usage === null) {
            return;
        }

        $totalUsed = $this->usage->used($type) + $this->localCounters[$type];

        if ($totalUsed >= $this->usage->limit($type)) {
            $this->ingest->pauseIngestion($type);
            $this->output->writeLn("Local counter reached limit for {$type} ({$totalUsed}/{$this->usage->limit($type)})");
        }
    }

    public function onQuotaExceeded(string $type): void
    {
        $this->ingest->pauseIngestion($type);
        $this->scheduleFetch();
    }

    public function fetch(): void
    {
        $url = "{$this->baseUrl}/v1/usage?key={$this->apiKey}";

        $this->browser->get($url, [
            'x-api-token' => $this->apiKey,
            'Accept' => 'application/json',
        ])->then(
            function (ResponseInterface $response): void {
                $this->handleFetchResponse($response);
            },
            function (\Throwable $error): void {
                $this->handleFetchFailure($error);
            }
        );
    }

    public function usage(): ?Usage
    {
        return $this->usage;
    }

    private function handleFetchResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            $this->output->writeLn("Usage fetch returned {$statusCode}: {$body}");
            $this->handleFetchFailure(new \RuntimeException("HTTP {$statusCode}"));

            return;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            $this->output->writeLn("Usage fetch returned invalid JSON: {$body}");
            $this->handleFetchFailure(new \RuntimeException('Invalid JSON'));

            return;
        }

        /** @var array<string, mixed> $data */
        $data = $decoded;

        $this->hasSucceeded = true;
        $this->consecutiveFailures = 0;
        $this->initialBackoffIndex = 0;
        $this->initialBackoffRepeatCount = 0;

        $this->usage = Usage::fromArray($data);
        $this->localCounters = ['errors' => 0, 'traces' => 0, 'logs' => 0];

        $this->output->writeLn("Usage fetched: errors {$this->usage->errorsUsed}/{$this->usage->errorsLimit}, traces {$this->usage->tracesUsed}/{$this->usage->tracesLimit}, logs {$this->usage->logsUsed}/{$this->usage->logsLimit}");

        $this->applyQuotaLimits();
    }

    private function applyQuotaLimits(): void
    {
        if ($this->usage === null) {
            return;
        }

        foreach (self::TYPES as $type) {
            if ($this->usage->isOverLimit($type)) {
                $this->ingest->pauseIngestion($type);
            } else {
                $this->ingest->resumeIngestion($type);
            }
        }

        if ($this->usage->allOverLimit() && $this->usage->resetAt !== '') {
            $this->scheduleResetAtFetch();
        }
    }

    private function handleFetchFailure(\Throwable $error): void
    {
        $this->consecutiveFailures++;

        $this->output->writeLn("Usage fetch failed: {$error->getMessage()} (attempt {$this->consecutiveFailures})");

        $this->scheduleRetry();
    }

    private function scheduleFetch(): void
    {
        $this->loop->addTimer(0.1, function (): void {
            $this->fetch();
        });
    }

    private function scheduleRetry(): void
    {
        $delay = $this->getRetryDelay();

        $this->loop->addTimer($delay, function (): void {
            $this->fetch();
        });
    }

    private function getRetryDelay(): float
    {
        if ($this->hasSucceeded) {
            return $this->consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES_BEFORE_SLOWDOWN
                ? self::RETRY_INTERVAL_AFTER_MANY_FAILURES
                : self::RETRY_INTERVAL_AFTER_SUCCESS;
        }

        return $this->getInitialBackoffDelay();
    }

    private function getInitialBackoffDelay(): float
    {
        if ($this->initialBackoffIndex < count(self::INITIAL_BACKOFF_SCHEDULE)) {
            $delay = self::INITIAL_BACKOFF_SCHEDULE[$this->initialBackoffIndex];
            $this->initialBackoffIndex++;

            return $delay;
        }

        $this->initialBackoffRepeatCount++;

        if ($this->initialBackoffRepeatCount <= 12) {
            return 300.0;
        }

        return 3600.0;
    }

    private function scheduleResetAtFetch(): void
    {
        if ($this->usage === null || $this->usage->resetAt === '') {
            return;
        }

        $resetTimestamp = strtotime($this->usage->resetAt);

        if ($resetTimestamp === false) {
            return;
        }

        $now = $this->clock->now();
        $delay = (float) $resetTimestamp - $now;

        if ($delay <= 0) {
            $this->scheduleFetch();

            return;
        }

        $this->loop->addTimer($delay, function (): void {
            $this->fetch();
        });
    }
}
