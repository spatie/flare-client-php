<?php

use PHPUnit\Framework\Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Mocks\FakeSender;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;

uses(MatchesReportSnapshots::class);

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
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
        $report->stage('development');
    });

    FakeSender::instance()->assertLastRequestAttribute('context.user', ['my key' => 'new value']);

    expect(FakeSender::instance()->getLastPayload()['stage'])->toBe('development');
});

it('can censor request data', function () {
    setupFlare(fn (FlareConfig $config) => $config->addRequestInfo(
        censorBodyFields: ['user', 'password'])
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

    expect(FakeSender::instance()->getLastPayload()['stage'])->toBe('production');
});


it('can add glows', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addGlows());

    $flare->glow(
        'my glow',
        MessageLevels::INFO,
        ['my key' => 'my value']
    );

    $flare->glow(
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
            'time' => 1546346096000,
        ],
        [
            'name' => 'Glow - another glow',
            'attributes' => [
                'flare.span_event_type' => SpanEventType::Glow,
                'glow.name' => 'another glow',
                'glow.level' => 'error',
                'glow.context' => ['another key' => 'another value'],
            ],
            'time' => 1546346096000,
        ],
    ], $payload['span_events']);
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

    expect($payload['application_version'])->toEqual('123');
});

it('will add the php version to the report', function (){
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
    $flare = setupFlare(fn (FlareConfig $config) => $config->setArgumentReducers(ArgumentReducers::default()));

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


