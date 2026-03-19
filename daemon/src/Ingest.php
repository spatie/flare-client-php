<?php

namespace Spatie\FlareDaemon;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use Spatie\FlareDaemon\Support\Output;
use Throwable;

use function React\Promise\resolve;

class Ingest
{
    /** @var array<string, array<string, Buffer>> */
    protected array $buffers = [];

    protected ?TimerInterface $maintenanceTimer = null;

    protected bool $shuttingDown = false;

    protected int $inFlight = 0;

    /** @var array<int, callable(): void> */
    protected array $drainCallbacks = [];

    protected QuotaState $quotaState;

    /** @var array<string, int> */
    protected array $forwardedSinceLastSummary = [];

    protected float $lastSummaryAt;

    public function __construct(
        protected LoopInterface $loop,
        protected Upstream $upstream,
        protected Output $output,
        ?QuotaState $quotaState = null,
        protected int $byteThreshold = 262144,
        protected float $flushAfterSeconds = 10.0,
        protected float $maintenanceIntervalSeconds = 1.0,
        protected int $defaultRetryAfterSeconds = 60,
        protected float $summaryIntervalSeconds = 10.0,
    ) {
        $this->quotaState = $quotaState ?? new QuotaState();
        $this->lastSummaryAt = microtime(true);
        $this->maintenanceTimer = $this->loop->addPeriodicTimer(
            $this->maintenanceIntervalSeconds,
            fn () => $this->maintain(),
        );
    }

