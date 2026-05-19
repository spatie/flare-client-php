<?php

use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Senders\DaemonSender;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Senders\Support\Response;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeSymfonyTester;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('can report an exception', function () {
    setupFlare();

    FakeSymfonyTester::create()->sendErrorPayload();

    FakeApi::assertSent(reports: 1);

    $report = FakeApi::lastReport()
        ->expectMessage('This is an exception to test if the integration with Flare works.')
        ->expectAttribute('flare.entry_point.type', 'cli')
        ->expectAttribute('flare.entry_point.value', 'flare:test')
        ->expectAttribute('flare.entry_point.handler.identifier', 'flare:test')
        ->expectAttribute('flare.entry_point.handler.type', 'php_command');

    $report->expectEventCount(1, SpanType::Command);

    $report->expectEvent(SpanType::Command)
        ->expectAttribute('process.command', 'flare:test')
        ->expectAttribute('process.exit_code', 0);
});

it('can send a trace', function () {
    setupFlare(alwaysSampleTraces: true);

    FakeSymfonyTester::create()->sendTracePayload();

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

    $commandSpan = $trace->expectSpan(3)
        ->expectName('Command - flare:test')
        ->expectParentId($applicationSpan)
        ->expectType(SpanType::Command)
        ->expectAttribute('process.command', 'flare:test')
        ->expectAttribute('process.exit_code', 0)
        ->expectHasAttribute('flare.peak_memory_usage');

    $commandSpan
        ->expectSpanEventCount(1)
        ->expectSpanEvent(0)
        ->expectType(SpanEventType::Glow)
        ->expectAttribute('glow.name', 'Hi there!');

    $trace->expectSpan(4)
        ->expectType(SpanType::Query)
        ->expectParentId($commandSpan)
        ->expectName('Query - select * from users where id = ?')
        ->expectAttribute('db.system', 'mysql')
        ->expectAttribute('db.name', 'flare')
        ->expectAttribute('db.statement', 'select * from users where id = ?')
        ->expectAttribute('db.sql.bindings', [42]);
});

it('can send logs', function () {
    setupFlare();

    FakeSymfonyTester::create()->sendLogPayload();

    FakeApi::assertSent(logs: 1);

    $logs = FakeApi::lastLog();

    $logs->expectLogCount(8);

    $logs->expectLog(0)
        ->expectBody('This is a DEBUG log message to test Flare integration.')
        ->expectSeverityText('debug');

    $logs->expectLog(1)
        ->expectBody('This is a INFO log message to test Flare integration.')
        ->expectSeverityText('info')
        ->expectAttribute('log.context', ['user_id' => 42, 'tenant' => 'acme']);

    $logs->expectLog(2)
        ->expectBody('This is a NOTICE log message to test Flare integration.')
        ->expectSeverityText('notice')
        ->expectAttribute('log.context', []);

    $logs->expectLog(3)
        ->expectBody('This is a WARNING log message to test Flare integration.')
        ->expectSeverityText('warning');

    $logs->expectLog(4)
        ->expectBody('This is a ERROR log message to test Flare integration.')
        ->expectSeverityText('error')
        ->expectAttribute('log.context', [
            'exception' => 'RuntimeException',
            'file' => '/var/www/app.php',
            'line' => 17,
        ]);

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

it('returns the framework-agnostic environment info rows', function () {
    setupFlare();

    $rows = FakeSymfonyTester::create()->environmentInfoRows();

    $labels = array_column($rows, 0);

    expect($labels)->toBe(['Platform', 'PHP', 'spatie/flare-client-php', 'Curl', 'SSL']);
});

it('formats a bad response with an array body containing a message key', function () {
    setupFlare();

    $exception = new BadResponseCode(new Response(422, ['message' => 'Invalid token']));

    expect(FakeSymfonyTester::create()->describeBadResponseFor($exception))->toBe('422 - Invalid token');
});

it('formats a bad response with a non-empty string body', function () {
    setupFlare();

    $exception = new BadResponseCode(new Response(500, 'Internal server error'));

    expect(FakeSymfonyTester::create()->describeBadResponseFor($exception))->toBe('500 - Internal server error');
});

it('formats a bad response with an empty string body as Unknown error', function () {
    setupFlare();

    $exception = new BadResponseCode(new Response(500, ''));

    expect(FakeSymfonyTester::create()->describeBadResponseFor($exception))->toBe('500 - Unknown error');
});

it('formats a bad response with a non-array non-string body as Unknown error', function () {
    setupFlare();

    $exception = new BadResponseCode(new Response(500, null));

    expect(FakeSymfonyTester::create()->describeBadResponseFor($exception))->toBe('500 - Unknown error');
});

it('warns about the stack frame arguments ini setting when both flags are on', function () {
    setupFlare();

    $previous = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '1');

    try {
        $tester = FakeSymfonyTester::create();

        expect($tester->shouldWarnAboutStackFrameArgumentsIniSettingFor(true))->toBeTrue();
        expect($tester->shouldWarnAboutStackFrameArgumentsIniSettingFor(false))->toBeFalse();
    } finally {
        ini_set('zend.exception_ignore_args', $previous);
    }
});

it('does not warn about the stack frame arguments ini setting when the ini setting is off', function () {
    setupFlare();

    $previous = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');

    try {
        $tester = FakeSymfonyTester::create();

        expect($tester->shouldWarnAboutStackFrameArgumentsIniSettingFor(true))->toBeFalse();
        expect($tester->shouldWarnAboutStackFrameArgumentsIniSettingFor(false))->toBeFalse();
    } finally {
        ini_set('zend.exception_ignore_args', $previous);
    }
});

it('aborts with a key-missing message when the api token is empty', function () {
    setupFlare();

    $tester = FakeSymfonyTester::create(config: new FlareConfig());

    expect($tester->run())->toBeFalse();
    expect($tester->output())->toContain('Flare key not specified');

    FakeApi::assertSent();
});

it('runs the happy path for all enabled entity types and writes the success messages', function () {
    setupFlare(alwaysSampleTraces: true);

    $tester = FakeSymfonyTester::create();

    expect($tester->run())->toBeTrue();

    $text = $tester->output();
    expect($text)->toContain('Flare key specified');
    expect($text)->toContain('Error sent to Flare');
    expect($text)->toContain('Trace sent to Flare');
    expect($text)->toContain('Log sent to Flare');

    FakeApi::assertSent(reports: 1, traces: 1, logs: 1);
});

it('only tests selected entity types when --errors is passed', function () {
    setupFlare();

    $tester = FakeSymfonyTester::create(options: ['errors' => true]);

    expect($tester->run())->toBeTrue();

    $text = $tester->output();
    expect($text)->toContain('Error sent to Flare');
    expect($text)->not->toContain('Trace sent to Flare');
    expect($text)->not->toContain('Log sent to Flare');

    FakeApi::assertSent(reports: 1, traces: 0, logs: 0);
});

it('writes a disabled message and skips sending when an entity type is disabled', function () {
    setupFlare();

    $tester = FakeSymfonyTester::create(options: ['errors' => true, 'logs' => true]);
    $tester->entityEnabled = [FlareEntityType::Logs->value => false];

    expect($tester->run())->toBeTrue();
    expect($tester->output())->toContain('Logging is disabled');

    FakeApi::assertSent(reports: 1, logs: 0);
});

it('writes the stack frame arguments ini warning before sending errors when both flags are on', function () {
    setupFlare();

    $previous = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '1');

    try {
        $tester = FakeSymfonyTester::create(options: ['errors' => true]);
        $tester->stackFrameArgumentsOn = true;

        $tester->run();

        $text = $tester->output();
        $iniPos = strpos($text, 'zend.exception_ignore_args');
        $sentPos = strpos($text, 'Error sent to Flare');

        expect($iniPos)->not->toBeFalse();
        expect($sentPos)->not->toBeFalse();
        expect($iniPos)->toBeLessThan($sentPos);
    } finally {
        ini_set('zend.exception_ignore_args', $previous);
    }
});

