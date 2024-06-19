<?php

use PHPUnit\Framework\Exception;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Mocks\FakeClient;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;

uses(MatchesReportSnapshots::class);

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('can report an exception', function () {
    setupFlare();

    reportException();

    $this->fakeClient->assertRequestsSent(1);

    $report = $this->fakeClient->getLastPayload();

    $this->assertMatchesReportSnapshot($report);
});

it('can report a initialised report instance', function () {
    setupFlare();

    $throwable = new Exception('This is a test');

    $report = $this->flare->createReport($throwable);

    $this->flare->report($throwable, report: $report);

    $this->fakeClient->assertRequestsSent(1);

    $report = $this->fakeClient->getLastPayload();

    $this->assertMatchesReportSnapshot($report);
});

it('can reset queued exceptions', function () {
    setupFlare();

    reportException();

    $this->flare->reset();

    $this->fakeClient->assertRequestsSent(1);

    $this->flare->reset();

    $this->fakeClient->assertRequestsSent(1);
});

it('can add user provided context', function () {
    setupFlare();

    $this->flare->context('my key', 'my value');

    reportException();

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'my value',
    ]);
});

test('callbacks can modify the report', function () {
    setupFlare();

    $this->flare->context('my key', 'my value');
    $this->flare->stage('production');
    $this->flare->messageLevel('info');

    $throwable = new Exception('This is a test');

    $this->flare->report($throwable, function ($report) {
        $report->context('my key', 'new value');
        $report->stage('development');
        $report->messageLevel('warning');
    });

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'new value',
    ]);
    $this->fakeClient->assertLastRequestHas('stage', 'development');
    $this->fakeClient->assertLastRequestHas('message_level', 'warning');
});

it('can censor request data', function () {
    setupFlare(fn(FlareConfig $config) => $config->censorRequestBodyFields(['user', 'password']));

    $_ENV['FLARE_FAKE_WEB_REQUEST'] = true;
    $_POST['user'] = 'john@example.com';
    $_POST['password'] = 'secret';

    reportException();

    $this->fakeClient->assertLastRequestContains('context.request_data.body', [
        'user' => '<CENSORED>',
        'password' => '<CENSORED>',
    ]);
});

it('can merge user provided context', function () {
    setupFlare();

    $this->flare->context('my key', 'my value');

    $this->flare->context('another key', 'another value');

    reportException();

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can add custom exception context', function () {
    setupFlare();

    $this->flare->context('my key', 'my value');

    $throwable = new ExceptionWithContext('This is a test');

    $this->flare->report($throwable);

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can add a group', function () {
    setupFlare();

    $this->flare->group('custom group', ['my key' => 'my value']);

    reportException();

    $this->fakeClient->assertLastRequestHas('context.custom group', [
        'my key' => 'my value',
    ]);
});

it('can return groups', function () {
    setupFlare();

    $this->flare->context('key', 'value');

    $this->flare->group('custom group', ['my key' => 'my value']);

    expect($this->flare->getGroup())->toBe(['key' => 'value']);
    expect($this->flare->getGroup('foo'))->toBe([]);
    expect($this->flare->getGroup('custom group'))->toBe(['my key' => 'my value']);
});

