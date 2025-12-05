<?php

use PHPUnit\Framework\Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Enums\TransactionStatus;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\ConcreteSpansRecorder as FakeSpansRecorder;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;
use Spatie\FlareClient\Tests\TestClasses\FakeFlareMiddleware;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;
use Spatie\FlareClient\Time\TimeHelper;

uses(MatchesReportSnapshots::class);

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('can report an exception', function () {
    setupFlare();

    reportException();

    FakeApi::assertSent(reports: 1);

    $this->assertMatchesReportSnapshot(
        FakeApi::lastReport()->toArray()
    );
});


it('can reset queued exceptions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext());

    $flare->context('test_key', 'test_value');

    expect($flare->recorder(RecorderType::Context)->toArray())->toBe(['context.custom' => ['test_key' => 'test_value']]);

    reportException();

    $flare->lifecycle->flush();

    FakeApi::assertSent(reports: 1);

    expect($flare->recorder(RecorderType::Context)->toArray())->toBe([]);

    $flare->lifecycle->flush();

    FakeApi::assertSent(reports: 1);

    expect($flare->recorder(RecorderType::Context)->toArray())->toBe([]);
});

it('can reset queued traces', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext(), alwaysSampleTraces: true);

    $flare->context('test_key', 'test_value');

    expect($flare->recorder(RecorderType::Context)->toArray())->toBe(['context.custom' => ['test_key' => 'test_value']]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('Test Span');
    $flare->tracer->endSpan($span);
    $flare->tracer->endTrace();

    FakeApi::assertSent(traces: 1);

    $flare->lifecycle->flush();

    FakeApi::assertSent(traces: 1);

    expect($flare->recorder(RecorderType::Context)->toArray())->toBe([]);
});

it('can add user provided context', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext());

    $flare->context('my key', 'my value');

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'context.custom',
        ['my key' => 'my value']
    );
});

it('can add user provided context easily as an array', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext());

    $flare->context(
        ['my key' => 'my value'],
    );

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'context.custom',
        ['my key' => 'my value']
    );
});

test('callbacks can modify the report', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->applicationStage('production')->collectContext()
    );

    $flare->context('my key', 'my value');

    $throwable = new Exception('This is a test');

    $flare->report($throwable, function (ReportFactory $report) {
        $report->context('my key', 'new value');
        $report->handled = true;
    });

    FakeApi::lastReport()
        ->expectAttribute('context.custom', ['my key' => 'new value'])
        ->expectHandled(true);
});

it('can censor request data', function () {
    setupFlare(
        fn (FlareConfig $config) => $config->censorBodyFields('user', 'password')->collectRequests()
    );

    $_ENV['FLARE_FAKE_WEB_REQUEST'] = true;
    $_POST['user'] = 'john@example.com';
    $_POST['password'] = 'secret';

    $_SERVER['REQUEST_METHOD'] = 'POST';

    reportException();

    FakeApi::lastReport()
        ->expectAttribute('http.request.body.contents', [
            'user' => '<CENSORED:string>',
            'password' => '<CENSORED:string>',
        ]);
});

it('can merge user provided context', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext());

    $flare->context('my key', 'my value');

    $flare->context('another key', 'another value');

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'context.custom',
        [
            'my key' => 'my value',
            'another key' => 'another value',
        ]
    );
});

it('can add custom exception context', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext());

    $flare->context('my key', 'my value');

    $throwable = new ExceptionWithContext('This is a test');

    $flare->report($throwable);

    FakeApi::lastReport()
        ->expectAttribute('context.custom', [
            'my key' => 'my value',
        ])
        ->expectAttribute('context.exception', [
            'another key' => 'another value',
        ]);
});


it('can merge groups', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectContext());

    $flare->context(['my key' => 'my value']);

    $flare->context(['another key' => 'another value']);

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'context.custom',
        [
            'my key' => 'my value',
            'another key' => 'another value',
        ]
    );
});

it('can set stages', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationStage('production'));

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'service.stage',
        'production'
    );
});