    public function isShuttingDown(): bool
    {
        return $this->shuttingDown;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public function accept(string $apiKey, string $type, array $payload): void
    {
        if ($this->shuttingDown) {
            return;
        }

        $now = microtime(true);

        if ($this->quotaState->isPaused($apiKey, $type, $now)) {
            return;
        }

        $this->output->debug('payload accepted', [
            'api_key' => $apiKey,
            'type' => $type,
        ]);

        $this->buffer($apiKey, $type)->add($payload, $now);

        // Flush immediately — no batch API yet. When batching arrives,
        // gate this behind shouldFlushBySize() again.
        $this->scheduleFlush($apiKey, $type);
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return PromiseInterface<array{status: int, body: mixed, headers: array<string, string>}>
     */
    public function diagnose(string $apiKey, string $type, array $payload): PromiseInterface
    {
        if ($this->shuttingDown) {
            return resolve($this->result(503, ['message' => 'Daemon is shutting down']));
        }

        return $this->upstream->send($apiKey, $type, $payload)->then(
            fn (array $response) => $this->diagnosticResult($response),
            function (Throwable $throwable): array {
                $this->output->error('upstream diagnostic request failed', [
                    'exception' => $throwable,
                ]);

                return $this->result(502, ['message' => 'Upstream request failed']);
            },
        );
    }

    public function shutdown(?callable $onDrained = null): void
    {
        if ($onDrained !== null) {
            $this->drainCallbacks[] = $onDrained;
        }

        if ($this->shuttingDown) {
            $this->checkForDrain();

            return;
        }

        $this->shuttingDown = true;
        $this->logForwardedSummary();

        if ($this->maintenanceTimer !== null) {
            $this->loop->cancelTimer($this->maintenanceTimer);
            $this->maintenanceTimer = null;
        }

        foreach ($this->buffers as $apiKey => $typedBuffers) {
            foreach (array_keys($typedBuffers) as $type) {
                $this->scheduleFlush($apiKey, $type);
            }
        }

        $this->checkForDrain();
    }

    /**
     * @return array{keys: array<string, array<string, array{buffered: int, paused: bool, retry_after: string|null, last_429_reason: string|null}>>|object}
     */
    public function status(): array
    {
        $now = microtime(true);
        $keys = array_unique([
            ...array_keys($this->buffers),
            ...$this->quotaState->keys(),
        ]);

        $status = [];

        foreach ($keys as $apiKey) {
            foreach (QuotaState::ENTITY_TYPES as $type) {
                $buffer = $this->buffers[$apiKey][$type] ?? null;

                $status['keys'][$apiKey][$type] = [
                    'buffered' => $buffer?->count() ?? 0,
                    'paused' => $this->quotaState->isPaused($apiKey, $type, $now),
                    'retry_after' => $this->quotaState->retryAfter($apiKey, $type, $now),
                    'last_429_reason' => $this->quotaState->reason($apiKey, $type),
                ];
            }
        }

        return $status === [] ? ['keys' => new \stdClass()] : $status;
    }

    protected function maintain(): void
    {
        $now = microtime(true);

        foreach ($this->quotaState->resumeExpired($now) as $resumed) {
            $this->output->info('quota pause resumed', $resumed);
        }

        foreach ($this->buffers as $apiKey => $typedBuffers) {
            foreach ($typedBuffers as $type => $buffer) {
                if (! $buffer->hasItems()) {
                    continue;
                }

                $oldestAge = $buffer->oldestAge($now);

                if ($buffer->shouldFlushBySize() || ($oldestAge !== null && $oldestAge >= $this->flushAfterSeconds)) {
                    $this->flush($apiKey, $type);
                }
            }
        }

        if ($this->forwardedSinceLastSummary !== [] && $now - $this->lastSummaryAt >= $this->summaryIntervalSeconds) {
            $this->logForwardedSummary();
            $this->lastSummaryAt = $now;
        }

        $this->checkForDrain();
    }

    protected function scheduleFlush(string $apiKey, string $type): void
    {
        $this->loop->futureTick(fn () => $this->flush($apiKey, $type));
    }

    protected function flush(string $apiKey, string $type): void
    {
        $buffer = $this->buffers[$apiKey][$type] ?? null;

        if ($buffer === null || ! $buffer->hasItems() || $buffer->isFlushing()) {
            $this->checkForDrain();

            return;
        }

        $now = microtime(true);

        if ($this->quotaState->isPaused($apiKey, $type, $now)) {
            $buffer->drain();
            $this->cleanupBuffer($apiKey, $type);
            $this->checkForDrain();

            return;
        }

        $item = $buffer->peek();

        if ($item === null) {
            $this->cleanupBuffer($apiKey, $type);
            $this->checkForDrain();

            return;
        }

        $buffer->markFlushing(true);
        $this->inFlight++;

        $this->upstream->send($apiKey, $type, $item['payload'])->then(
            fn (array $response) => $this->completeSuccessfulSend($apiKey, $type, $response),
            fn (Throwable $throwable) => $this->completeFailedSend($apiKey, $type, $throwable),
        );
    }

    /**
     * @param array{status: int, body: mixed, headers: array<string, array<int, string>>} $response
     */
    protected function completeSuccessfulSend(string $apiKey, string $type, array $response): void
    {
        $buffer = $this->buffers[$apiKey][$type] ?? null;
        $item = $buffer?->shift();

        if ($buffer !== null) {
            $buffer->markFlushing(false);
        }

        $this->inFlight--;

        if ($item === null) {
            $this->cleanupBuffer($apiKey, $type);
            $this->checkForDrain();

            return;
        }

        $status = $response['status'];
        $body = $response['body'];

        if ($status === 429) {
            $reason = Upstream::reasonFromResponseBody($body, $status);
            $retryAfter = $this->parseRetryAfter($response['headers'], microtime(true));

            $this->quotaState->pause($apiKey, $type, $retryAfter, $reason);
            $this->output->warning('upstream request paused by quota', [
                'api_key' => $apiKey,
                'type' => $type,
                'reason' => $reason,
                'retry_after' => $retryAfter === null ? null : gmdate(DATE_ATOM, (int) $retryAfter),
            ]);
        } elseif ($status === 403) {
            $reason = Upstream::reasonFromResponseBody($body, $status);
            $this->quotaState->pauseAll($apiKey, $reason);
            $this->output->error('upstream rejected api key', [
                'api_key' => $apiKey,
                'reason' => $reason,
            ]);
        } elseif ($status === 422) {
            $this->output->warning('upstream validation failed', [
                'api_key' => $apiKey,
                'type' => $type,
                'body' => Upstream::summarizeBody($body),
            ]);
        } elseif ($status < 200 || $status >= 300) {
            $this->output->error('upstream request failed', [
                'api_key' => $apiKey,
                'type' => $type,
                'status' => $status,
                'body' => Upstream::summarizeBody($body),
            ]);
        } else {
            $this->forwardedSinceLastSummary[$type] = ($this->forwardedSinceLastSummary[$type] ?? 0) + 1;
            $this->output->debug('payload forwarded upstream', [
                'api_key' => $apiKey,
                'type' => $type,
                'status' => $status,
            ]);
        }

        if (($this->buffers[$apiKey][$type] ?? null)?->hasItems()) {
            $this->scheduleFlush($apiKey, $type);
        } else {
            $this->cleanupBuffer($apiKey, $type);
        }

        $this->checkForDrain();
    }

    protected function completeFailedSend(string $apiKey, string $type, Throwable $throwable): void
    {
        $buffer = $this->buffers[$apiKey][$type] ?? null;
        $buffer?->shift();

        if ($buffer !== null) {
            $buffer->markFlushing(false);
        }

        $this->inFlight--;

        $this->output->error('upstream request failed', [
            'api_key' => $apiKey,
            'type' => $type,
            'exception' => $throwable,
        ]);

        if (($this->buffers[$apiKey][$type] ?? null)?->hasItems()) {
            $this->scheduleFlush($apiKey, $type);
        } else {
            $this->cleanupBuffer($apiKey, $type);
        }

        $this->checkForDrain();
    }

    /**
     * @param array<string, array<int, string>> $headers
     */
    protected function parseRetryAfter(array $headers, float $now): ?float
    {
        $header = $headers['Retry-After'][0] ?? $headers['retry-after'][0] ?? null;

        if ($header === null) {
            return $now + $this->defaultRetryAfterSeconds;
        }

        if (is_numeric($header)) {
            return $now + (float) $header;
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? $now + $this->defaultRetryAfterSeconds : (float) $timestamp;
    }

    protected function logForwardedSummary(): void
    {
        if ($this->forwardedSinceLastSummary === []) {
            return;
        }

        $total = array_sum($this->forwardedSinceLastSummary);
        $label = $total === 1 ? 'payload' : 'payloads';

        $this->output->info("forwarded {$total} {$label} upstream", $this->forwardedSinceLastSummary);

        $this->forwardedSinceLastSummary = [];
    }

    protected function cleanupBuffer(string $apiKey, string $type): void
    {
        if (($this->buffers[$apiKey][$type] ?? null)?->hasItems() === true) {
            return;
        }

        unset($this->buffers[$apiKey][$type]);

        if (($this->buffers[$apiKey] ?? []) === []) {
            unset($this->buffers[$apiKey]);
        }
    }

    protected function buffer(string $apiKey, string $type): Buffer
    {
        return $this->buffers[$apiKey][$type] ??= new Buffer($apiKey, $type, $this->byteThreshold);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: mixed, headers: array<string, string>}
     */
    protected function result(int $status, mixed $body, array $headers = []): array
    {
        return [
            'status' => $status,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    /**
     * @param array{status: int, body: mixed, headers: array<string, array<int, string>>} $response
     *
     * @return array{status: int, body: array{upstream_status: int, reason: string, body: mixed, headers: array<string, string>}, headers: array<string, string>}
     */
    protected function diagnosticResult(array $response): array
    {
        $status = $response['status'];
        $body = $response['body'];

        return $this->result(200, [
            'upstream_status' => $status,
            'reason' => Upstream::reasonFromResponseBody($body, $status),
            'body' => $body,
            'headers' => $this->diagnosticHeaders($response['headers']),
        ], ['Content-Type' => 'application/json']);
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @return array<string, string>
     */
    protected function diagnosticHeaders(array $headers): array
    {
        $selectedHeaders = [];

        foreach (['Retry-After', 'retry-after'] as $name) {
            $value = $headers[$name][0] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $selectedHeaders['Retry-After'] = $value;
        }

        return $selectedHeaders;
    }

    protected function checkForDrain(): void
    {
        if (! $this->shuttingDown || $this->inFlight !== 0) {
            return;
        }

        foreach ($this->buffers as $typedBuffers) {
            foreach ($typedBuffers as $buffer) {
                if ($buffer->hasItems()) {
                    return;
                }
            }
        }

        foreach ($this->drainCallbacks as $callback) {
            $callback();
        }

        $this->drainCallbacks = [];
    }
}