it('warns when sending logs without the daemon sender', function () {
    setupFlare();

    $tester = FakeSymfonyTester::create(options: ['logs' => true]);

    expect($tester->run())->toBeTrue();
    expect($tester->output())->toContain('Logs are being sent without the Flare daemon');

    FakeApi::assertSent(logs: 1);
});

it('does not warn about the daemon sender when it is configured', function () {
    setupFlare(fn (FlareConfig $config) => $config->sender = DaemonSender::class);

    $tester = FakeSymfonyTester::create(
        options: ['logs' => true],
        config: new FlareConfig(apiToken: 'fake-api-key', sender: DaemonSender::class),
    );

    expect($tester->run())->toBeTrue();
    expect($tester->output())->not->toContain('Logs are being sent without the Flare daemon');
});

it('aborts when a per-entity pre-check returns false', function () {
    setupFlare();

    $tester = FakeSymfonyTester::create(options: ['errors' => true, 'logs' => true]);
    $tester->preCheckCallback = function (FlareEntityType $type, FakeSymfonyTester $tester): bool {
        if ($type === FlareEntityType::Errors) {
            $tester->writeLinePublic('Custom pre-check failed for errors');

            return false;
        }

        return true;
    };

    expect($tester->run())->toBeFalse();
    expect($tester->output())->toContain('Custom pre-check failed for errors');

    FakeApi::assertSent();
});

it('renders the failure block including the extra environment rows when sending fails', function () {
    setupFlare(
        fn (FlareConfig $config) => $config->sender = FakeSender::class,
        useFakeApi: false,
        disableApiQueue: true,
    );

    FakeSender::$responseCode = 500;
    FakeSender::$responseBody = ['message' => 'Server error'];

    $tester = FakeSymfonyTester::create(options: ['errors' => true]);
    $tester->extraEnvironmentRows = [['Framework', '1.2.3']];

    expect($tester->run())->toBeFalse();

    $text = $tester->output();
    expect($text)->toContain('500 - Server error');
    expect($text)->toContain('Framework');
    expect($text)->toContain('1.2.3');
    expect($text)->toContain('Platform');
});
