<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\TraceExporters\TraceExporter;

class FakeTraceExporter implements TraceExporter
{
    public function export(Resource $resource, Scope $scope, array $traces, array $context): array
    {
        $exportedSpans = [];

        foreach ($traces as $spans) {
            foreach ($spans as $span) {
                $exportedSpans[] = $span;
            }
        }

        return $exportedSpans;
    }
}
