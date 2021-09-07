<?php

namespace Spatie\FlareClient\Tests;

use Exception;
use Spatie\FlareClient\Context\ConsoleContextProvider;
use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Tests\Concerns\MatchesReportSnapshots;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;

class ReportTest extends TestCase
{
    use MatchesReportSnapshots;

    public function setUp(): void
    {
        parent::setUp();

        Report::useTime(new FakeTime('2019-01-01 01:23:45'));
    }

    /** @test */
    public function it_can_create_a_report()
    {
        $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

        $report = $report->toArray();

        $this->assertMatchesReportSnapshot($report);
    }

    /** @test */
    public function it_will_generate_a_uuid()
    {
        $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

        $this->assertIsString($report->trackingUuid());

        $this->assertIsString($report->toArray()['tracking_uuid']);
    }

    /** @test */
    public function it_can_create_a_report_for_a_string_message()
    {
        $report = Report::createForMessage('this is a message', 'Log', new ConsoleContextProvider());

        $report = $report->toArray();

        $this->assertMatchesReportSnapshot($report);
    }

    /** @test */
    public function it_can_create_a_report_with_glows()
    {
        /** @var Report $report */
        $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

        $report->addGlow(new Glow('Glow 1', 'info', ['meta' => 'data']));

        $report = $report->toArray();

        $this->assertMatchesReportSnapshot($report);
    }

    /** @test */
    public function it_can_create_a_report_with_meta_data()
    {
        /** @var Report $report */
        $report = Report::createForThrowable(new Exception('this is an exception'), new ConsoleContextProvider());

        $metadata = [
            'some' => 'data',
            'something' => 'more',
        ];

        $report->userProvidedContext(['meta' => $metadata]);

        $this->assertEquals($metadata, $report->toArray()['context']['meta']);
    }
}
