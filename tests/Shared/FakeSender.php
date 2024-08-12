<?php

namespace Spatie\FlareClient\Tests\Shared;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Support\Arr;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Tests\TestClasses\Assert;

class FakeSender implements Sender
{
    use ArraySubsetAsserts;

    /** @var array<int, array{verb: string, fullUrl: string, headers: array<string, string>, arguments: array<string, mixed>}>  */
    protected static array $requests = [];

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
        array $payload
    ): array{
        self::$requests[] = [
            'verb' => 'POST',
            'fullUrl' => $endpoint,
            'headers' => ['X-API-KEY' => $apiToken],
            'arguments' => $payload,
        ];

        return [];
    }

    public function assertRequestsSent(int $expectedCount): void
    {
        expect(count(self::$requests))->toBe($expectedCount);
    }

    public function assertLastRequestAttribute(string $key, mixed $expectedContent = null): void
    {
        expect(count(self::$requests))->toBeGreaterThan(0, 'There were no requests sent');

        $lastPayload = Arr::last(self::$requests)['arguments'];

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
        return Arr::last(self::$requests)['arguments'];
    }
}
