<?php

use Spatie\FlareClient\Context\ConsoleContextProvider;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Performance\Support\Timer;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\TestClasses\FakeErrorHandler;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;

uses(MatchesReportSnapshots::class);

beforeEach(function () {
    Report::useTime(new FakeTime('2019-01-01 01:23:45'));
});

it('can create a report', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config);

    $report = $flare->report(new Exception('this is an exception'));

    $this->assertMatchesReportSnapshot($report->toArray());
});

it('will generate a uuid', function () {
    $flare = setupFlare();

    $report = $flare->report(new Exception('this is an exception'));

    expect($report->trackingUuid())->toBeUuid();

    expect($report->toArray()['tracking_uuid'])->toBeString();
});

it('can create a report for a string message', function () {
    $flare = setupFlare();

    $report = $flare->reportMessage('this is a message', 'Log');

    $this->assertMatchesReportSnapshot($report->toArray());
});

it('can create a report with error exception and will cleanup the stack trace', function () {
    $flare = setupFlare();

    FakeErrorHandler::setup(function (ErrorException $exception) use ($flare) {
        ;

        $stacktrace = $flare->report($exception)->toArray()['stacktrace'];

        expect($stacktrace[0]['file'])->toBe(__FILE__);
        expect($stacktrace[0]['arguments'])->toBeNull();
        expect($stacktrace[0]['method'])->toBe(__METHOD__);
    });

    $test->doSomething; // We expect this to fail!
});
