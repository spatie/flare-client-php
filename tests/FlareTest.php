<?php

use PHPUnit\Framework\Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Disabled\DisabledFlare;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\OverriddenGrouping;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Enums\TransactionStatus;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Shared\ExpectResource;
use Spatie\FlareClient\Tests\Shared\ExpectScope;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\DeprecatedSpansRecorder as FakeSpansRecorder;
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

    FakeSender::instance()->assertRequestsSent(1);

    $report = FakeSender::instance()->getLastPayload();

    $this->assertMatchesReportSnapshot($report);
});


it('can reset queued exceptions', function () {
    $flare = setupFlare();

    $flare->context('test_key', 'test_value');
    expect($flare->customContext)->toBe(['test_key' => 'test_value']);

    reportException();

    $flare->reset();

    FakeSender::instance()->assertRequestsSent(1);
    expect($flare->customContext)->toBe([]);

    $flare->reset();

    FakeSender::instance()->assertRequestsSent(1);
    expect($flare->customContext)->toBe([]);
});

it('can reset queued traces', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->context('test_key', 'test_value');
    expect($flare->customContext)->toBe(['test_key' => 'test_value']);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('Test Span');
    $flare->tracer->endSpan($span);
    $flare->tracer->endTrace(); // This should trigger the trace to be exported and queued

    expect(count(FakeSender::$requests))->toBe(1);

    $flare->reset(traces: false);

    expect(count(FakeSender::$requests))->toBe(1);
    expect($flare->customContext)->toBe([]);
});

it('can reset queued exceptions but keep custom context', function () {
    $flare = setupFlare();

    $flare->context('test_key', 'test_value');
    expect($flare->customContext)->toBe(['test_key' => 'test_value']);

    reportException();

    $flare->reset(clearCustomContext: false);

    FakeSender::instance()->assertRequestsSent(1);
    expect($flare->customContext)->toBe(['test_key' => 'test_value']);

    $flare->reset(clearCustomContext: true);

    FakeSender::instance()->assertRequestsSent(1);
    expect($flare->customContext)->toBe([]);
});

it('can add user provided context', function () {
    $flare = setupFlare();

    $flare->context('my key', 'my value');

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.custom', ['my key' => 'my value']);
});

it('can add user provided context easily as an array', function () {
    $flare = setupFlare();

    $flare->context(
        ['my key' => 'my value'],
    );

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.custom', ['my key' => 'my value']);
});

test('callbacks can modify the report', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->applicationStage('production')
    );

    $flare->context('my key', 'my value');

    $throwable = new Exception('This is a test');

    $flare->report($throwable, function (ReportFactory $report) {
        $report->context('my key', 'new value');
        $report->handled = true;
    });

    FakeSender::instance()->assertLastRequestAttribute('context.custom', ['my key' => 'new value']);

    expect(FakeSender::instance()->getLastPayload()['handled'])->toBeTrue();
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

    FakeSender::instance()->assertLastRequestAttribute('http.request.body.contents', [
        'user' => '<CENSORED:string>',
        'password' => '<CENSORED:string>',
    ]);
});

