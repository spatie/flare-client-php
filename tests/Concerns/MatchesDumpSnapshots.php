<?php

namespace Spatie\FlareClient\Tests\Concerns;

use Spatie\FlareClient\Tests\TestClasses\DumpDriver;
use Spatie\Snapshots\MatchesSnapshots;

trait MatchesDumpSnapshots
{
    use MatchesSnapshots;

    public function assertMatchesDumpSnapshot(array $codeSnippet)
    {
        $this->assertMatchesSnapshot($codeSnippet, new DumpDriver());
    }
}
