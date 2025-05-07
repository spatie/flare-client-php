<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpSpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('can symphony record dumps', function () {

    $flare = setupFlare();
    $dumpRecorder = new DumpRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
        ],
    );

    $dumpRecorder->boot();

    dump('This is a test for the DumpRecorder');

    $dumps = $dumpRecorder->getSpanEvents();
    $this->assertCount(1, $dumps);

    expect($dumps[0])
        ->toBeInstanceOf(DumpSpanEvent::class)
        ->name->toBe('Dump entry')
        ->timestamp->toBe(1546346096000000000);

    expect($dumps[0]->attributes)
        ->toHaveCount(2)
        ->toHaveKey('flare.span_event_type', SpanEventType::Dump)
        ->toHaveKey('dump.html');
});

it('can record dump origins', function () {
    $flare = setupFlare();

    $dumpRecorder = new DumpRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'find_origin' => true,
        ],
    );

    $dumpRecorder->boot();

    dump('This is a test for the DumpRecorder');

    expect($dumpRecorder->getSpanEvents()[0]->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('can disable recording dump origins', function () {
    $flare = setupFlare();

    $dumpRecorder = new DumpRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'find_origin' => false,
        ],
    );

    $dumpRecorder->boot();

    dump('This is a test for the DumpRecorder');

    expect($dumpRecorder->getSpanEvents()[0]->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});
