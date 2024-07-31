<?php

namespace Spatie\FlareClient\Exporters;


use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;

class JsonExporter
{
    /**
     * @param array<string, array<string, Span>> $traces
     */
    public function export(
        Resource $resource,
        Scope $scope,
        array $traces,
    ): array {
        $flattenedTraces = [];

        foreach ($traces as $spans) {
            foreach ($spans as $span) {
                $flattenedTraces[] = $span->toTrace();
            }
        }

        return [
            'resourceSpans' => [
                [
                    'resource' => $resource->toArray(),
                    'scopeSpans' => [
                        [
                            'scope' => $scope->toArray(),
                            'spans' => $flattenedTraces,
                        ],
                    ],
                ],
            ],
        ];
    }
}
