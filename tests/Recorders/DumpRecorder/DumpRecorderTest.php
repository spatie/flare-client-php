<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpRecorder;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpSpanEvent;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('can symphony record dumps', function () {

    $dumpRecorder = new DumpRecorder(
        tracer: setupFlare()->tracer,
        traceDumps: true,
        reportDumps: true,
        maxReportedDumps: 10,
        findDumpOrigin: false,
    );

    $dumpRecorder->start();

    dump('This is a test for the DumpRecorder');

    $dumps = $dumpRecorder->getSpanEvents();
    $this->assertCount(1, $dumps);

    expect($dumps[0])
        ->toBeInstanceOf(DumpSpanEvent::class)
        ->name->toBe('Dump entry')
        ->timeUs->toBe(1546346096000);

    expect($dumps[0]->attributes)
        ->toHaveCount(2)
        ->toHaveKey('flare.span_event_type', SpanEventType::Dump)
        ->toHaveKey('dump.html');
});

it('can record dump origins', function () {

    $dumpRecorder = new DumpRecorder(
        tracer: setupFlare()->tracer,
        traceDumps: true,
        reportDumps: true,
        maxReportedDumps: 10,
        findDumpOrigin: true,
    );

    $dumpRecorder->start();

    dump('This is a test for the DumpRecorder');

    expect($dumpRecorder->getSpanEvents()[0]->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('can disable recording dump origins', function () {

    $dumpRecorder = new DumpRecorder(
        tracer: setupFlare()->tracer,
        traceDumps: true,
        reportDumps: true,
        maxReportedDumps: 10,
        findDumpOrigin: false,
    );

    $dumpRecorder->start();

    dump('This is a test for the DumpRecorder');

    expect($dumpRecorder->getSpanEvents()[0]->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});
