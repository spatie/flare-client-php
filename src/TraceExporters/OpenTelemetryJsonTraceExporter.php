<?php

namespace Spatie\FlareClient\TraceExporters;

use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Spans\SpanStatus;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;

class OpenTelemetryJsonTraceExporter implements TraceExporter
{
    public function __construct(
        protected OpenTelemetryAttributeMapper $attributeMapper = new OpenTelemetryAttributeMapper()
    ) {
    }

    /**
     * @param array<string, array<string, Span>> $traces
     */
    public function export(
        Resource $resource,
        Scope $scope,
        array $traces,
    ): array {
        $exportedSpans = [];

        foreach ($traces as $spans) {
            foreach ($spans as $span) {
                $exportedSpans[] = $this->exportSpan($span);
            }
        }

        return [
            'resourceSpans' => [
                [
                    'resource' => $this->exportResource($resource),
                    'scopeSpans' => [
                        [
                            'scope' => $this->exportScope($scope),
                            'spans' => $exportedSpans,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function exportResource(Resource $resource): array
    {
        return [
            'attributes' => $this->attributeMapper->attributesToOpenTelemetry(
                $resource->attributes,
            ),
            'droppedAttributesCount' => $resource->droppedAttributesCount,
        ];
    }

    /**
     * @return array{name: string, version: string, attributes: array<string, mixed>, droppedAttributesCount: int}
     */
    protected function exportScope(Scope $scope): array
    {
        return [
            'name' => $scope->name,
            'version' => $scope->version,
            'attributes' => $this->attributeMapper->attributesToOpenTelemetry($scope->attributes),
            'droppedAttributesCount' => $scope->droppedAttributesCount,
        ];
    }

    protected function exportSpan(Span $span): array
    {
        return [
            'traceId' => $span->traceId,
            'spanId' => $span->spanId,
            'parentSpanId' => $span->parentSpanId,
            'name' => $span->name,
            'startTimeUnixNano' => $span->start,
            'endTimeUnixNano' => $span->end,
            'attributes' => $this->attributeMapper->attributesToOpenTelemetry($span->attributes),
            'droppedAttributesCount' => $span->droppedAttributesCount,
            'events' => array_map(fn (SpanEvent $event) => $this->exportSpanEvent($event), $span->events),
            'droppedEventsCount' => $span->droppedEventsCount,
            'links' => [],
            'droppedLinksCount' => 0,
            'status' => $span->status?->toArray() ?? SpanStatus::default(),
        ];
    }

    /**
     * @param SpanEvent $spanEvent
     *
     * @return array{name: string, timeUnixNano: int, attributes: array<string, mixed>, droppedAttributesCount: int}
     */
    protected function exportSpanEvent(SpanEvent $spanEvent): array
    {
        return [
            'name' => $spanEvent->name,
            'timeUnixNano' => $spanEvent->timestamp,
            'attributes' => $this->attributeMapper->attributesToOpenTelemetry($spanEvent->attributes),
            'droppedAttributesCount' => $spanEvent->droppedAttributesCount,
        ];
    }
}
