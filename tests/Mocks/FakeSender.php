<?php

namespace Spatie\FlareClient\Tests\Mocks;

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
        Assert::assertCount($expectedCount, self::$requests);
    }

    public function assertLastRequestAttribute(string $key, mixed $expectedContent = null): void
    {
        Assert::assertGreaterThan(0, count(self::$requests), 'There were no requests sent');

        $lastPayload = Arr::last(self::$requests)['arguments'];

        Assert::assertTrue(array_key_exists($key, $lastPayload['attributes']), 'The last payload doesnt have the expected key. ');

        if ($expectedContent === null) {
            return;
        }

        $actualContent = $lastPayload['attributes'][$key];

        Assert::assertEquals($expectedContent, $actualContent);
    }

    /** @return array<string, mixed>|null */
    public function getLastPayload(): ?array
    {
        return Arr::last(self::$requests)['arguments'];
    }
}
