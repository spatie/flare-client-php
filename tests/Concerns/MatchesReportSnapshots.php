<?php

namespace Spatie\FlareClient\Tests\Concerns;

use Spatie\FlareClient\Tests\TestClasses\ReportDriver;
use function Spatie\Snapshots\assertMatchesSnapshot;

trait MatchesReportSnapshots
{
    public function assertMatchesReportSnapshot(array $report)
    {
        assertMatchesSnapshot($report, new ReportDriver());
    }
}
