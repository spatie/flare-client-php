<?php

use Exception;
use Spatie\FlareClient\Context\ConsoleContextProvider;
use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;

uses(TestCase::class);
uses(MatchesReportSnapshots::class);

beforeEach(function () {
    Report::useTime(new FakeTime('2019-01-01 01:23:45'));
});

it('can create a report', function () {
    $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

    $report = $report->toArray();

    $this->assertMatchesReportSnapshot($report);
});

it('will generate a uuid', function () {
    $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

    $this->assertIsString($report->trackingUuid());

    $this->assertIsString($report->toArray()['tracking_uuid']);
});

it('can create a report for a string message', function () {
    $report = Report::createForMessage('this is a message', 'Log', new ConsoleContextProvider());

    $report = $report->toArray();

    $this->assertMatchesReportSnapshot($report);
});

it('can create a report with glows', function () {
    /** @var Report $report */
    $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

    $report->addGlow(new Glow('Glow 1', 'info', ['meta' => 'data']));

    $report = $report->toArray();

    $this->assertMatchesReportSnapshot($report);
});

it('can create a report with meta data', function () {
    /** @var Report $report */
    $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

    $metadata = [
        'some' => 'data',
        'something' => 'more',
    ];

    $report->userProvidedContext(['meta' => $metadata]);

    $this->assertEquals($metadata, $report->toArray()['context']['meta']);
});
