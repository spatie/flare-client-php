<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionSpan;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;
use Spatie\FlareClient\Time\Duration;

beforeEach(function () {
    useTime('2019-01-01 12:34:56'); // 1546346096000
});

it('can trace a transaction', function () {
    $tracer = setupFlare()->tracer;

    $transactionRecorder = new TransactionRecorder(
        $tracer,
        traceTransactions: true
    );

    $queryRecorder = new QueryRecorder(
        $tracer,
        traceQueries: true,
        reportQueries: true,
        maxReportedQueries: 10,
        includeBindings: true,
        findQueryOrigin: true,
        findQueryOriginThreshold: 0,
    );

    $tracer->startTrace();

    $transactionSpan = $transactionRecorder->recordBegin();

    $querySpan = $queryRecorder->record(
        'select * from users',
        Duration::milliseconds(300),
        bindings: ['id' => 1],
        databaseName: 'mysql',
        driverName: 'mysql',
    );

    useTime('2019-01-01 12:34:57'); // 1546346097000

    $transactionRecorder->recordCommit();

    expect($tracer->currentTrace())->toHaveCount(2);

    expect($transactionSpan)
        ->toBeInstanceOf(TransactionSpan::class)
        ->name->toBe('DB Transaction')
        ->startUs->toBe(1546346096000)
        ->endUs->toBe(1546346097000)
        ->parentSpanId->toBeNull()
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('flare.span_type', SpanType::Transaction);

    expect($querySpan)
        ->parentSpanId->toBe($transactionSpan->spanId);
});

it('can rollback a transaction', function () {
    $tracer = setupFlare()->tracer;

    $transactionRecorder = new TransactionRecorder(
        $tracer,
        traceTransactions: true
    );

    $tracer->startTrace();

    $transactionSpan = $transactionRecorder->recordBegin();

    useTime('2019-01-01 12:34:57'); // 1546346097000

    $transactionRecorder->recordRollback();

    expect($tracer->currentTrace())->toHaveCount(1);

    expect($transactionSpan)
        ->toBeInstanceOf(TransactionSpan::class)
        ->name->toBe('DB Transaction')
        ->startUs->toBe(1546346096000)
        ->endUs->toBe(1546346097000)
        ->parentSpanId->toBeNull()
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('flare.span_type', SpanType::Transaction);
});

it('can nest transaction spans', function () {
    $tracer = setupFlare()->tracer;

    $transactionRecorder = new TransactionRecorder(
        $tracer,
        traceTransactions: true
    );

    $tracer->startTrace();

    $transactionSpanA = $transactionRecorder->recordBegin();

    useTime('2019-01-01 12:34:57'); // 1546346097000

    $transactionSpanB = $transactionRecorder->recordBegin();

    useTime('2019-01-01 12:34:58'); // 1546346098000

    $transactionRecorder->recordCommit();

    useTime('2019-01-01 12:34:59'); // 1546346099000

    $transactionRecorder->recordRollback();

    expect($tracer->currentTrace())->toHaveCount(2);

    expect($transactionSpanA)
        ->startUs->toBe(1546346096000)
        ->endUs->toBe(1546346099000)
        ->parentSpanId->toBeNull();

    expect($transactionSpanB)
        ->startUs->toBe(1546346097000)
        ->endUs->toBe(1546346098000)
        ->parentSpanId->toBe($transactionSpanA->spanId);
});


