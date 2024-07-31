<?php

use Illuminate\Support\Arr;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QuerySpan;
use Spatie\FlareClient\Time\Duration;
use Spatie\FlareClient\Time\SystemTime;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('can trace queries', function () {
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: true,
        reportQueries: false,
        maxReportedQueries: null,
        includeBindings: true,
        findQueryOrigin: false,
        findQueryOriginThreshold: null
    );

    $tracer->startTrace();

    $recorder->record('select * from users where id = ?', Duration::milliseconds(300), ['id' => 1], 'users', 'mysql');

    expect($tracer->currentTrace())->toHaveCount(1);

    $span = reset($tracer->currentTrace());

    expect($span)
        ->toBeInstanceOf(QuerySpan::class)
        ->spanId->not()->toBeNull()
        ->traceId->toBe($tracer->currentTraceId())
        ->parentSpanId->toBeNull()
        ->startUs->toBe(1546346096000 - Duration::milliseconds(300))
        ->endUs->toBe(1546346096000)
        ->name->toBe('Query - select * from users where id = ?');

    expect($span->attributes)
        ->toHaveCount(5)
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('db.statement', 'select * from users where id = ?')
        ->toHaveKey('db.sql.bindings', ['id' => 1])
        ->toHaveKey('flare.span_type', SpanType::Query);
});

it('can report queries without tracing', function () {
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: false,
        reportQueries: true,
        maxReportedQueries: null,
        includeBindings: true,
        findQueryOrigin: false,
        findQueryOriginThreshold: null
    );

    $recorder->record('select * from users where id = ?', Duration::milliseconds(300), ['id' => 1], 'users', 'mysql');

    expect($recorder->getSpans())->toHaveCount(1);

    $span = $recorder->getSpans()[0];

    expect($span)
        ->toBeInstanceOf(QuerySpan::class)
        ->spanId->not()->toBeNull()
        ->traceId->toBe('')
        ->parentSpanId->toBeNull()
        ->startUs->toBe(1546346096000 - Duration::milliseconds(300))
        ->endUs->toBe(1546346096000)
        ->name->toBe('Query - select * from users where id = ?');

    expect($span->attributes)
        ->toHaveCount(5)
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('db.statement', 'select * from users where id = ?')
        ->toHaveKey('db.sql.bindings', ['id' => 1])
        ->toHaveKey('flare.span_type', SpanType::Query);
});

it('can disable the inclusion of bindings', function () {
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: false,
        reportQueries: true,
        maxReportedQueries: null,
        includeBindings: false,
        findQueryOrigin: false,
        findQueryOriginThreshold: null
    );

    $tracer->startTrace();

    $recorder->record('select * from users where id = ?', Duration::milliseconds(300), ['id' => 1], 'users', 'mysql');

    expect($recorder->getSpans()[0]->attributes)->not()->toHaveKey('db.sql.bindings');
});

it('can find the origin of a query when tracing and a threshold is met', function (){
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: true,
        reportQueries: false,
        maxReportedQueries: null,
        includeBindings: true,
        findQueryOrigin: true,
        findQueryOriginThreshold: Duration::milliseconds(300)
    );

    $tracer->startTrace();

    $recorder->record('select * from users where id = ?', Duration::milliseconds(300), ['id' => 1], 'users', 'mysql');

    expect($tracer->currentTrace())->toHaveCount(1);

    $span = reset($tracer->currentTrace());

    expect($span->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('can find the origin of a query when tracing no threshold is set', function (){
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: true,
        reportQueries: false,
        maxReportedQueries: null,
        includeBindings: true,
        findQueryOrigin: true,
        findQueryOriginThreshold: null
    );

    $tracer->startTrace();

    $recorder->record('select * from users where id = ?', Duration::milliseconds(300), ['id' => 1], 'users', 'mysql');

    expect($tracer->currentTrace())->toHaveCount(1);

    $span = reset($tracer->currentTrace());

    expect($span->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not find the origin of a query when tracing and a threshold is not met', function (){
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: true,
        reportQueries: false,
        maxReportedQueries: null,
        includeBindings: true,
        findQueryOrigin: true,
        findQueryOriginThreshold: Duration::milliseconds(300)
    );

    $tracer->startTrace();

    $recorder->record('select * from users where id = ?', 99, ['id' => 1], 'users', 'mysql');

    expect($tracer->currentTrace())->toHaveCount(1);

    $span = reset($tracer->currentTrace());

    expect($span->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not find the origin of a query when only reporting', function (){
    $recorder = new QueryRecorder(
        $tracer = setupFlare()->tracer,
        traceQueries: false,
        reportQueries: true,
        maxReportedQueries: null,
        includeBindings: true,
        findQueryOrigin: true,
        findQueryOriginThreshold: Duration::milliseconds(300)
    );

    $recorder->record('select * from users where id = ?', Duration::milliseconds(299), ['id' => 1], 'users', 'mysql');

    expect($recorder->getSpans())->toHaveCount(1);

    $span = $recorder->getSpans()[0];

    expect($span->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});
