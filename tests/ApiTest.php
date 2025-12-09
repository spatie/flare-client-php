<?php

use Spatie\FlareClient\Api;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Tests\Shared\FakeSender;

it('queues a report when immediately is false', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);


    $api = Container::instance()->get(Api::class);

    $api->report(
        $flare->createReport(new Exception('Test exception')),
    );

    FakeSender::assertNothingSent();

    $api->sendQueue();

    FakeSender::assertSent(reports: 1);
});

it('sends a report immediately when immediately is true', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    $api->report(
        $flare->createReport(new Exception('Test exception')),
        immediately: true
    );

    FakeSender::assertSent(reports: 1);
});

it('will never throw an error when sending reports', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    FakeSender::$responseCode = 500;

    $api->report(
        $flare->createReport(new Exception('Test exception')),
        immediately: true
    );

    FakeSender::assertSent(reports: 1);

    $api->report(
        $flare->createReport(new Exception('Test exception')),
    );
    $api->sendQueue();

    FakeSender::assertSent(reports: 2);
});

it('will send test reports immediately', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    $api->report(
        $flare->createReport(new Exception('Test exception')),
        test: true,
    );

    FakeSender::assertSent(reports: 1);
});

it('will throw errors when sending test reports', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    FakeSender::$responseCode = 500;

    expect(fn () => $api->report(
        $flare->createReport(new Exception('Test exception')),
        test: true
    ))->toThrow(BadResponseCode::class);
});

it('queues a trace when immediately is false', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false, alwaysSampleTraces: true);

    $api = Container::instance()->get(Api::class);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('test span');
    $flare->tracer->endSpan($span);

    $api->trace($flare->tracer->currentTrace());

    FakeSender::assertNothingSent();

    $api->sendQueue();

    FakeSender::assertSent(traces: 1);
});

it('sends a trace immediately when immediately is true', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false, alwaysSampleTraces: true);

    $api = Container::instance()->get(Api::class);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('test span');
    $flare->tracer->endSpan($span);

    $api->trace($flare->tracer->currentTrace(), immediately: true);

    FakeSender::assertSent(traces: 1);
});

it('will never throw an error when sending traces', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false, alwaysSampleTraces: true);

    $api = Container::instance()->get(Api::class);

    FakeSender::$responseCode = 500;

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('test span');
    $flare->tracer->endSpan($span);

    $api->trace($flare->tracer->currentTrace(), immediately: true);

    FakeSender::assertSent(traces: 1);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('test span');
    $flare->tracer->endSpan($span);

    $api->trace($flare->tracer->currentTrace());
    $api->sendQueue();

    FakeSender::assertSent(traces: 2);
});

it('will send test traces immediately', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false, alwaysSampleTraces: true);

    $api = Container::instance()->get(Api::class);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('test span');
    $flare->tracer->endSpan($span);

    $api->trace($flare->tracer->currentTrace(), test: true);

    FakeSender::assertSent(traces: 1);
});

it('will throw errors when sending test traces', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false, alwaysSampleTraces: true);

    $api = Container::instance()->get(Api::class);

    FakeSender::$responseCode = 500;

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('test span');
    $flare->tracer->endSpan($span);

    expect(fn () => $api->trace($flare->tracer->currentTrace(), test: true))->toThrow(BadResponseCode::class);
});

it('queues a log when immediately is false', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    $flare->logger->log(body: 'test log message');

    $api->log($flare->logger->logs());

    FakeSender::assertNothingSent();

    $api->sendQueue();

    FakeSender::assertSent(logs: 1);
});

it('sends a log immediately when immediately is true', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    $flare->logger->log(body: 'test log message');

    $api->log($flare->logger->logs(), immediately: true);

    FakeSender::assertSent(logs: 1);
});

it('will never throw an error when sending logs', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    FakeSender::$responseCode = 500;

    $flare->logger->log(body: 'test log message');

    $api->log($flare->logger->logs(), immediately: true);

    FakeSender::assertSent(logs: 1);

    $flare->logger->log(body: 'test log message');

    $api->log($flare->logger->logs());
    $api->sendQueue();

    FakeSender::assertSent(logs: 2);
});

it('will send test logs immediately', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    $flare->logger->log(body: 'test log message');

    $api->log($flare->logger->logs(), test: true);

    FakeSender::assertSent(logs: 1);
});

it('will throw errors when sending test logs', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->sender = FakeSender::class, useFakeApi:  false);

    $api = Container::instance()->get(Api::class);

    FakeSender::$responseCode = 500;

    $flare->logger->log(body: 'test log message');

    expect(fn () => $api->log($flare->logger->logs(), test: true))->toThrow(BadResponseCode::class);
});
