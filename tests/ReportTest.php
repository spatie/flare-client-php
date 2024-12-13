<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\FakeErrorHandler;

uses(MatchesReportSnapshots::class);

beforeEach(function () {
    FakeTime::setup('2019-01-01 01:23:45');
});

it('can create a report', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config);

    $report = $flare->report(new Exception('this is an exception'));

    $this->assertMatchesReportSnapshot($report->toArray());
});

it('can create an error exception report', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config);

    $flare->registerFlareHandlers();

    try {
        trigger_error('this is a custom error', E_USER_ERROR);
    } catch (Error $error) {

    }

    $this->assertMatchesReportSnapshot(FakeSender::instance()->getLastPayload());
});


it('will generate a uuid', function () {
    $flare = setupFlare();

    $report = $flare->report(new Exception('this is an exception'));

    expect($report->trackingUuid)->toBeUuid();

    expect($report->toArray()['trackingUuid'])->toBeString();
});

it('can create a report for a string message', function () {
    $flare = setupFlare();

    $report = $flare->reportMessage('this is a message', 'Error');

    $this->assertMatchesReportSnapshot($report->toArray());
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

    $report = $flare->report(new Exception('this is an exception'));

    FakeSender::instance()->assertRequestsSent(1);

    expect($flare->sentReports()->all())->toHaveCount(1);
});
