<?php

use PHPUnit\Framework\Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;
use Spatie\FlareClient\Time\Duration;

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

    reportException();

    $flare->reset();

    FakeSender::instance()->assertRequestsSent(1);

    $flare->reset();

    FakeSender::instance()->assertRequestsSent(1);
});

it('can add user provided context', function () {
    $flare = setupFlare();

    $flare->context('my key', 'my value');

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.user', ['my key' => 'my value']);
});

it('can add user provided context easily as an array', function () {
    $flare = setupFlare();

    $flare->context(
        ['my key' => 'my value'],
    );

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.user', ['my key' => 'my value']);
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

    FakeSender::instance()->assertLastRequestAttribute('context.user', ['my key' => 'new value']);

    expect(FakeSender::instance()->getLastPayload()['handled'])->toBeTrue();
});

it('can censor request data', function () {
    setupFlare(
        fn (FlareConfig $config) => $config->addRequestInfo(
            censorBodyFields: ['user', 'password']
        )
    );

    $_ENV['FLARE_FAKE_WEB_REQUEST'] = true;
    $_POST['user'] = 'john@example.com';
    $_POST['password'] = 'secret';

    $_SERVER['REQUEST_METHOD'] = 'POST';

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('http.request.body.contents', [
        'user' => '<CENSORED>',
        'password' => '<CENSORED>',
    ]);
});

it('can merge user provided context', function () {
    $flare = setupFlare();

    $flare->context('my key', 'my value');

    $flare->context('another key', 'another value');

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.user', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can add custom exception context', function () {
    $flare = setupFlare();

    $flare->context('my key', 'my value');

    $throwable = new ExceptionWithContext('This is a test');

    $flare->report($throwable);

    FakeSender::instance()->assertLastRequestAttribute('context.user', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});


it('can merge groups', function () {
    $flare = setupFlare();

    $flare->context(['my key' => 'my value']);

    $flare->context(['another key' => 'another value']);

    reportException();

    FakeSender::instance()->assertLastRequestAttribute('context.user', [
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
    $flare = setupFlare(fn (FlareConfig $config) => $config->addCacheEvents());

    $flare->cache()->recordHit('key', 'store');

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->cache()->recordMiss('key', 'store');

    FakeTime::setup('2019-01-01 12:34:58'); // One second later 1546346098000000

    $flare->cache()->recordKeyWritten('key', 'store');

    FakeTime::setup('2019-01-01 12:34:59'); // One second later 1546346099000000

    $flare->cache()->recordKeyForgotten('key', 'store');

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['span_events'])->toHaveCount(4);

    expect($payload['span_events'][0])
        ->toHaveKey('name', 'Cache hit - key')
        ->toHaveKey('timeUnixNano', 1546346096000000000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheHit);

    expect($payload['span_events'][1])
        ->toHaveKey('name', 'Cache miss - key')
        ->toHaveKey('timeUnixNano', 1546346097000000000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheMiss);

    expect($payload['span_events'][2])
        ->toHaveKey('name', 'Cache key written - key')
        ->toHaveKey('timeUnixNano', 1546346098000000000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheKeyWritten);

    expect($payload['span_events'][3])
        ->toHaveKey('name', 'Cache key forgotten - key')
        ->toHaveKey('timeUnixNano', 1546346099000000000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheKeyForgotten);
});

it('can add glows', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addGlows());

    $flare->glow()->record(
        'my glow',
        MessageLevels::INFO,
        ['my key' => 'my value']
    );

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->glow()->record(
        'another glow',
        MessageLevels::ERROR,
        ['another key' => 'another value']
    );

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    $this->assertEquals([
        [
            'name' => 'Glow - my glow',
            'attributes' => [
                'flare.span_event_type' => SpanEventType::Glow,
                'glow.name' => 'my glow',
                'glow.level' => 'info',
                'glow.context' => ['my key' => 'my value'],
            ],
            'timeUnixNano' => 1546346096000000000,
        ],
        [
            'name' => 'Glow - another glow',
            'attributes' => [
                'flare.span_event_type' => SpanEventType::Glow,
                'glow.name' => 'another glow',
                'glow.level' => 'error',
                'glow.context' => ['another key' => 'another value'],
            ],
            'timeUnixNano' => 1546346097000000000,
        ],
    ], $payload['span_events']);
});

it('can add logs', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addLogs());

    $flare->log()->record(
        'my log',
        MessageLevels::INFO,
        ['my key' => 'my value']
    );

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->log()->record(
        'another log',
        MessageLevels::ERROR,
        ['another key' => 'another value']
    );

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    $this->assertEquals([
        [
            'name' => 'Log entry',
            'attributes' => [
                'flare.span_event_type' => SpanEventType::Log,
                'log.message' => 'my log',
                'log.level' => 'info',
                'log.context' => ['my key' => 'my value'],
            ],
            'timeUnixNano' => 1546346096000000000,
        ],
        [
            'name' => 'Log entry',
            'attributes' => [
                'flare.span_event_type' => SpanEventType::Log,
                'log.message' => 'another log',
                'log.level' => 'error',
                'log.context' => ['another key' => 'another value'],
            ],
            'timeUnixNano' => 1546346097000000000,
        ],
    ], $payload['span_events']);
});

