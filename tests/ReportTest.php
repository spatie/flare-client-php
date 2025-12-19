<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;
use Spatie\FlareClient\Tests\TestClasses\FakeErrorHandler;

uses(MatchesReportSnapshots::class);

beforeEach(function () {
    FakeTime::setup('2019-01-01 01:23:45');
});

it('can create a report', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config);

    $report = $flare->report(new Exception('this is an exception', 1337));

    $this->assertMatchesReportSnapshot($report->toArray());
});

it('can create an error exception report', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config);

    set_error_handler(function () {
    }); // Ensure no previous error handler is set so that we don't get deprection warnings

    $flare->registerFlareHandlers();

    try {
        trigger_error('this is a custom error');
    } catch (Error $error) {
    }

    $this->assertMatchesReportSnapshot(FakeApi::lastReport()->toArray());
});

it('will generate a uuid', function () {
    FakeIds::setup()->nextUuid($fakeUuid = '123e4567-e89b-12d3-a456-426614174000');

    $flare = setupFlare();

    $flare->report(new Exception('this is an exception'));

    FakeApi::lastReport()->expectTrackingUuid(
        $fakeUuid
    );
});

it('can create a report with error exception and will cleanup the stack trace', function () {
    $flare = setupFlare();

    FakeErrorHandler::setup(function (ErrorException $exception) use ($flare) {
        $stacktrace = $flare->report($exception)->toArray()['stacktrace'];

        expect($stacktrace[0]['file'])->toContain('ReportTest.php');
        expect($stacktrace[0]['arguments'])->toBeNull();
        expect($stacktrace[0]['method'])->toContain('closure');
    });

    $test->doSomething; // We expect this to fail!
});

it('will keep sent reports', function () {
    $flare = setupFlare();

    $flare->report(new Exception('this is an exception'));

    FakeApi::assertSent(reports: 1);

    expect($flare->sentReports->all())->toHaveCount(1);
});

it('can add a report to a trace', function () {
    FakeIds::setup()->nextUuid('fake-uuid');

    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectErrorsWithTraces()->collectCommands()->trace()->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();
    $flare->command()->recordStart('command', []);

    $flare->report(new ExceptionWithContext('We failed'));

    $flare->command()->recordEnd(1);
    $flare->tracer->endTrace();

    FakeApi::lastTrace()->expectSpan(0)->expectSpanEvent(0)
        ->expectName('Exception - Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext')
        ->expectType(SpanEventType::Exception)
        ->expectAttribute('exception.message', 'We failed')
        ->expectAttribute('exception.type', 'Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext')
        ->expectMissingAttribute('exception.handled', null) // Removed due to otel
        ->expectAttribute('exception.id', 'fake-uuid');
});

it('can create entries for previous exceptions', function () {
    $flare = setupFlare();

    $rootException = new InvalidArgumentException('This is the root cause exception');
    $childException = new RuntimeException('This is the previous exception', previous: $rootException);
    $reportedException = new Exception('This is the main exception', previous: $childException);

    $flare->report($reportedException);

    FakeApi::assertSent(reports: 1);

    $report = FakeApi::lastReport()->expectPreviousCount(2);

    $report->expectPrevious(0)
        ->expectExceptionClass(RuntimeException::class)
        ->expectMessage('This is the previous exception')
        ->expectStacktraceFrame(0)->expectFile(__FILE__);

    $report->expectPrevious(1)
        ->expectExceptionClass(InvalidArgumentException::class)
        ->expectMessage('This is the root cause exception')
        ->expectStacktraceFrame(0)->expectFile(__FILE__);
});
