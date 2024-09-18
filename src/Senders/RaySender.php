<?php

namespace Spatie\FlareClient\Senders;

use Spatie\FlareClient\Senders\Support\Response;

class RaySender implements Sender
{
    public function __construct(
        protected bool $raw = false,
    ) {
    }

    public function post(string $endpoint, string $apiToken, array $payload): Response
    {
        if ($this->raw) {
            ray($payload)->label($endpoint);

            return [];
        }

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

        return new Response(200, []);
    }
}
