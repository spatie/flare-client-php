<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Enums\TransactionStatus;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\FlareClient\Recorders\TransactionRecorder\TransactionSpan;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Time\TimeHelper;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56'); // 1546346096000
});

it('can trace a transaction', function () {
    $flare = setupFlare();

    $transactionRecorder = new TransactionRecorder(
        $flare->tracer,
        $flare->backTracer,
        config: ['with_traces' => true]
    );

    $queryRecorder = new QueryRecorder(
        $flare->tracer,
        $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'include_bindings' => true,
            'find_origin' => true,
            'find_origin_threshold' => 0,
        ],
    );

    $flare->tracer->startTrace();

    $transactionSpan = $transactionRecorder->recordBegin();

    $querySpan = $queryRecorder->record(
        'select * from users',
        TimeHelper::milliseconds(300),
        bindings: ['id' => 1],
        databaseName: 'mysql',
        driverName: 'mysql',
    );

    FakeTime::setup('2019-01-01 12:34:57'); // 1546346097000

    $transactionRecorder->recordCommit();

    expect($flare->tracer->currentTrace())->toHaveCount(2);

    expect($transactionSpan)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('DB Transaction')
        ->start->toBe(1546346096000000000)
        ->end->toBe(1546346097000000000)
        ->parentSpanId->toBeNull()
        ->attributes
        ->toHaveCount(2)
        ->toHaveKey('flare.span_type', SpanType::Transaction)
        ->toHaveKey('db.transaction.status', TransactionStatus::Committed);

    expect($querySpan)
        ->parentSpanId->toBe($transactionSpan->spanId);
});

it('can rollback a transaction', function () {
    $flare = setupFlare();

    $transactionRecorder = new TransactionRecorder(
        $flare->tracer,
        $flare->backTracer,
        config: ['with_traces' => true]
    );

    $flare->tracer->startTrace();

    $transactionSpan = $transactionRecorder->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // 1546346097000

    $transactionRecorder->recordRollback();

    expect($flare->tracer->currentTrace())->toHaveCount(1);

    expect($transactionSpan)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('DB Transaction')
        ->start->toBe(1546346096000000000)
        ->end->toBe(1546346097000000000)
        ->parentSpanId->toBeNull()
        ->attributes
        ->toHaveCount(2)
        ->toHaveKey('flare.span_type', SpanType::Transaction)
        ->toHaveKey('db.transaction.status', TransactionStatus::RolledBack);
});

it('can nest transaction spans', function () {
    $flare = setupFlare();

    $transactionRecorder = new TransactionRecorder(
        $flare->tracer,
        $flare->backTracer,
        config: ['with_traces' => true]
    );

    $flare->tracer->startTrace();

    $transactionSpanA = $transactionRecorder->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // 1546346097000

    $transactionSpanB = $transactionRecorder->recordBegin();

    FakeTime::setup('2019-01-01 12:34:58'); // 1546346098000

    $transactionRecorder->recordCommit();

    FakeTime::setup('2019-01-01 12:34:59'); // 1546346099000

    $transactionRecorder->recordRollback();

    expect($flare->tracer->currentTrace())->toHaveCount(2);

    expect($transactionSpanA)
        ->start->toBe(1546346096000000000)
        ->end->toBe(1546346099000000000)
        ->parentSpanId->toBeNull();

    expect($transactionSpanB)
        ->start->toBe(1546346097000000000)
        ->end->toBe(1546346098000000000)
        ->parentSpanId->toBe($transactionSpanA->spanId);
});
