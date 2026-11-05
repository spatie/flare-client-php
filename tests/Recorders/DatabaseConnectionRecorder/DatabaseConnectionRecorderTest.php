<?php

use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('can trace database connections without adding them to error reports', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(), alwaysSampleTraces: true);

    $recorder = $flare->databaseConnection();

    $flare->tracer->startTrace();

    $recorder->recordStart('mysql', 'users', ['laravel.db.connection' => 'mysql']);

    FakeTime::setCurrentTime('2019-01-01 12:34:57');

    $recorder->recordEnd();

    expect($flare->tracer->currentTrace())->toHaveCount(1);
    expect($recorder->getSpans())->toHaveCount(0);

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Database connection')
        ->start->toBe(1546346096000000000)
        ->end->toBe(1546346097000000000);

    expect($span->attributes)
        ->toHaveCount(4)
        ->toHaveKey('flare.span_type', SpanType::DatabaseConnection)
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('laravel.db.connection', 'mysql');
});

it('can trace failed database connections', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries(), alwaysSampleTraces: true);

    $recorder = $flare->databaseConnection();

    $flare->tracer->startTrace();

    $recorder->recordStart('mysql', 'users');
    $recorder->recordFailed();

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span->end)->not()->toBeNull();
    expect($span->status?->code)->toBe(SpanStatusCode::Error);
    expect($span->status?->message)->toBeNull();
});
