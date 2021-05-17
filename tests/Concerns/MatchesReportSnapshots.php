<?php

namespace Spatie\FlareClient\Tests\Concerns;

use Spatie\FlareClient\Tests\TestClasses\ReportDriver;
use Spatie\Snapshots\MatchesSnapshots;

trait MatchesReportSnapshots
{
    use MatchesSnapshots;

    public function assertMatchesReportSnapshot(array $report)
    {
        $this->assertMatchesSnapshot($report, new ReportDriver());
    }
}
