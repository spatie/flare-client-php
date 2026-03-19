<?php

use React\Http\Message\Response;

it('pauses a key and type after a 429 response and resumes after retry after', function () {
    $responseCount = 0;

    $upstream = createUpstreamFixture(function () use (&$responseCount) {
        $responseCount++;

        return match ($responseCount) {
            1 => new Response(429, ['Retry-After' => '1', 'Content-Type' => 'text/plain'], 'Trace quota exceeded'),
            default => new Response(201, ['Content-Type' => 'application/json'], '{"ok":true}'),
        };
    });

    $daemon = createDaemonFixture($upstream['base_url'], [
        'flush_after' => 0.01,
        'default_retry_after' => 1,
    ]);

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/traces',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['trace' => 1]),
    ));

    waitUntil(fn () => $upstream['requests']->count() === 1);
    waitUntil(function () use ($daemon) {
        $statusResponse = \React\Async\await($daemon['client']->get($daemon['daemon_url'].'/status'));
        $statusBody = json_decode((string) $statusResponse->getBody(), true);

        return $statusBody['keys']['api-key']['traces']['paused'] ?? false;
    });

    $statusWhilePaused = \React\Async\await($daemon['client']->get($daemon['daemon_url'].'/status'));
    $pausedBody = json_decode((string) $statusWhilePaused->getBody(), true);

    expect($pausedBody['keys']['api-key']['traces']['paused'])->toBeTrue()
        ->and($pausedBody['keys']['api-key']['traces']['last_429_reason'])->toBe('Trace quota exceeded');

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/traces',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['trace' => 2]),
    ));

    waitFor(1.05);

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/traces',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['trace' => 3]),
    ));

    waitFor(0.05);

    expect($upstream['requests'])->toHaveCount(2)
        ->and(upstreamBody($upstream['requests'], 0))->toBe(['trace' => 1])
        ->and(upstreamBody($upstream['requests'], 1))->toBe(['trace' => 3]);
});

it('keeps permanent pause state for normal payloads while still allowing diagnostic test requests', function () {
    $upstream = createUpstreamFixture(fn () => new Response(403, ['Content-Type' => 'text/plain'], 'Invalid API key'));
    $daemon = createDaemonFixture($upstream['base_url'], ['flush_after' => 0.01]);

    \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/errors',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
        ],
        encodePayload(['message' => 'normal']),
    ));

    waitFor(0.05);

    $testResponse = \React\Async\await($daemon['client']->post(
        $daemon['daemon_url'].'/v1/errors',
        [
            'Content-Type' => 'application/json',
            'X-API-Token' => 'api-key',
            'X-Flare-Test' => '1',
        ],
        encodePayload(['message' => 'test']),
    ));

    $statusResponse = \React\Async\await($daemon['client']->get($daemon['daemon_url'].'/status'));
    $statusBody = json_decode((string) $statusResponse->getBody(), true);

    expect($testResponse->getStatusCode())->toBe(200)
        ->and(json_decode((string) $testResponse->getBody(), true))->toBe([
            'upstream_status' => 403,
            'reason' => 'Invalid API key',
            'body' => 'Invalid API key',
            'headers' => [],
        ])
        ->and($statusBody['keys']['api-key']['errors']['paused'])->toBeTrue()
        ->and($statusBody['keys']['api-key']['traces']['paused'])->toBeTrue()
        ->and($statusBody['keys']['api-key']['logs']['paused'])->toBeTrue();
});