it('can add cache events', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectCacheEvents());

    $flare->cache()->recordHit('key', 'store');

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->cache()->recordMiss('key', 'store');

    FakeTime::setup('2019-01-01 12:34:58'); // One second later 1546346098000000

    $flare->cache()->recordKeyWritten('key', 'store');

    FakeTime::setup('2019-01-01 12:34:59'); // One second later 1546346099000000

    $flare->cache()->recordKeyForgotten('key', 'store');

    reportException();

    $payload = FakeApi::lastReport();

    $payload->expectEventCount(4);

    $payload->expectEvent(0)
        ->expectStart(1546346096000000000)
        ->expectEnd(null)
        ->expectType(SpanEventType::Cache)
        ->expectAttributesCount(4)
        ->expectAttribute('cache.key', 'key')
        ->expectAttribute('cache.store', 'store')
        ->expectAttribute('cache.operation', CacheOperation::Get)
        ->expectAttribute('cache.result', CacheResult::Hit);

    $payload->expectEvent(1)
        ->expectStart(1546346097000000000)
        ->expectEnd(null)
        ->expectType(SpanEventType::Cache)
        ->expectAttributesCount(4)
        ->expectAttribute('cache.key', 'key')
        ->expectAttribute('cache.store', 'store')
        ->expectAttribute('cache.operation', CacheOperation::Get)
        ->expectAttribute('cache.result', CacheResult::Miss);

    $payload->expectEvent(2)
        ->expectStart(1546346098000000000)
        ->expectEnd(null)
        ->expectType(SpanEventType::Cache)
        ->expectAttributesCount(4)
        ->expectAttribute('cache.key', 'key')
        ->expectAttribute('cache.store', 'store')
        ->expectAttribute('cache.operation', CacheOperation::Set)
        ->expectAttribute('cache.result', CacheResult::Success);

    $payload->expectEvent(3)
        ->expectStart(1546346099000000000)
        ->expectEnd(null)
        ->expectType(SpanEventType::Cache)
        ->expectAttributesCount(4)
        ->expectAttribute('cache.key', 'key')
        ->expectAttribute('cache.store', 'store')
        ->expectAttribute('cache.operation', CacheOperation::Forget)
        ->expectAttribute('cache.result', CacheResult::Success);
});

it('can add glows', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectGlows());

    $flare->glow()->record(
        'my glow',
        MessageLevels::Info,
        ['my key' => 'my value']
    );

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->glow()->record(
        'another glow',
        MessageLevels::Error,
        ['another key' => 'another value']
    );

    reportException();

    $report = FakeApi::lastReport();

    $report->expectEventCount(2);

    $report->expectEvent(0)
        ->expectType(SpanEventType::Glow)
        ->expectStart(1546346096000000000)
        ->expectEnd(null)
        ->expectAttributesCount(3)
        ->expectAttribute('glow.name', 'my glow')
        ->expectAttribute('glow.level', 'info')
        ->expectAttribute('glow.context', ['my key' => 'my value']);

    $report->expectEvent(1)
        ->expectType(SpanEventType::Glow)
        ->expectStart(1546346097000000000)
        ->expectEnd(null)
        ->expectAttributesCount(3)
        ->expectAttribute('glow.name', 'another glow')
        ->expectAttribute('glow.level', 'error')
        ->expectAttribute('glow.context', ['another key' => 'another value']);
});

it('can add logs', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectLogs());

    $flare->log()->record(
        'my log',
        MessageLevels::Info,
        ['my key' => 'my value']
    );

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->log()->record(
        'another log',
        MessageLevels::Error,
        ['another key' => 'another value']
    );

    reportException();

    $report = FakeApi::lastReport();

    $report->expectEventCount(2);

    $report->expectEvent(0)
        ->expectType(SpanEventType::Log)
        ->expectStart(1546346096000000000)
        ->expectEnd(null)
        ->expectAttributesCount(3)
        ->expectAttribute('log.message', 'my log')
        ->expectAttribute('log.level', 'info')
        ->expectAttribute('log.context', ['my key' => 'my value']);

    $report->expectEvent(1)
        ->expectType(SpanEventType::Log)
        ->expectStart(1546346097000000000)
        ->expectEnd(null)
        ->expectAttributesCount(3)
        ->expectAttribute('log.message', 'another log')
        ->expectAttribute('log.level', 'error')
        ->expectAttribute('log.context', ['another key' => 'another value']);
});

