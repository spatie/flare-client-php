<?php

namespace Spatie\FlareClient\Exporters;

use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;

interface Exporter
{
    /**
     * @param array<int, Span> $spans
     */
    public function traces(
        Resource $resource,
        Scope $scope,
        array $spans,
    ): mixed;

    /**
     * @param array<int, array{time_unix_nano: int, observed_time_unix_nano: int, trace_id?: string, span_id?: string, flags?: string, severity_text?: string, severity_number?: int, body: mixed, attributes?: array<string, mixed>}> $logs
     */
    public function logs(
        Resource $resource,
        Scope $scope,
        array $logs,
    ): mixed;

    public function report(
        ReportFactory $report,
    ): mixed;
}
