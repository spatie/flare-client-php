<?php

namespace Spatie\FlareClient\Tests\Concerns;

use Spatie\FlareClient\Tests\TestClasses\DumpDriver;
use Spatie\Snapshots\MatchesSnapshots;
use function Spatie\Snapshots\assertMatchesSnapshot;

trait MatchesDumpSnapshots
{
    public function assertMatchesDumpSnapshot(array $codeSnippet)
    {
        assertMatchesSnapshot($codeSnippet, new DumpDriver());
    }
}
