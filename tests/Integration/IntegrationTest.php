<?php

use Monolog\Level;
use Spatie\FlareClient\Enums\LifecycleStage;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Tests\Shared\FakeApi;

beforeEach(function () {
    $this->originalEnv = $_ENV;
    $this->originalServer = $_SERVER;
});

afterEach(function () {
    $_ENV = $this->originalEnv;
    $_SERVER = $this->originalServer;
});

it('drives a full web request lifecycle including sampling, recording, logging, and reporting', function () {
    $_ENV['FLARE_FAKE_WEB_REQUEST'] = 'true';
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/admin/users';
    $_SERVER['REQUEST_METHOD'] = 'GET';

    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->collectRequests()
            ->collectQueries(includeBindings: true)
            ->collectViews()
            ->collectLogsWithErrors()
            ->sampleTracesDynamic(
                baseRate: 0,
                rules: [SamplingRule::forRoute('/admin/*', 1.0)],
            ),
    );

    $flare->lifecycle->start(timeUnixNano: 0);

    $flare->request()->recordStartFromGlobals();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->sampler->isPending())->toBeTrue();

    $flare->routing()->recordRoutingStart();
    $flare->routing()->recordRoutingEndFromDefined(
        route: '/admin/users',
        method: 'GET',
        handlerName: 'AdminController@index',
    );

    expect($flare->tracer->sampler->isPending())->toBeFalse();


    $flare->query()->record(
        sql: 'select * from users',
        duration: 250_000,
        bindings: ['id' => 1],
        databaseName: 'app',
        driverName: 'mysql',
    );

    $flare->view()->recordRendering(viewName: 'admin.users');
    $flare->view()->recordRendered();

    $flare->log()->record('about to crash', Level::Warning);

    $flare->request()->recordEndFromDefined(statusCode: 500);

    $flare->report(new Exception('Boom'));

    $flare->lifecycle->terminated(timeUnixNano: 100);

    FakeApi::assertSent(reports: 1, traces: 1, logs: 1);

    $expectedEntryPointAttributes = [
        'flare.entry_point.type' => 'web',
        'flare.entry_point.value' => 'http://example.com/admin/users',
        'flare.entry_point.handler.identifier' => 'GET /admin/users',
        'flare.entry_point.handler.name' => 'AdminController@index',
        'flare.entry_point.handler.type' => 'php_request',
    ];

    FakeApi::lastReport()
        ->expectAttributes($expectedEntryPointAttributes);

    $trace = FakeApi::lastTrace()
        ->expectSpanCount(5)
        ->expectAllSpansClosed();

    $applicationSpan = $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectMissingParentId();

    $requestSpan = $trace->expectSpan(1)
        ->expectType(SpanType::Request)
        ->expectParentId($applicationSpan)
        ->expectAttributes($expectedEntryPointAttributes);

    $trace->expectSpan(2)
        ->expectType(SpanType::Routing)
        ->expectName('Routing')
        ->expectParentId($requestSpan)
        ->expectAttribute('http.route', '/admin/users');

    $trace->expectSpan(3)
        ->expectType(SpanType::Query)
        ->expectName('Query - select * from users')
        ->expectParentId($requestSpan);

    $trace->expectSpan(4)
        ->expectType(SpanType::View)
        ->expectName('View - admin.users')
        ->expectParentId($requestSpan);

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectBody('about to crash')
        ->expectSeverityText('warning')
        ->expectSeverityNumber(13)
        ->expectTraceId($applicationSpan->span['traceId'])
        ->expectAttributes($expectedEntryPointAttributes);
});

it('drives a console command lifecycle and propagates the cli entry point', function () {
    $_SERVER['argv'] = ['artisan', 'app:sync', '--force'];

    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->collectCommands()
            ->collectLogsWithErrors(),
        alwaysSampleTraces: true,
    );

    $flare->lifecycle->start(timeUnixNano: 0);

    $flare->command()->recordStartFromCli('app:sync', 'App\\Console\\SyncCommand');

    $flare->log()->record('starting sync', Level::Info);

    $flare->command()->recordEnd();

    $flare->report(new Exception('boom from cli'));

    $flare->lifecycle->terminated(timeUnixNano: 50);

    FakeApi::assertSent(reports: 1, traces: 1, logs: 1);

    $expectedEntryPointAttributes = [
        'flare.entry_point.type' => 'cli',
        'flare.entry_point.value' => 'artisan app:sync --force',
        'flare.entry_point.handler.identifier' => 'app:sync',
        'flare.entry_point.handler.name' => 'App\\Console\\SyncCommand',
        'flare.entry_point.handler.type' => 'php_command',
    ];

    FakeApi::lastReport()
        ->expectAttributes($expectedEntryPointAttributes);

    $trace = FakeApi::lastTrace()
        ->expectSpanCount(2)
        ->expectAllSpansClosed();

    $applicationSpan = $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectMissingParentId();

    $trace->expectSpan(1)
        ->expectType(SpanType::Command)
        ->expectName('Command - app:sync')
        ->expectParentId($applicationSpan)
        ->expectAttributes($expectedEntryPointAttributes);

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectBody('starting sync')
        ->expectSeverityText('info')
        ->expectTraceId($applicationSpan->span['traceId'])
        ->expectAttributes($expectedEntryPointAttributes);
});

it('drives a queued job in subtask mode without polluting the parent trace', function () {
     $traceparent = '00-1234567890abcdef1234567890abcdef-fedcba9876543210-01';

    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->collectJobs()
            ->collectLogsWithErrors(),
        alwaysSampleTraces: true,
        isUsingSubtasks: true,
    );

    expect($flare->lifecycle->usesSubtasks)->toBeTrue();
    expect($flare->lifecycle->getStage())->toBe(LifecycleStage::Idle);

    $flare->job()->recordStartFromJob('App\\Jobs\\Send', 'App\\Jobs\\Send', traceparent: $traceparent);

    expect($flare->lifecycle->getStage())->toBe(LifecycleStage::Subtask);

    $flare->log()->record('processing job', Level::Info);

    $flare->job()->recordEnd();

    expect($flare->lifecycle->getStage())->toBe(LifecycleStage::Idle);

    FakeApi::assertSent(traces: 1, logs: 1);

    $expectedEntryPointAttributes = [
        'flare.entry_point.type' => 'queue',
        'flare.entry_point.value' => 'App\\Jobs\\Send',
        'flare.entry_point.handler.identifier' => 'App\\Jobs\\Send',
        'flare.entry_point.handler.name' => 'App\\Jobs\\Send',
        'flare.entry_point.handler.type' => 'php_job',
    ];

    $trace = FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed();

    $trace->expectSpan(0)
        ->expectType(SpanType::Job)
        ->expectName('Job - App\\Jobs\\Send')
        ->expectTraceId('1234567890abcdef1234567890abcdef')
        ->expectAttributes($expectedEntryPointAttributes);

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectBody('processing job')
        ->expectSeverityText('info')
        ->expectTraceId('1234567890abcdef1234567890abcdef');
});
