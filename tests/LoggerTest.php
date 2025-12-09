<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Time\TimeHelper;

it('can create a log with all parameters specified', function () {
    $flare = setupFlare();

    $flare->tracer->startTrace();

    $flare->logger->log(
        timestampUnixNano: $timestamp = new DateTime(),
        body: 'Test log message',
        observedTimestampUnixNano: $observedTimeStamp = new DateTime('-1 minute'),
        severityText: 'ERROR',
        severityNumber: 17,
        attributes: $attributes = ['key' => 'value', 'foo' => 'bar'],
        traceId: 'custom-trace-id',
        spanId: 'custom-span-id',
        flags: '01',
    );

    expect($flare->logger->logs())->toHaveCount(1);

    $flare->logger->flush();

    FakeApi::assertSent(logs: 1);

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectBody('Test log message')
        ->expectTime(TimeHelper::dateTimeToNano($timestamp))
        ->expectObservedTime(TimeHelper::dateTimeToNano($observedTimeStamp))
        ->expectSeverityText('ERROR')
        ->expectSeverityNumber(17)
        ->expectTraceId('custom-trace-id')
        ->expectSpanId('custom-span-id')
        ->expectFlags('01')
        ->expectAttributes($attributes);
});

it('uses default parameters when not specified', function () {
    FakeTime::setup('2019-01-01 12:34:56');

    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $traceId = $flare->tracer->currentTraceId();
    $spanId = $flare->tracer->currentSpanId();

    $flare->logger->log(body: 'Simple log message');

    expect($flare->logger->logs())->toHaveCount(1);

    $flare->logger->flush();

    FakeApi::assertSent(logs: 1);

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectBody('Simple log message')
        ->expectTime(TimeHelper::dateTimeToNano(new DateTime('2019-01-01 12:34:56')))
        ->expectObservedTime(TimeHelper::dateTimeToNano(new DateTime('2019-01-01 12:34:56')))
        ->expectTraceId($traceId)
        ->expectSpanId($spanId)
        ->expectFlags('01');
});

it('sends logs to the API when flushed', function () {
    $flare = setupFlare();

    $flare->logger->log(body: 'First log');
    $flare->logger->log(body: 'Second log');

    expect($flare->logger->logs())->toHaveCount(2);

    $flare->logger->flush();

    FakeApi::assertSent(logs: 1);

    FakeApi::lastLog()->expectLogCount(2);
});

it('will not send logs when disabled', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config->log(false));

    $flare->logger->log(body: 'This log will not be sent');

    $flare->logger->flush();

    FakeApi::assertSent(logs: 0);
});

it('will not send a request when flushing an empty log', function () {
    $flare = setupFlare();

    $flare->logger->flush();

    FakeApi::assertSent(logs: 0);
});

it('will add context from the context recorder', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config->collectContext());

    $flare->context('user_id', 123);
    $flare->context('session_id', 'abc');

    $flare->logger->log(
        body: 'Log with context',
        attributes: ['custom_key' => 'custom_value'],
    );

    $flare->logger->flush();

    FakeApi::assertSent(logs: 1);

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectAttribute('custom_key', 'custom_value')
        ->expectAttribute('context.custom', [
            'user_id' => 123,
            'session_id' => 'abc',
        ]);
});