it('can add queries', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addQueries());

    $flare->query()->record(
        'select * from users where id = ?',
        Duration::milliseconds(250),
        ['id' => 1],
        'users',
        'mysql',
    );

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->query()->record(
        'select * from users where id = ?',
        Duration::milliseconds(125),
        ['id' => 2],
        'users',
        'mysql',
    );

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['spans'])->toHaveCount(2);

    expect($payload['spans'][0])
        ->toHaveKey('name', 'Query - select * from users where id = ?')
        ->toHaveKey('startTimeUnixNano', 1546346096000000000 - Duration::milliseconds(250, asNano: true))
        ->toHaveKey('endTimeUnixNano', 1546346096000000000)
        ->attributes
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('db.statement', 'select * from users where id = ?')
        ->toHaveKey('db.sql.bindings', ['id' => 1])
        ->toHaveKey('flare.span_type', SpanType::Query);

    expect($payload['spans'][1])
        ->toHaveKey('name', 'Query - select * from users where id = ?')
        ->toHaveKey('startTimeUnixNano', 1546346097000000000 - Duration::milliseconds(125, asNano: true))
        ->toHaveKey('endTimeUnixNano', 1546346097000000000)
        ->attributes
        ->toHaveKey('db.system', 'mysql')
        ->toHaveKey('db.name', 'users')
        ->toHaveKey('db.statement', 'select * from users where id = ?')
        ->toHaveKey('db.sql.bindings', ['id' => 2])
        ->toHaveKey('flare.span_type', SpanType::Query);
});

it('can begin and commit transactions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addTransactions());

    $flare->transaction()->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->transaction()->recordCommit();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['spans'])->toHaveCount(1);

    expect($payload['spans'][0])
        ->toHaveKey('name', 'DB Transaction')
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', 1546346097000000000)
        ->attributes
        ->toHaveKey('flare.span_type', SpanType::Transaction);
});

it('can begin and rollback transactions', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addTransactions());

    $flare->transaction()->recordBegin();

    FakeTime::setup('2019-01-01 12:34:57'); // One second later 1546346097000000

    $flare->transaction()->recordRollback();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['spans'])->toHaveCount(1);

    expect($payload['spans'][0])
        ->toHaveKey('name', 'DB Transaction')
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', 1546346097000000000)
        ->attributes
        ->toHaveKey('flare.span_type', SpanType::Transaction);
});

test('a version is by default null', function () {
    setupFlare();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['application_version'])->toBeNull();
});

it('will add the version to the report', function () {
    setupFlare(fn (FlareConfig $config) => $config->applicationVersion(function () {
        return '123';
    }));

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['attributes']['service.version'])->toEqual('123');
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

it('will add the php version to the report', function () {
    setupFlare();

    reportException();

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload['language_version'])->toEqual(phpversion());
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
    $flare = setupFlare(fn (FlareConfig $config) => $config->addStackFrameArguments(argumentReducers: ArgumentReducers::default()));

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

    $flare = setupFlare(fn (FlareConfig $config) => $config->addStackFrameArguments(false));

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $flare->report($exception);

    expect(FakeSender::instance()->getLastPayload()['stacktrace'][1]['arguments'])->toBeNull();
});

it('is possible to disable stack frame arguments with zend.exception_ignore_args', function () {
    ini_set('zend.exception_ignore_args', 1);

    $flare = setupFlare(fn (FlareConfig $config) => $config->addStackFrameArguments(false));

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
