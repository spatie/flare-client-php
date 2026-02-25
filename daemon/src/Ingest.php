<?php

namespace Spatie\FlareDaemon;

use Closure;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use Spatie\FlareDaemon\Contracts\Browser;

class Ingest
{
    private const MAX_CONCURRENT_REQUESTS = 5;

    private const FLUSH_INTERVAL = 10.0;

    private const MAX_LOG_BODY_LENGTH = 1005;

    private const VERSION = '0.1.0';

    /** @var array<string, StreamBuffer> */
    private array $realBuffers;

    /** @var array<string, StreamBuffer|NullBuffer> */
    private array $activeBuffers;

    private int $inFlightCount = 0;

    private bool $stopped = false;

    /** @var array<string, array{connection: ConnectionInterface, payloadId: string}> */
    private array $pendingTestConnections = [];

    /** @var array<string, string> */
    private array $testPayloadIds = [];

    private int $nextPayloadId = 0;

    /** @var Closure(string, int): void */
    private Closure $onUsageIncrement;

    /** @var Closure(): void */
    private Closure $onInvalidApiKey;

    /** @var Closure(string): void */
    private Closure $onQuotaExceeded;

    public function __construct(
        private Loop $loop,
        private Browser $browser,
        private OutputWriter $output,
        private string $apiKey,
        private string $baseUrl,
    ) {
        $this->realBuffers = [
            'errors' => new StreamBuffer(),
            'traces' => new StreamBuffer(),
            'logs' => new StreamBuffer(),
        ];

        $this->activeBuffers = $this->realBuffers;

        $this->onUsageIncrement = function (string $type, int $count): void {};
        $this->onInvalidApiKey = function (): void {};
        $this->onQuotaExceeded = function (string $type): void {};
    }

    /** @param Closure(string, int): void $callback */
    public function onUsageIncrement(Closure $callback): void
    {
        $this->onUsageIncrement = $callback;
    }

    /** @param Closure(): void $callback */
    public function onInvalidApiKey(Closure $callback): void
    {
        $this->onInvalidApiKey = $callback;
    }

    /** @param Closure(string): void $callback */
    public function onQuotaExceeded(Closure $callback): void
    {
        $this->onQuotaExceeded = $callback;
    }

    public function startFlushTimers(): void
    {
        $this->loop->addPeriodicTimer(self::FLUSH_INTERVAL, function (): void {
            $this->flushAll();
        });
    }

    public function buffer(string $type, string $data): void
    {
        if ($this->stopped) {
            return;
        }

        $this->activeBuffers[$type]->add($data);

        if ($this->activeBuffers[$type]->shouldFlush()) {
            $this->flush($type);
        }
    }

    public function bufferTest(string $baseType, string $data, ConnectionInterface $connection): void
    {
        $payloadId = $this->generatePayloadId();

        $this->realBuffers[$baseType]->add($data);

        $this->testPayloadIds[$payloadId] = $baseType;
        $this->pendingTestConnections[$payloadId] = [
            'connection' => $connection,
            'payloadId' => $payloadId,
        ];

        $this->flushForTest($baseType, $payloadId, $data);
    }

    public function pauseIngestion(string $type): void
    {
        if ($this->activeBuffers[$type] instanceof NullBuffer) {
            return;
        }

        $this->activeBuffers[$type] = new NullBuffer();

        $this->output->writeLn("Paused ingestion for {$type}");
    }

    public function resumeIngestion(string $type): void
    {
        if (! ($this->activeBuffers[$type] instanceof NullBuffer)) {
            return;
        }

        $this->activeBuffers[$type] = $this->realBuffers[$type];

        $this->output->writeLn("Resumed ingestion for {$type}");
    }

    public function isPaused(string $type): bool
    {
        return $this->activeBuffers[$type] instanceof NullBuffer;
    }

    /**
     * @return PromiseInterface<void>
     */
    public function forceDigest(): PromiseInterface
    {
        $this->stopped = true;

        $this->flushAll();

        return $this->waitForInFlight();
    }

    public function inFlightCount(): int
    {
        return $this->inFlightCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return [
            'buffers' => [
                'errors' => [
                    'count' => $this->activeBuffers['errors']->count(),
                    'size' => $this->activeBuffers['errors']->size(),
                    'paused' => $this->isPaused('errors'),
                ],
                'traces' => [
                    'count' => $this->activeBuffers['traces']->count(),
                    'size' => $this->activeBuffers['traces']->size(),
                    'paused' => $this->isPaused('traces'),
                ],
                'logs' => [
                    'count' => $this->activeBuffers['logs']->count(),
                    'size' => $this->activeBuffers['logs']->size(),
                    'paused' => $this->isPaused('logs'),
                ],
            ],
            'in_flight' => $this->inFlightCount,
        ];
    }

    private function flushAll(): void
    {
        foreach (['errors', 'traces', 'logs'] as $type) {
            if (! $this->activeBuffers[$type]->isEmpty()) {
                $this->flush($type);
            }
        }
    }

    private function flush(string $type): void
    {
        $payloads = $this->activeBuffers[$type]->pull();

        if ($payloads === []) {
            return;
        }

        $this->sendBatch($type, $payloads);
    }

    private function flushForTest(string $baseType, string $testPayloadId, string $testData): void
    {
        $payloads = $this->realBuffers[$baseType]->pull();

        foreach ($payloads as $payload) {
            $isTestPayload = $payload === $testData;

            $this->sendSingle($baseType, $payload, $isTestPayload ? $testPayloadId : null);
        }
    }