it('can merge user provided context', function () {
    $flare = setupFlare();

    $flare->context('my key', 'my value');

    $flare->context('another key', 'another value');

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.custom', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can add custom exception context', function () {
    $flare = setupFlare();

    $flare->context('my key', 'my value');

    $throwable = new ExceptionWithContext('This is a test');

    $flare->report($throwable);

    FakeSender::instance()->assertLastRequestAttribute('context.custom', [
        'my key' => 'my value',
    ]);

    FakeSender::instance()->assertLastRequestAttribute('context.exception', [
        'another key' => 'another value',
    ]);
});


it('can merge groups', function () {
    $flare = setupFlare();

    $flare->context(['my key' => 'my value']);

    $flare->context(['another key' => 'another value']);

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.custom', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can set stages', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationStage('production'));

    reportException();

    expect(FakeSender::instance()->getLastPayload()['attributes']['service.stage'])->toBe('production');
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

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['events'])->toHaveCount(4);

    expect($payload['events'][0])
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', null)
        ->toHaveKey('type', SpanEventType::Cache)
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Get)
        ->toHaveKey('cache.result', CacheResult::Hit);

    expect($payload['events'][1])
        ->toHaveKey('startTimeUnixNano', 1546346097000000000)
        ->toHaveKey('endTimeUnixNano', null)
        ->toHaveKey('type', SpanEventType::Cache)
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Get)
        ->toHaveKey('cache.result', CacheResult::Miss);

    expect($payload['events'][2])
        ->toHaveKey('startTimeUnixNano', 1546346098000000000)
        ->toHaveKey('endTimeUnixNano', null)
        ->toHaveKey('type', SpanEventType::Cache)
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Set)
        ->toHaveKey('cache.result', CacheResult::Success);

    expect($payload['events'][3])
        ->toHaveKey('startTimeUnixNano', 1546346099000000000)
        ->toHaveKey('endTimeUnixNano', null)
        ->toHaveKey('type', SpanEventType::Cache)
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Forget)
        ->toHaveKey('cache.result', CacheResult::Success);
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

    $payload = FakeSender::instance()->getLastPayload();

    $this->assertEquals([
        [
            'attributes' => [
                'glow.name' => 'my glow',
                'glow.level' => 'info',
                'glow.context' => ['my key' => 'my value'],
            ],
            'startTimeUnixNano' => 1546346096000000000,
            'endTimeUnixNano' => null,
            'type' => SpanEventType::Glow,
        ],
        [
            'attributes' => [
                'glow.name' => 'another glow',
                'glow.level' => 'error',
                'glow.context' => ['another key' => 'another value'],
            ],
            'startTimeUnixNano' => 1546346097000000000,
            'endTimeUnixNano' => null,
            'type' => SpanEventType::Glow,
        ],
    ], $payload['events']);
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

    $payload = FakeSender::instance()->getLastPayload();

    $this->assertEquals([
        [
            'attributes' => [
                'log.message' => 'my log',
                'log.level' => 'info',
                'log.context' => ['my key' => 'my value'],
            ],
            'startTimeUnixNano' => 1546346096000000000,
            'endTimeUnixNano' => null,
            'type' => SpanEventType::Log,
        ],
        [
            'attributes' => [
                'log.message' => 'another log',
                'log.level' => 'error',
                'log.context' => ['another key' => 'another value'],
            ],
            'startTimeUnixNano' => 1546346097000000000,
            'endTimeUnixNano' => null,
            'type' => SpanEventType::Log,
        ],
    ], $payload['events']);
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

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['events'])->toHaveCount(2);

    expect($payload['events'][0])
        ->toHaveKey('startTimeUnixNano', 1546346096000000000 - TimeHelper::milliseconds(250))
        ->toHaveKey('endTimeUnixNano', 1546346096000000000)
        ->toHaveKey('type', SpanType::Query)
        ->attributes
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('db.statement', 'select * from users where id = ?')
        ->toHaveKey('db.sql.bindings', ['id' => 1]);

    expect($payload['events'][1])
        ->toHaveKey('startTimeUnixNano', 1546346097000000000 - TimeHelper::milliseconds(125))
        ->toHaveKey('endTimeUnixNano', 1546346097000000000)
        ->toHaveKey('type', SpanType::Query)
        ->attributes
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('db.statement', 'select * from users where id = ?')
        ->toHaveKey('db.sql.bindings', ['id' => 2]);
});

it('can begin and commit transactions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectTransactions());

    $flare->transaction()->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->transaction()->recordCommit();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['events'])->toHaveCount(1);

    expect($payload['events'][0])
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', 1546346097000000000)
        ->toHaveKey('type', SpanType::Transaction)
        ->attributes
        ->toHaveKey('db.transaction.status', TransactionStatus::Committed);
});

it('can begin and rollback transactions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectTransactions());

    $flare->transaction()->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->transaction()->recordRollback();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['events'])->toHaveCount(1);

    expect($payload['events'][0])
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', 1546346097000000000)
        ->toHaveKey('type', SpanType::Transaction)
        ->attributes
        ->toHaveKey('db.transaction.status', TransactionStatus::RolledBack);

});

test('a version is by default null', function () {
    setupFlare();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.version'])->toBeNull();
});

it('will add the version to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationVersion(function () {
        return '123';
    }));

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.version'])->toEqual('123');
});

it('will add the application name to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationName(function () {
        return 'Flare';
    }));

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.name'])->toEqual('Flare');
});

it('is possible to configure the version on the flare instance', function () {
    $flare = setupFlare();

    $flare->withApplicationVersion(function () {
        return '123';
    });

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.version'])->toEqual('123');
});

it('is possible to configure the application name on the flare instance', function () {
    $flare = setupFlare();

    $flare->withApplicationName(function () {
        return 'Flare';
    });

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.name'])->toEqual('Flare');
});

it('is possible to configure the application stage on the flare instance', function () {
    $flare = setupFlare();

    $flare->withApplicationStage(function () {
        return 'Development';
    });

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.stage'])->toEqual('Development');
});

it('will add the php version to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->collectServerInfo());

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['process.runtime.version'])->toEqual(phpversion());
});

it('can filter exceptions being reported', function () {
    setupFlare(fn (FlareConfig $config) => $config->filterExceptionsUsing(fn (Throwable $exception) => false));

    reportException();

    FakeSender::instance()->assertRequestsSent(0);
});

it('can filter exceptions being reported and allow them', function () {
    setupFlare(fn (FlareConfig $config) => $config->filterExceptionsUsing(fn (Throwable $exception) => true));

    reportException();

    FakeSender::instance()->assertRequestsSent(1);
});

it('can filter exceptions being reported by setting it on the flare instance', function () {
    $flare = setupFlare();

    $flare->filterExceptionsUsing(fn (Throwable $exception) => false);

    reportException();

    FakeSender::instance()->assertRequestsSent(0);
});

it('can filter reports', function () {
    setupFlare(fn (FlareConfig $config) => $config->filterReportsUsing(fn (Report $report) => false));

    reportException();

    FakeSender::instance()->assertRequestsSent(0);
});

