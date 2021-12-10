<?php

use PHPUnit\Framework\Exception;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Mocks\FakeClient;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;

uses(TestCase::class);
uses(MatchesReportSnapshots::class);

beforeEach(function () {
    $this->fakeClient = new FakeClient();

    $this->flare = new Flare($this->fakeClient);

    $this->useTime('2019-01-01 12:34:56');
});

it('can report an exception', function () {
    reportException();

    $this->fakeClient->assertRequestsSent(1);

    $report = $this->fakeClient->getLastPayload();

    $this->assertMatchesReportSnapshot($report);
});

it('can reset queued exceptions', function () {
    reportException();

    $this->flare->reset();

    $this->fakeClient->assertRequestsSent(1);

    $this->flare->reset();

    $this->fakeClient->assertRequestsSent(1);
});

it('can add user provided context', function () {
    $this->flare->context('my key', 'my value');

    reportException();

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'my value',
    ]);
});

test('callbacks can modify the report', function () {
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
    $_ENV['FLARE_FAKE_WEB_REQUEST'] = true;
    $_POST['user'] = 'john@example.com';
    $_POST['password'] = 'secret';

    $this->flare->censorRequestBodyFields(['user', 'password']);

    reportException();

    $this->fakeClient->assertLastRequestContains('context.request_data.body', [
        'user' => '<CENSORED>',
        'password' => '<CENSORED>',
    ]);
});

it('can merge user provided context', function () {
    $this->flare->context('my key', 'my value');

    $this->flare->context('another key', 'another value');

    reportException();

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can add custom exception context', function () {
    $this->flare->context('my key', 'my value');

    $throwable = new ExceptionWithContext('This is a test');

    $this->flare->report($throwable);

    $this->fakeClient->assertLastRequestHas('context.context', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can add a group', function () {
    $this->flare->group('custom group', ['my key' => 'my value']);

    reportException();

    $this->fakeClient->assertLastRequestHas('context.custom group', [
        'my key' => 'my value',
    ]);
});

it('can return groups', function () {
    $this->flare->context('key', 'value');

    $this->flare->group('custom group', ['my key' => 'my value']);

    $this->assertSame(['key' => 'value'], $this->flare->getGroup());
    $this->assertSame([], $this->flare->getGroup('foo'));
    $this->assertSame(['my key' => 'my value'], $this->flare->getGroup('custom group'));
});

it('can merge groups', function () {
    $this->flare->group('custom group', ['my key' => 'my value']);

    $this->flare->group('custom group', ['another key' => 'another value']);

    reportException();

    $this->fakeClient->assertLastRequestHas('context.custom group', [
        'my key' => 'my value',
        'another key' => 'another value',
    ]);
});

it('can set stages', function () {
    $this->flare->stage('production');

    reportException();

    $this->fakeClient->assertLastRequestHas('stage', 'production');
});

it('can set message levels', function () {
    $this->flare->messageLevel('info');

    reportException();

    $this->fakeClient->assertLastRequestHas('message_level', 'info');
});

it('can add glows', function () {
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

test('a version callable can be set', function () {
    $this->assertNull($this->flare->version());

    $this->flare->determineVersionUsing(function () {
        return '123';
    });

    $this->assertEquals('123', $this->flare->version());
});

it('will add the version to the report', function () {
    reportException();

    $payload = $this->fakeClient->getLastPayload();

    $this->assertNull($payload['application_version']);

    $this->flare->determineVersionUsing(function () {
        return '123';
    });

    reportException();

    $payload = $this->fakeClient->getLastPayload();

    $this->assertEquals('123', $payload['application_version']);
});

it('can filter exceptions being reported', function () {
    reportException();

    $this->fakeClient->assertRequestsSent(1);

    $this->flare->filterExceptionsUsing(function (Throwable $exception) {
        return false;
    });

    reportException();

    $this->fakeClient->assertRequestsSent(1);

    $this->flare->filterExceptionsUsing(function (Throwable $exception) {
        return true;
    });

    reportException();

    $this->fakeClient->assertRequestsSent(2);
});

it('can filter errors based on their level', function () {
    reportError(E_NOTICE);
    reportError(E_WARNING);

    $this->fakeClient->assertRequestsSent(2);

    $this->flare->reportErrorLevels(E_ALL & ~E_NOTICE);

    reportError(E_NOTICE);
    reportError(E_WARNING);

    $this->fakeClient->assertRequestsSent(3);
});

it('can filter error exceptions based on their severity', function () {
    $this->flare->report(new ErrorException('test', 0, E_NOTICE));
    $this->flare->report(new ErrorException('test', 0, E_WARNING));

    $this->fakeClient->assertRequestsSent(2);

    $this->flare->reportErrorLevels(E_ALL & ~E_NOTICE);

    $this->flare->report(new ErrorException('test', 0, E_NOTICE));
    $this->flare->report(new ErrorException('test', 0, E_WARNING));

    $this->fakeClient->assertRequestsSent(3);
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