    /**
     * @param array<int, string> $payloads
     */
    private function sendBatch(string $type, array $payloads): void
    {
        if ($this->inFlightCount >= self::MAX_CONCURRENT_REQUESTS) {
            foreach ($payloads as $payload) {
                $this->activeBuffers[$type]->add($payload);
            }

            return;
        }

        $combined = '[' . implode(',', $payloads) . ']';
        $payloadCount = count($payloads);

        $this->send($type, $combined, $payloadCount, null);
    }

    private function sendSingle(string $type, string $payload, ?string $testPayloadId): void
    {
        if ($this->inFlightCount >= self::MAX_CONCURRENT_REQUESTS) {
            $this->realBuffers[$type]->add($payload);

            return;
        }

        $combined = '[' . $payload . ']';

        $this->send($type, $combined, 1, $testPayloadId);
    }

    private function send(string $type, string $body, int $payloadCount, ?string $testPayloadId): void
    {
        $this->inFlightCount++;

        $compressed = gzencode($body);

        if ($compressed === false) {
            $this->inFlightCount--;
            $this->output->writeLn("Failed to gzip-compress {$type} payload");

            return;
        }

        $url = "{$this->baseUrl}/v1/{$type}?key={$this->apiKey}";

        $headers = [
            'x-api-token' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Content-Encoding' => 'gzip',
            'Accept' => 'application/json',
            'User-Agent' => 'FlareDaemon/' . self::VERSION,
        ];

        $this->browser->post($url, $headers, $compressed)->then(
            function (ResponseInterface $response) use ($type, $payloadCount, $testPayloadId): void {
                $this->inFlightCount--;
                $this->handleResponse($response, $type, $payloadCount, $testPayloadId);
            },
            function (\Throwable $error) use ($type, $testPayloadId): void {
                $this->inFlightCount--;
                $this->output->writeLn("Failed to send {$type}: {$error->getMessage()}");
                $this->resolveTestPayload($testPayloadId, $type, 503, '{"message":"Upstream error"}');
            }
        );
    }

    private function handleResponse(ResponseInterface $response, string $type, int $payloadCount, ?string $testPayloadId): void
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        $this->resolveTestPayload($testPayloadId, $type, $statusCode, $body);

        if ($statusCode === 201) {
            ($this->onUsageIncrement)($type, $payloadCount);

            return;
        }

        $logBody = strlen($body) > self::MAX_LOG_BODY_LENGTH
            ? substr($body, 0, self::MAX_LOG_BODY_LENGTH) . '...'
            : $body;

        if ($statusCode === 403) {
            $this->output->writeLn("WARNING: Invalid API key — stopping all ingestion. Response: {$logBody}");
            $this->stopAll();
            ($this->onInvalidApiKey)();

            return;
        }

        if ($statusCode === 422) {
            $message = $this->extractMessage($body);

            if ($message === 'Missing API key') {
                $this->output->writeLn("WARNING: Missing API key — stopping all ingestion. Response: {$logBody}");
                $this->stopAll();
                ($this->onInvalidApiKey)();

                return;
            }

            $this->output->writeLn("Validation error for {$type}: {$logBody}");

            return;
        }

        if ($statusCode === 429) {
            $message = $this->extractMessage($body);

            if (str_contains(strtolower($message), 'quota exceeded')) {
                $this->output->writeLn("Quota exceeded for {$type}: {$logBody}");
                $this->pauseIngestion($type);
                ($this->onQuotaExceeded)($type);

                return;
            }

            $this->output->writeLn("Rate limited for {$type}: {$logBody}");

            return;
        }

        $this->output->writeLn("Unexpected response {$statusCode} for {$type}: {$logBody}");
    }

    private function resolveTestPayload(?string $testPayloadId, string $type, int $statusCode, string $body): void
    {
        if ($testPayloadId === null) {
            return;
        }

        if (! isset($this->pendingTestConnections[$testPayloadId])) {
            return;
        }

        $pending = $this->pendingTestConnections[$testPayloadId];
        $connection = $pending['connection'];

        unset($this->pendingTestConnections[$testPayloadId], $this->testPayloadIds[$testPayloadId]);

        $responsePayload = "{$type}:{$statusCode}:{$body}";
        $length = strlen($responsePayload);

        $connection->write("{$length}:{$responsePayload}");
    }

    private function stopAll(): void
    {
        $this->stopped = true;

        foreach (['errors', 'traces', 'logs'] as $type) {
            $this->pauseIngestion($type);
        }
    }

    private function extractMessage(string $body): string
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded['message']) || ! is_string($decoded['message'])) {
            return '';
        }

        return $decoded['message'];
    }

    /**
     * @return PromiseInterface<void>
     */
    private function waitForInFlight(): PromiseInterface
    {
        /** @var Deferred<void> $deferred */
        $deferred = new Deferred();

        if ($this->inFlightCount === 0) {
            $deferred->resolve(null);

            return $deferred->promise();
        }

        $this->loop->addPeriodicTimer(0.1, function (TimerInterface $timer) use ($deferred): void {
            if ($this->inFlightCount === 0) {
                $this->loop->get()->cancelTimer($timer);
                $deferred->resolve(null);
            }
        });

        return $deferred->promise();
    }

    private function generatePayloadId(): string
    {
        return 'test_' . (++$this->nextPayloadId);
    }
}