it('can filter reports by setting it on the flare instance', function () {
    $flare = setupFlare();

    $flare->filterReportsUsing(fn (Report $report) => false);

    reportException();

    FakeSender::instance()->assertRequestsSent(0);
});

it('can filter errors based on their level', function () {
    setupFlare(fn (FlareConfig $config) => $config->reportErrorLevels(E_ALL & ~E_NOTICE));

    reportError(E_NOTICE);
    reportError(E_WARNING);

    FakeSender::instance()->assertRequestsSent(1);
});

it('can filter error exceptions based on their severity', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->reportErrorLevels(E_ALL & ~E_NOTICE));

    $flare->report(new ErrorException('test', 0, E_NOTICE));
    $flare->report(new ErrorException('test', 0, E_WARNING));

    FakeSender::instance()->assertRequestsSent(1);
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

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['stacktrace'][1]['arguments'])->toEqual([
        [
            "name" => "string",
            "value" => "a message",
            "passed_by_reference" => false,
            "is_variadic" => false,
            "truncated" => false,
            'original_type' => 'string',
        ],
        [
            "name" => "dateTime",
            "value" => '16 May 2020 14:00:00 Europe/Brussels',
            "passed_by_reference" => false,
            "is_variadic" => false,
            "truncated" => false,
            'original_type' => DateTime::class,
        ],
    ]);
});

it('is possible to disable stack frame arguments', function () {
    ini_set('zend.exception_ignore_args', 0); // Enabled on GH actions

    $flare = setupFlare(fn (FlareConfig $config) => $config->ignoreStackFrameArguments());

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $flare->report($exception);

    expect(FakeSender::instance()->getLastPayload()['stacktrace'][1]['arguments'])->toBeNull();
});

it('is possible to disable stack frame arguments with zend.exception_ignore_args', function () {
    ini_set('zend.exception_ignore_args', 1);

    $flare = setupFlare(fn (FlareConfig $config) => $config->ignoreStackFrameArguments());

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $flare->report($exception);

    expect(FakeSender::instance()->getLastPayload()['stacktrace'][1]['arguments'])->toBeNull();
});

it('can report a handled error', function () {
    $flare = setupFlare();

    $throwable = new Exception('This is a test');

    $flare->reportHandled($throwable);

    FakeSender::instance()->assertRequestsSent(1);

    $report = FakeSender::instance()->getLastPayload();

    expect($report['handled'])->toBeTrue();
});

it('is possible to manually add spans and span events', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->span('Test Span', function () use ($flare) {
        $flare->tracer->span('Test Child Span', function () use ($flare) {
            $flare->tracer->spanEvent('Test Child Span Event');
        });

        $flare->tracer->spanEvent('Test Span Event');
    });

    ExpectTracer::create($flare)
        ->trace(
            fn (ExpectTrace $trace) => $trace
                ->hasSpanCount(2)
                ->span(
                    fn (ExpectSpan $span) => $span
                        ->hasName('Test Span')
                        ->missingParent()
                        ->hasSpanEventCount(1)
                        ->spanEvent(fn (ExpectSpanEvent $spanEvent) => $spanEvent->hasName('Test Span Event')),
                    $parentSpan
                )
                ->span(
                    fn (ExpectSpan $span) => $span
                        ->hasName('Test Child Span')
                        ->hasParent($parentSpan)
                        ->hasSpanEventCount(1)
                        ->spanEvent(fn (ExpectSpanEvent $spanEvent) => $spanEvent->hasName('Test Child Span Event'))
                )
        );
});

it('is possible to configure a tracing resource', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
            ->configureResource(fn (Resource $resource) => $resource->addAttribute('custom_attribute', 'test'))
            ->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();

    $flare->tracer->span('Test Span', fn () => null);

    ExpectTracer::create($flare)->resource(
        fn (ExpectResource $resource) => $resource->hasAttribute('custom_attribute', 'test')
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

    ExpectTracer::create($flare)->scope(
        fn (ExpectScope $scope) => $scope->hasAttribute('custom_attribute', 'test')
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

    ExpectTracer::create($flare)->trace(
        fn (ExpectTrace $trace) => $trace->span(
            fn (ExpectSpan $span) => $span->hasAttribute('custom_attribute', 'test')
        )
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

    ExpectTracer::create($flare)->trace(
        fn (ExpectTrace $trace) => $trace->span(
            fn (ExpectSpan $span) => $span->spanEvent(
                fn (ExpectSpanEvent $spanEvent) => $spanEvent->hasAttribute('custom_attribute', 'test')
            )
        )
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

    FakeSender::instance()->assertRequestsSent(1);

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['overriddenGrouping'])->toBe('exception_message_and_class');
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

    FakeSender::instance()->assertRequestsSent(1);

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['events'])->toHaveCount(1);
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

    FakeSender::instance()->assertRequestsSent(1);

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['context.custom'])->toEqual([
        'extra' => ['key' => 'value'],
    ]);
});

it('can setup a disabled flare', function () {
    $flare = DisabledFlare::make('');

    $flare->sentReports()->all();
    $flare->application()->recordBooting();
    $flare->tracer->startSpan('test');

    expect(true)->toBeTrue();
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
