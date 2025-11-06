<?php

namespace Spatie\FlareClient\TraceExporters;

use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;

interface TraceExporter
{
    /**
     * @param array<string, array<array-key, mixed>> $context
     * @param array<string, array<string, Span>> $traces
     *
     * @return array<mixed>
     */
    public function export(
        Resource $resource,
        Scope $scope,
        array $traces,
        array $context
    ): array;
}
