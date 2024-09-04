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

        ray($scope['spans'])->label('Spans');

        return [];
    }
}
