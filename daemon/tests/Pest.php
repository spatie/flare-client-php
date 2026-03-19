<?php

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Http\HttpServer;
use React\Promise\Deferred;
use React\Socket\SocketServer;
use Spatie\FlareDaemon\Ingest;
use Spatie\FlareDaemon\QuotaState;
use Spatie\FlareDaemon\Server;
use Spatie\FlareDaemon\Support\Json;
use Spatie\FlareDaemon\Support\Output;
use Spatie\FlareDaemon\Upstream;

uses()->beforeEach(function () {
    $GLOBALS['flare_daemon_test_closers'] = [];
    $GLOBALS['flare_daemon_test_shutdowns'] = [];
})->afterEach(function () {
    /** @var array<int, callable(): void> $shutdowns */
    $shutdowns = $GLOBALS['flare_daemon_test_shutdowns'];

    foreach (array_reverse($shutdowns) as $shutdown) {
        $shutdown();
    }

    /** @var array<int, object> $closers */
    $closers = $GLOBALS['flare_daemon_test_closers'];

    foreach (array_reverse($closers) as $closer) {
        if (method_exists($closer, 'close')) {
            $closer->close();
        }
    }
})->in(__DIR__);

/** @param array<array-key, mixed> $payload */
function encodePayload(array $payload): string
{
    $encoded = json_encode($payload);

    if (! is_string($encoded)) {
        throw new RuntimeException('Unable to encode test payload');
    }

    return $encoded;
}

function freeLocalAddress(): string
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errorMessage);

    if ($socket === false) {
        throw new RuntimeException($errorMessage ?? 'Unable to open local socket', $errno ?? 0);
    }

    $address = str_replace('tcp://', '', (string) stream_socket_get_name($socket, false));

    fclose($socket);

    return $address;
}

function rememberCloser(object $closer): void
{
    $GLOBALS['flare_daemon_test_closers'][] = $closer;
}

function rememberShutdown(callable $shutdown): void
{
    $GLOBALS['flare_daemon_test_shutdowns'][] = $shutdown;
}

function makeOutput(): Output
{
    $stdout = fopen('php://temp', 'w+');
    $stderr = fopen('php://temp', 'w+');

    return new Output(
        is_resource($stdout) ? $stdout : null,
        is_resource($stderr) ? $stderr : null,
    );
}

/**
 * @return array{output: Output, stdout: resource, stderr: resource}
 */
function makeOutputWithCapture(): array
{
    $stdout = fopen('php://temp', 'w+');
    $stderr = fopen('php://temp', 'w+');

    assert(is_resource($stdout));
    assert(is_resource($stderr));

    return [
        'output' => new Output($stdout, $stderr),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

/**
 * @param resource $stream
 */
function readStream($stream): string
{
    rewind($stream);

    return stream_get_contents($stream) ?: '';
}

/**
 * @param callable(\Psr\Http\Message\ServerRequestInterface): \Psr\Http\Message\ResponseInterface $handler
 *
 * @return array{
 *     base_url: string,
 *     requests: ArrayObject<int, array{
 *         method: string,
 *         path: string,
 *         headers: array<string, array<int, string>>,
 *         body: array<array-key, mixed>|null
 *     }>
 * }
 */
function createUpstreamFixture(callable $handler): array
{
    /** @var ArrayObject<int, array{method: string, path: string, headers: array<string, array<int, string>>, body: array<array-key, mixed>|null}> $requests */
    $requests = new ArrayObject();
    $address = freeLocalAddress();

    $server = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($handler, $requests) {
        $body = (string) $request->getBody();

        if ($request->getHeaderLine('content-encoding') === 'gzip') {
            $body = gzdecode($body) ?: '';
        }

        $requests->append([
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'headers' => $request->getHeaders(),
            'body' => $body === '' ? null : Json::decode($body),
        ]);

        return $handler($request);
    });

    $socket = new SocketServer($address, [], Loop::get());
    $server->listen($socket);

    rememberCloser($server);
    rememberCloser($socket);

    return [
        'base_url' => 'http://'.$address,
        'requests' => $requests,
    ];
}

/**
 * @param array{byte_threshold?: int, flush_after?: float, maintenance_interval?: float, default_retry_after?: int} $options
 *
 * @return array{daemon_url: string, client: Browser, ingest: Ingest, server: Server, quota_state: QuotaState}
 */
function createDaemonFixture(string $upstreamBaseUrl, array $options = []): array
{
    $address = freeLocalAddress();
    $output = makeOutput();
    $quotaState = new QuotaState();
    $browser = (new Browser())
        ->withRejectErrorResponse(false)
        ->withTimeout(1.0);

    $upstream = new Upstream($browser, $upstreamBaseUrl, 'FlareDaemon/tests');
    $ingest = new Ingest(
        loop: Loop::get(),
        upstream: $upstream,
        output: $output,
        quotaState: $quotaState,
        byteThreshold: $options['byte_threshold'] ?? 256,
        flushAfterSeconds: $options['flush_after'] ?? 0.05,
        maintenanceIntervalSeconds: $options['maintenance_interval'] ?? 0.01,
        defaultRetryAfterSeconds: $options['default_retry_after'] ?? 1,
    );

    $server = new Server(
        loop: Loop::get(),
        ingest: $ingest,
        output: $output,
        listenAddress: $address,
    );

    $server->listen();

    rememberShutdown(fn () => $ingest->shutdown());
    rememberCloser($server);

    return [
        'daemon_url' => 'http://'.$address,
        'client' => $browser,
        'ingest' => $ingest,
        'server' => $server,
        'quota_state' => $quotaState,
    ];
}

function waitFor(float $seconds): void
{
    $deferred = new Deferred();
    Loop::addTimer($seconds, fn () => $deferred->resolve(true));

    \React\Async\await($deferred->promise());
}

function waitUntil(callable $condition, float $timeout = 1.0, float $interval = 0.01): void
{
    $deadline = microtime(true) + $timeout;

    do {
        if ($condition()) {
            return;
        }

        waitFor($interval);
    } while (microtime(true) < $deadline);

    expect($condition())->toBeTrue();
}

/**
 * @param ArrayObject<int, array{method: string, path: string, headers: array<string, array<int, string>>, body: array<array-key, mixed>|null}> $requests
 *
 * @return array<array-key, mixed>
 */
function upstreamBody(ArrayObject $requests, int $index): array
{
    $request = $requests[$index] ?? null;

    expect($request)->toBeArray();
    expect($request['body'] ?? null)->toBeArray();

    /** @var array<array-key, mixed> $body */
    $body = $request['body'];

    return $body;
}

/**
 * @param ArrayObject<int, array{method: string, path: string, headers: array<string, array<int, string>>, body: array<array-key, mixed>|null}> $requests
 */
function upstreamPath(ArrayObject $requests, int $index): string
{
    $request = $requests[$index] ?? null;

    expect($request)->toBeArray();
    assert(is_array($request));

    return $request['path'];
}