it('can add queries', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectQueries());

    $flare->query()->record(
        'select * from users where id = ?',
        TimeHelper::milliseconds(250),
        ['id' => 1],
        'users',
        'mysql',
    );

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->query()->record(
        'select * from users where id = ?',
        TimeHelper::milliseconds(125),
        ['id' => 2],
        'users',
        'mysql',
    );

    reportException();

    $report = FakeApi::lastReport();

    $report->expectEventCount(2);

    $report->expectEvent(0)
        ->expectType(SpanType::Query)
        ->expectStart(1546346096000000000 - TimeHelper::milliseconds(250))
        ->expectEnd(1546346096000000000)
        ->expectAttributesCount(4)
        ->expectAttribute('db.system', 'mysql')
        ->expectAttribute('db.name', 'users')
        ->expectAttribute('db.statement', 'select * from users where id = ?')
        ->expectAttribute('db.sql.bindings', ['id' => 1]);

    $report->expectEvent(1)
        ->expectType(SpanType::Query)
        ->expectStart(1546346097000000000 - TimeHelper::milliseconds(125))
        ->expectEnd(1546346097000000000)
        ->expectAttributesCount(4)
        ->expectAttribute('db.system', 'mysql')
        ->expectAttribute('db.name', 'users')
        ->expectAttribute('db.statement', 'select * from users where id = ?')
        ->expectAttribute('db.sql.bindings', ['id' => 2]);
});

it('can begin and commit transactions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectTransactions());

    $flare->transaction()->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->transaction()->recordCommit();

    reportException();

    $report = FakeApi::lastReport();

    $report->expectEventCount(1);

    $report->expectEvent(0)
        ->expectStart(1546346096000000000)
        ->expectEnd(1546346097000000000)
        ->expectType(SpanType::Transaction)
        ->expectAttributesCount(1)
        ->expectAttribute('db.transaction.status', TransactionStatus::Committed);
});

it('can begin and rollback transactions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectTransactions());

    $flare->transaction()->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->transaction()->recordRollback();

    reportException();

    $report = FakeApi::lastReport();

    $report->expectEventCount(1);

    $report->expectEvent(0)
        ->expectStart(1546346096000000000)
        ->expectEnd(1546346097000000000)
        ->expectType(SpanType::Transaction)
        ->expectAttributesCount(1)
        ->expectAttribute('db.transaction.status', TransactionStatus::RolledBack);

});

test('a version is by default null', function () {
    setupFlare();

    reportException();

    $report = FakeApi::lastReport()->expectAttribute(
        'service.version',
        null
    );
});

it('will add the version to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationVersion(function () {
        return '123';
    }));

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'service.version',
        '123'
    );
});

it('will add the application name to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationName(function () {
        return 'Flare';
    }));

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'service.name',
        'Flare'
    );
});

it('is possible to configure the version on the flare instance', function () {
    $flare = setupFlare();

    $flare->withApplicationVersion(function () {
        return '123';
    });

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'service.version',
        '123'
    );
});

it('is possible to configure the application name on the flare instance', function () {
    $flare = setupFlare();

    $flare->withApplicationName(function () {
        return 'Flare';
    });

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'service.name',
        'Flare'
    );
});

it('is possible to configure the application stage on the flare instance', function () {
    $flare = setupFlare();

    $flare->withApplicationStage(function () {
        return 'Development';
    });

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'service.stage',
        'Development'
    );
});

it('will add the php version to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->collectServerInfo());

    reportException();

    FakeApi::lastReport()->expectAttribute(
        'process.runtime.version',
        phpversion()
    );
});

it('can filter exceptions being reported', function () {
    setupFlare(fn (FlareConfig $config) => $config->filterExceptionsUsing(fn (Throwable $exception) => false));

    reportException();

    FakeApi::assertSent(reports: 0);
});

