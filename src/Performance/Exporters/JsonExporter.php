<?php

namespace Spatie\FlareClient\Performance\Exporters;


use Spatie\FlareClient\Performance\Resources\Resource;
use Spatie\FlareClient\Performance\Scopes\Scope;
use Spatie\FlareClient\Performance\Spans\Span;

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
                $flattenedTraces[] = $span->toArray();
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
