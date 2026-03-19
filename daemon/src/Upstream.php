<?php

namespace Spatie\FlareDaemon;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Spatie\FlareDaemon\Support\Json;

class Upstream
{
    public function __construct(
        protected Browser $browser,
        protected string $baseUrl = 'https://ingress.flareapp.io',
        protected string $userAgent = 'FlareDaemon/dev',
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return PromiseInterface<array{status: int, body: mixed, headers: array<string, array<int, string>>}>
     */
    public function send(string $apiKey, string $type, array $payload): PromiseInterface
    {
        $body = Json::encode($payload);

        return $this->browser->post(
            "{$this->baseUrl}/v1/{$type}",
            [
                'X-API-Token' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->userAgent,
            ],
            $body,
        )->then(function (ResponseInterface $response): array {
            /** @var array<string, array<int, string>> $headers */
            $headers = $response->getHeaders();

            return [
                'status' => $response->getStatusCode(),
                'body' => Json::decodeLoose((string) $response->getBody()),
                'headers' => $headers,
            ];
        });
    }

    public static function reasonFromResponseBody(mixed $body, int $status): string
    {
        if (is_array($body) && is_string($body['message'] ?? null) && $body['message'] !== '') {
            return $body['message'];
        }

        if (is_string($body) && trim($body) !== '') {
            return trim($body);
        }

        return "HTTP {$status}";
    }

    public static function summarizeBody(mixed $body, int $limit = 200): string
    {
        $string = match (true) {
            $body === null => '',
            is_string($body) => $body,
            default => Json::encode($body),
        };

        return mb_strlen($string) <= $limit
            ? $string
            : mb_substr($string, 0, $limit).'...';
    }
}
