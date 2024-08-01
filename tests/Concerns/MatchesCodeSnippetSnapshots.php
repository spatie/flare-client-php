<?php

namespace Spatie\FlareClient\Tests\Concerns;

use Spatie\FlareClient\Tests\TestClasses\CodeSnippetDriver;
use function Spatie\Snapshots\assertMatchesSnapshot;

trait MatchesCodeSnippetSnapshots
{
    public function assertMatchesCodeSnippetSnapshot(array $codeSnippet)
    {
        $codeSnippet = $this->removeMicrotime($codeSnippet);
        $codeSnippet = $this->removeTime($codeSnippet);

        assertMatchesSnapshot($codeSnippet, new CodeSnippetDriver());
    }

    private function removeMicrotime(array $codeSnippet): array
    {
        array_walk_recursive($codeSnippet, function (&$value, $key) {
            if ($key === 'microtime') {
                $value = '1234';
            }
        });

        return $codeSnippet;
    }

    private function removeTime(array $codeSnippet): array
    {
        array_walk_recursive($codeSnippet, function (&$value, $key) {
            if ($key === 'time') {
                $value = 1234;
            }
        });

        return $codeSnippet;
    }
}
