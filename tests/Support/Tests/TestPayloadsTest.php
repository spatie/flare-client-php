<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Tests\TestPayloads;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('can report an exception', function () {
    setupFlare();

    $testPayloads = Container::instance()->get(TestPayloads::class);

    $testPayloads->report();

    FakeApi::assertSent(reports: 1);

    FakeApi::lastReport()->expectMessage('This is an exception to test if the integration with Flare works.');
});

it('can send a trace', function () {
    setupFlare(alwaysSampleTraces: true);

    $testPayloads = Container::instance()->get(TestPayloads::class);

    $testPayloads->trace();

    FakeApi::assertSent(traces: 1);

    $trace = FakeApi::lastTrace();

    $trace->expectSpanCount(5);

    $applicationSpan = $trace->expectSpan(0)->expectType(SpanType::Application);

    $trace->expectSpan(1)
        ->expectParentId($applicationSpan)
        ->expectType(SpanType::ApplicationRegistration);

    $trace->expectSpan(2)
        ->expectParentId($applicationSpan)
        ->expectType(SpanType::ApplicationBoot);

    $requestSpan = $trace->expectSpan(3)
        ->expectName('Request -  /test-flare-integration')
        ->expectParentId($applicationSpan)
        ->expectType(SpanType::Request)
        ->expectHasAttribute('flare.peak_memory_usage');

    $requestSpan
        ->expectSpanEventCount(1)
        ->expectSpanEvent(0)
        ->expectType(SpanEventType::Glow)
        ->expectAttribute('glow.name', 'Hi there!');

    $trace->expectSpan(4)
        ->expectType(SpanType::Query)
        ->expectParentId($requestSpan)
        ->expectName('Query - select * from users where id = ?')
        ->expectAttribute('db.system', 'mysql')
        ->expectAttribute('db.name', 'default')
        ->expectAttribute('db.statement', 'select * from users where id = ?');
});

it('can send logs', function () {
    setupFlare();

    $testPayloads = Container::instance()->get(TestPayloads::class);

    $testPayloads->log();

    FakeApi::assertSent(logs: 1);

    $logs = FakeApi::lastLog();

    $logs->expectLogCount(8);

    $logs->expectLog(0)
        ->expectBody('This is a DEBUG log message to test Flare integration.')
        ->expectSeverityText('debug');

    $logs->expectLog(1)
        ->expectBody('This is a INFO log message to test Flare integration.')
        ->expectSeverityText('info');

    $logs->expectLog(2)
        ->expectBody('This is a NOTICE log message to test Flare integration.')
        ->expectSeverityText('notice');

    $logs->expectLog(3)
        ->expectBody('This is a WARNING log message to test Flare integration.')
        ->expectSeverityText('warning');

    $logs->expectLog(4)
        ->expectBody('This is a ERROR log message to test Flare integration.')
        ->expectSeverityText('error');

    $logs->expectLog(5)
        ->expectBody('This is a CRITICAL log message to test Flare integration.')
        ->expectSeverityText('critical');

    $logs->expectLog(6)
        ->expectBody('This is a ALERT log message to test Flare integration.')
        ->expectSeverityText('alert');

    $logs->expectLog(7)
        ->expectBody('This is a EMERGENCY log message to test Flare integration.')
        ->expectSeverityText('emergency');
});
