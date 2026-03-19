<?php

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Http\Message\Response;
use Spatie\FlareDaemon\Ingest;
use Spatie\FlareDaemon\QuotaState;
use Spatie\FlareDaemon\Server;
use Spatie\FlareDaemon\Support\Output;
use Spatie\FlareDaemon\Upstream;

/**
 * @param array{verbose?: bool, byte_threshold?: int, flush_after?: float, maintenance_interval?: float, default_retry_after?: int, summary_interval?: float} $options
 *
 * @return array{daemon_url: string, client: Browser, ingest: Ingest, server: Server, stdout: resource, stderr: resource}
 */
function createDaemonFixtureWithCapture(string $upstreamBaseUrl, array $options = []): array
{
    $address = freeLocalAddress();

    $stdout = fopen('php://temp', 'w+');
    $stderr = fopen('php://temp', 'w+');
    assert(is_resource($stdout));
    assert(is_resource($stderr));

    $output = new Output($stdout, $stderr, verbose: $options['verbose'] ?? false);
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
        summaryIntervalSeconds: $options['summary_interval'] ?? 0.05,
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
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

it('logs a periodic summary of forwarded payloads', function () {
    $upstream = createUpstreamFixture(fn () => new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}'));
    $daemon = createDaemonFixtureWithCapture($upstream['base_url'], [
        'summary_interval' => 0.05,
        'maintenance_interval' => 0.01,
    ]);

    // Send a few payloads
    for ($i = 0; $i < 3; $i++) {
        \React\Async\await($daemon['client']->post(
            $daemon['daemon_url'].'/v1/errors',
            [
                'Content-Type' => 'application/json',
                'X-API-Token' => 'api-key',
            ],
            encodePayload(['message' => "error-{$i}"]),
        ));
    }

    // Wait for upstream forwarding + summary interval
    waitUntil(fn () => count($upstream['requests']) >= 3);
    waitFor(0.1);

    $stdout = readStream($daemon['stdout']);

    expect($stdout)->toContain('forwarded 3 payloads upstream')
        ->toContain('"errors":3');
});

it('logs forwarded summary on shutdown', function () {
    $upstream = createUpstreamFixture(fn () => new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}'));
    $daemon = createDaemonFixtureWithCapture($upstream['base_url'], [
        'summary_interval' => 60.0, // Long interval — summary should only fire on shutdown
        'maintenance_interval' => 0.01,
    ]);

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/errors',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['message' => 'before-shutdown']),
    ));

    waitUntil(fn () => count($upstream['requests']) >= 1);

    // Stdout should NOT contain a summary yet (interval hasn't elapsed)
    $stdout = readStream($daemon['stdout']);
    expect($stdout)->not->toContain('forwarded');

    // Trigger shutdown — summary should flush
    $daemon['ingest']->shutdown();

    $stdout = readStream($daemon['stdout']);
    expect($stdout)->toContain('forwarded 1 payload upstream');
});

it('logs individual payloads at debug level in verbose mode', function () {
    $upstream = createUpstreamFixture(fn () => new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}'));
    $daemon = createDaemonFixtureWithCapture($upstream['base_url'], [
        'verbose' => true,
        'maintenance_interval' => 0.01,
    ]);

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/errors',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['message' => 'verbose-test']),
    ));

    waitUntil(fn () => count($upstream['requests']) >= 1);
    waitFor(0.05);

    $stdout = readStream($daemon['stdout']);

    expect($stdout)->toContain('DEBUG')
        ->toContain('payload accepted')
        ->toContain('payload forwarded upstream');
});

it('does not log debug messages when verbose is disabled', function () {
    $upstream = createUpstreamFixture(fn () => new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}'));
    $daemon = createDaemonFixtureWithCapture($upstream['base_url'], [
        'verbose' => false,
        'summary_interval' => 60.0,
        'maintenance_interval' => 0.01,
    ]);

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/errors',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['message' => 'quiet-test']),
    ));

    waitUntil(fn () => count($upstream['requests']) >= 1);
    waitFor(0.05);

    $stdout = readStream($daemon['stdout']);

    expect($stdout)->not->toContain('DEBUG');
    expect($stdout)->not->toContain('payload accepted');
    expect($stdout)->not->toContain('payload forwarded');
});

it('groups forwarded counts by entity type in the summary', function () {
    $upstream = createUpstreamFixture(fn () => new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}'));
    $daemon = createDaemonFixtureWithCapture($upstream['base_url'], [
        'summary_interval' => 0.05,
        'maintenance_interval' => 0.01,
    ]);

    // Send different entity types
    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/errors',
        ['Content-Type' => 'application/json', 'X-API-Token' => 'api-key'],
        encodePayload(['message' => 'error-1']),
    ));

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/traces',
        ['Content-Type' => 'application/json', 'X-API-Token' => 'api-key'],
        encodePayload(['message' => 'trace-1']),
    ));

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/traces',
        ['Content-Type' => 'application/json', 'X-API-Token' => 'api-key'],
        encodePayload(['message' => 'trace-2']),
    ));

    waitUntil(fn () => count($upstream['requests']) >= 3);
    waitFor(0.1);

    $stdout = readStream($daemon['stdout']);

    expect($stdout)->toContain('forwarded 3 payloads upstream')
        ->toContain('"errors":1')
        ->toContain('"traces":2');
});