it('can merge groups', function () {
    setupFlare();

    $this->flare->group('custom group', ['my key' => 'my value']);

    $this->flare->group('custom group', ['another key' => 'another value']);

    reportException();

    $this->fakeClient->assertLastRequestHas('context.custom group', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can set stages', function () {
    setupFlare(fn(FlareConfig $config) => $config->applicationStage('production'));

    reportException();

    $this->fakeClient->assertLastRequestHas('stage', 'production');
});

it('can set message levels', function () {
    setupFlare();

    $this->flare->messageLevel('info');

    reportException();

    $this->fakeClient->assertLastRequestHas('message_level', 'info');
});

it('can add glows', function () {
    setupFlare(fn(FlareConfig $config) => $config->withGlows());

    $this->flare->glow(
        'my glow',
        MessageLevels::INFO,
        ['my key' => 'my value']
    );

    $this->flare->glow(
        'another glow',
        MessageLevels::ERROR,
        ['another key' => 'another value']
    );

    reportException();

    $payload = $this->fakeClient->getLastPayload();

    $glows = collect($payload['glows'])->map(function ($glow) {
        unset($glow['microtime']);

        return $glow;
    })->toArray();

    $this->assertEquals([
        [
            'name' => 'my glow',
            'message_level' => 'info',
            'meta_data' => ['my key' => 'my value'],
            'time' => 1546346096,
        ],
        [
            'name' => 'another glow',
            'message_level' => 'error',
            'meta_data' => ['another key' => 'another value'],
            'time' => 1546346096,
        ],
    ], $glows);
});

test('a version is by default null', function () {
    setupFlare();

    reportException();

    $payload = $this->fakeClient->getLastPayload();

    expect($payload['application_version'])->toBeNull();
});

it('will add the version to the report', function () {
    setupFlare(fn(FlareConfig $config) => $config->applicationVersion(function () {
        return '123';
    }));

    reportException();

    $payload = $this->fakeClient->getLastPayload();

    expect($payload['application_version'])->toEqual('123');
});

it('can filter exceptions being reported', function () {
    setupFlare(fn(FlareConfig $config) => $config->filterExceptionsUsing(fn (Throwable $exception) => false));

    reportException();

    $this->fakeClient->assertRequestsSent(0);
});

it('can filter exceptions being reported and allow them', function () {
    setupFlare(fn(FlareConfig $config) => $config->filterExceptionsUsing(fn (Throwable $exception) => true));

    reportException();

    $this->fakeClient->assertRequestsSent(1);
});

it('can filter errors based on their level', function () {
    setupFlare(fn(FlareConfig $config) => $config->reportErrorLevels(E_ALL & ~E_NOTICE));

    reportError(E_NOTICE);
    reportError(E_WARNING);

    $this->fakeClient->assertRequestsSent(1);
});

it('can filter error exceptions based on their severity', function () {
    setupFlare(fn(FlareConfig $config) => $config->reportErrorLevels(E_ALL & ~E_NOTICE));

    $this->flare->report(new ErrorException('test', 0, E_NOTICE));
    $this->flare->report(new ErrorException('test', 0, E_WARNING));

    $this->fakeClient->assertRequestsSent(1);
});

it('will add arguments to a stack trace by default', function () {
    // Todo: add some default argument reducers in the config
    setupFlare();

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $this->flare->report($exception);

    $this->fakeClient->assertLastRequestHas('stacktrace.1.arguments', [
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

    setupFlare(fn(FlareConfig $config) => $config->withStackFrameArguments(false));

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $this->flare->report($exception);

    $this->fakeClient->assertLastRequestHas('stacktrace.0.arguments', null);
});

it('is possible to disable stack frame arguments with zend.exception_ignore_args', function () {
    ini_set('zend.exception_ignore_args', 1);

    setupFlare(fn(FlareConfig $config) => $config->withStackFrameArguments(false));

    $exception = TraceArguments::create()->exception(
        'a message',
        new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
    );

    $this->flare->report($exception);

    $this->fakeClient->assertLastRequestHas('stacktrace.0.arguments', null);
});

it('can report a handled error', function () {
    setupFlare();

    $throwable = new Exception('This is a test');

    $this->flare->reportHandled($throwable);

    $this->fakeClient->assertRequestsSent(1);

    $report = $this->fakeClient->getLastPayload();

    expect($report['handled'])->toBeTrue();
});

/**
 * @param ?Closure(FlareConfig):void $closure
 */
function setupFlare(
    ?Closure $closure = null,
    bool $sendReportsImmediately = true,
    bool $useFakeClient = true
): Flare {
    $client = null;

    if($useFakeClient){
        $client = new FakeClient();
        test()->fakeClient = $client;
    }

    $config = new FlareConfig(
        apiToken: 'fake-api-key',
        sendReportsImmediately: $sendReportsImmediately,
        client: $client
    );

    if($closure){
        $closure($config);
    }

    return test()->flare = Flare::makeFromConfig($config);
}

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