it('can filter exceptions being reported and allow them', function () {
    setupFlare(fn (FlareConfig $config) => $config->filterExceptionsUsing(fn (Throwable $exception) => true));

    reportException();

    FakeApi::assertSent(reports: 1);
});

it('can filter exceptions being reported by setting it on the flare instance', function () {
    $flare = setupFlare();

    $flare->filterExceptionsUsing(fn (Throwable $exception) => false);

    reportException();

    FakeApi::assertSent(reports: 0);
});

it('can filter reports', function () {
    setupFlare(fn (FlareConfig $config) => $config->filterReportsUsing(fn (array $report) => false));

    reportException();

    FakeApi::assertSent(reports: 0);
});

it('can filter reports by setting it on the flare instance', function () {
    $flare = setupFlare();

    $flare->filterReportsUsing(fn (array $report) => false);

    reportException();

    FakeApi::assertSent(reports: 0);
});

it('can filter errors based on their level', function () {
    setupFlare(fn (FlareConfig $config) => $config->reportErrorLevels(E_ALL & ~E_NOTICE));

    reportError(E_NOTICE);
    reportError(E_WARNING);

    FakeApi::assertSent(reports: 1);
});

it('can filter error exceptions based on their severity', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->reportErrorLevels(E_ALL & ~E_NOTICE));

    $flare->report(new ErrorException('test', 0, E_NOTICE));
    $flare->report(new ErrorException('test', 0, E_WARNING));

    FakeApi::assertSent(reports: 1);
});

it('will add arguments to a stack trace by default', function () {
    // Todo: add some default argument reducers in the config
    $flare = setupFlare(fn (
        FlareConfig $config
    ) => $config->collectStackFrameArguments(argumentReducers: ArgumentReducers::default()));

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $flare->report($exception);

    $frame = FakeApi::lastReport()->expectStacktraceFrame(1);

    $frame->expectArgumentCount(2);

    $frame->expectArgument(0)
        ->expectName('string')
        ->expectValue('a message')
        ->expectPassedByReference(false)
        ->expectIsVariadic(false)
        ->expectTruncated(false)
        ->expectOriginalType('string');

    $frame->expectArgument(1)
        ->expectName('dateTime')
        ->expectValue('16 May 2020 14:00:00 Europe/Brussels')
        ->expectPassedByReference(false)
        ->expectIsVariadic(false)
        ->expectTruncated(false)
        ->expectOriginalType(DateTime::class);
});

it('is possible to disable stack frame arguments', function () {
    ini_set('zend.exception_ignore_args', 0); // Enabled on GH actions

    $flare = setupFlare(fn (FlareConfig $config) => $config->ignoreStackFrameArguments());

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $flare->report($exception);

    FakeApi::lastReport()->expectStacktraceFrame(1)->expectNoArguments();
});

it('is possible to disable stack frame arguments with zend.exception_ignore_args', function () {
    ini_set('zend.exception_ignore_args', 1);

    $flare = setupFlare(fn (FlareConfig $config) => $config->ignoreStackFrameArguments());

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $flare->report($exception);

    FakeApi::lastReport()->expectStacktraceFrame(1)->expectNoArguments();
});

it('can report a handled error', function () {
    $flare = setupFlare();

    $throwable = new Exception('This is a test');

    $flare->reportHandled($throwable);

    FakeApi::lastReport()->expectHandled();
});

it('is possible to manually add spans and span events', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();
    $flare->tracer->span('Test Span', function () use ($flare) {
        $flare->tracer->span('Test Child Span', function () use ($flare) {
            $flare->tracer->spanEvent('Test Child Span Event');
        });

        $flare->tracer->spanEvent('Test Span Event');
    });
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace();

    $trace->expectSpanCount(2);

    $parentSpan = $trace->expectSpan(0)
        ->expectName('Test Span')
        ->expectMissingParent()
        ->expectSpanEventCount(1);

    $parentSpan->expectSpanEvent(0)->expectName('Test Span Event');

    $childSpan = $trace->expectSpan(1)
        ->expectName('Test Child Span')
        ->expectParent($parentSpan)
        ->expectSpanEventCount(1);

    $childSpan->expectSpanEvent(0)->expectName('Test Child Span Event');
});

