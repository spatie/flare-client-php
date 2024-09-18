<?php

namespace Spatie\FlareClient\Senders;

use Spatie\FlareClient\Senders\Support\Response;

class OtelSender implements Sender
{
    protected Sender|null $reportSender = null;

    /**
     * @param class-string<Sender> $reportSenderClass
     * @param array $reportSenderConfig
     */
    public function __construct(
        public string $otelEndpoint = 'http://localhost:4318/v1/traces',
        public string $reportSenderClass = GuzzleSender::class,
        public array $reportSenderConfig = [],
    ) {
    }

    public function post(string $endpoint, string $apiToken, array $payload): Response
    {
        if (! array_key_exists('resourceSpans', $payload)) {
            return $this->reportSender()->post($endpoint, $apiToken, $payload);
        }

        try {
            return (new GuzzleSender())->post($this->otelEndpoint, $apiToken, $payload);
        } catch (\Exception $e) {
            ray($payload, $e);
        }
    }

    protected function reportSender(): Sender
    {
        return $this->reportSender ??= new $this->reportSenderClass(...$this->reportSenderConfig);
    }
}
