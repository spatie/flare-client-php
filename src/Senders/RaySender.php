<?php

namespace Spatie\FlareClient\Senders;

class RaySender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        if (! array_key_exists('resourceSpans', $payload)) {
            ray($payload)->label($endpoint);
        }

        if (count($payload['resourceSpans']) > 1) {
            ray('Found more than one resource span');
        }

        $resource = $payload['resourceSpans'][0];

        ray($resource['resource'])->label('Resource');

        if (count($resource['scopeSpans']) > 1) {
            ray('Found more than one scope span');
        }

        $scope = $resource['scopeSpans'][0];

        ray($scope['scope'])->label('Scope');

        $minUnixNanoTime = PHP_INT_MAX;

        foreach ($scope['spans'] as $i => $span) {
            $minUnixNanoTime = min($minUnixNanoTime, $span['startTimeUnixNano']);
        }

        foreach ($scope['spans'] as $i => $span) {
            try {
                $startDiff = $span['startTimeUnixNano'] - $minUnixNanoTime;
                $endDiff = $span['endTimeUnixNano'] - $minUnixNanoTime;

                $span['startTimeUnixNano'] = "{$span['startTimeUnixNano']} (+ ".number_format($startDiff).")";
                $span['endTimeUnixNano'] = "{$span['endTimeUnixNano']} (+ ".number_format($endDiff).")";
                $span['durationNano'] = number_format($endDiff - $startDiff);

                ray($span)->label("Span $i");
            } catch (\Exception $e) {
                ray($e);
            }
        }

        return [];
    }
}
