<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Exporters\Exporter;

class FakeExporter implements Exporter
{
    public function traces(Resource $resource, Scope $scope, array $traces): array
    {
        $exportedSpans = [];

        foreach ($traces as $spans) {
            foreach ($spans as $span) {
                $exportedSpans[] = $span;
            }
        }

        return $exportedSpans;
    }

    public function logs(Resource $resource, Scope $scope, array $logs): mixed
    {
        return $logs;
    }
}