it('is possible to configure a tracing resource', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->configureResource(fn (Resource $resource) => $resource->addAttribute('custom_attribute', 'test'))
            ->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();

    $flare->tracer->span('Test Span', fn () => null);

    $flare->tracer->endTrace();

    FakeApi::lastTrace()->expectResource()->expectAttribute(
        'custom_attribute',
        'test'
    );
});

it('it is possible to configure a tracing scope', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->configureScope(fn (Scope $scope) => $scope->addAttribute('custom_attribute', 'test'))
            ->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();

    $flare->tracer->span('Test Span', fn () => null);

    $flare->tracer->endTrace();

    FakeApi::lastTrace()->expectScope()->expectAttribute(
        'custom_attribute',
        'test'
    );
});

it('is possible to configure a span when ended', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->configureSpans(fn (Span $span) => $span->addAttribute('custom_attribute', 'test'))
            ->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();

    $flare->tracer->span('Test Span', fn () => null);

    $flare->tracer->endTrace();

    FakeApi::lastTrace()->expectSpan(0)->expectAttribute(
        'custom_attribute',
        'test'
    );
});

it('is possible to configure a span event when ended', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->configureSpanEvents(fn (SpanEvent $spanEvent) => $spanEvent->addAttribute('custom_attribute', 'test'))
            ->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();

    $flare->tracer->span('Test Span', fn () => $flare->tracer->spanEvent('Test Span Event'));

    $flare->tracer->endTrace();

    FakeApi::lastTrace()->expectSpan(0)->expectSpanEvent(0)->expectAttribute(
        'custom_attribute',
        'test'
    );
});

it('can override the grouping algorithm for specific classes', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->configureSpanEvents(fn (SpanEvent $spanEvent) => $spanEvent->addAttribute('custom_attribute', 'test'))
            ->overrideGrouping(
                RuntimeException::class,
                OverriddenGrouping::ExceptionMessageAndClass
            )
            ->alwaysSampleTraces()
    );

    $throwable = new RuntimeException('This is a test');

    $flare->reportHandled($throwable);

    FakeApi::lastReport()->expectOverriddenGrouping(OverriddenGrouping::ExceptionMessageAndClass);
});

it('can add an additional recorders', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRecorders([
            FakeSpansRecorder::class => [
                'with_errors' => true,
            ],
        ])
    );

    $flare->recorder('spans')->record('Hi', duration: TimeHelper::milliseconds(300));

    $throwable = new RuntimeException('This is a test');

    $flare->reportHandled($throwable);

    FakeApi::lastReport()->expectEventCount(1);
});

it('it can add additional middleware', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectFlareMiddleware([
            FakeFlareMiddleware::class => [
                'extra' => ['key' => 'value'],
            ],
        ])
    );

    $throwable = new RuntimeException('This is a test');

    $flare->reportHandled($throwable);

    FakeApi::lastReport()->expectAttribute(
        'context.custom',
        [
            'extra' => ['key' => 'value'],
        ]
    );
});

it('can setup a disabled flare', function () {
    $flare = setupFlare(
        closure: fn (FlareConfig $config) => $config->collectLogs()->collectGlows(),
        withoutApiKey: true,
        alwaysSampleTraces: true
    );

    $flare->report(new Exception('This is a test'));

    $flare->glow()?->record('Hello', MessageLevels::Info);
    $flare->context('test', 'value');
    $flare->log()?->record('Hello', MessageLevels::Info);

    $flare->lifecycle->start();
    $flare->lifecycle->booted();
    $flare->tracer->span('test', fn () => null);
    $flare->lifecycle->terminated();

    expect($flare->sentReports->all())->toBeEmpty();

    FakeApi::assertSent(reports: 0, traces: 0, logs: 0);
});

// Helpers
function reportException()
{
    $throwable = new Exception('This is a test');

    test()->flare->report($throwable);
}

function reportError(int $code)
{
    $throwable = new Error('This is a test', $code);

    test()->flare->report($throwable);
}
