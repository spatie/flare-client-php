<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;

it('records a redis command span with OTEL db attributes', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRedisCommands(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->redisCommand()->record(
        command: 'GET',
        parameters: ['key:1'],
        duration: 150_000,
        namespace: 0,
        serverAddress: '127.0.0.1',
        serverPort: 6379,
    );

    expect($span)->not->toBeNull();
    expect($span->name)->toBe('Redis - GET');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::RedisCommand)
        ->toHaveKey('db.system', 'redis')
        ->toHaveKey('db.namespace', 0)
        ->toHaveKey('db.operation.name', 'GET')
        ->toHaveKey('db.query.parameters', ['key:1'])
        ->toHaveKey('network.peer.address', '127.0.0.1')
        ->toHaveKey('network.peer.port', 6379);
});

it('records start and end as separate calls', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRedisCommands(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $start = $flare->redisCommand()->recordStart('SET', ['key:1', 'value']);

    expect($start)->not->toBeNull();
    expect($start->name)->toBe('Redis - SET');
    expect($start->end)->toBeNull();

    $end = $flare->redisCommand()->recordEnd(['db.response.size' => 12]);

    expect($end->end)->not->toBeNull();
    expect($end->attributes)->toHaveKey('db.response.size', 12);
});
