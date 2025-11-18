<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class FakeSender implements Sender
{
    /** @var array<int, array{verb: string, fullUrl: string, headers: array<string, string>, arguments: array<string, mixed>}> */
    public static array $requests = [];

    public static function reset(): void
    {
        self::$requests = [];
    }

    public static function instance(): self
    {
        return new self();
    }

    public function post(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlarePayloadType $type,
        Closure $callback
    ): void {
        self::$requests[] = [
            'verb' => 'POST',
            'fullUrl' => $endpoint,
            'headers' => ['X-API-KEY' => $apiToken],
            'arguments' => $payload,
        ];

        $callback(new Response(200, []));
    }

    public function assertRequestsSent(int $expectedCount): void
    {
        expect(count(self::$requests))->toBe($expectedCount);
    }

    public function assertLastRequestAttribute(string $key, mixed $expectedContent = null): void
    {
        expect(count(self::$requests))->toBeGreaterThan(0, 'There were no requests sent');

        $lastPayload = end(self::$requests) ? end(self::$requests)['arguments'] : null;

        expect(array_key_exists($key, $lastPayload['attributes']))->toBeTrue('The last payload doesnt have the expected key. ');

        if ($expectedContent === null) {
            return;
        }

        $actualContent = $lastPayload['attributes'][$key];

        expect($actualContent)->toEqual($expectedContent);
    }

    /** @return array<string, mixed>|null */
    public function getLastPayload(): ?array
    {
        return end(self::$requests) ? end(self::$requests)['arguments'] : null;
    }
}
