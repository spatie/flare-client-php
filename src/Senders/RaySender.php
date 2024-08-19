<?php

namespace Spatie\FlareClient\Senders;

class RaySender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        if(! array_key_exists('resourceSpans', $payload)){
            ray($payload)->label($endpoint);
        }

        if(count($payload['resourceSpans']) > 1){
            ray('Found more than one resource span');
        }

        ray($payload['resourceSpans'][0]['resource'])->label('Resource');

        if(count($payload['resourceSpans'][0]['scopeSpans']) > 1){
            ray('Found more than one scope span');
        }

        ray($payload['resourceSpans'][0]['scopeSpans'][0]['scope'])->label('Scope');

        foreach ($payload['resourceSpans'][0]['scopeSpans'][0]['spans'] as $i => $span) {
            ray($span)->label("Span $i")->expand('attributes');
        }

        return [];
    }
}
