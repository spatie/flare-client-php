<?php

namespace Spatie\FlareClient\Tests\Mocks;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Support\Arr;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Http\Response;
use Spatie\FlareClient\Tests\TestClasses\Assert;

class FakeClient extends Client
{
    use ArraySubsetAsserts;

    static self $self;

    /** @var array<int, array{verb: string, fullUrl: string, headers: array<string, string>, arguments: array<string, mixed>}>  */
    protected array $requests = [];

    public static function setup(): self
    {
        if(!isset(static::$self)) {
            return static::$self;
        }

        return static::$self = new self();
    }

    public function __construct(string $apiToken = null)
    {
        parent::__construct($apiToken ?? uniqid());
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function post(string $url, array $arguments = []): array
    {
        $this->requests[] = [
            'verb' => 'POST',
            'fullUrl' => "{$this->baseUrl}/{$url}",
            'headers' => ['X-API-KEY' => $this->apiToken],
            'arguments' => $arguments,
        ];

        return [];
    }

    public function assertRequestsSent(int $expectedCount): void
    {
        Assert::assertCount($expectedCount, $this->requests);
    }

    public function assertLastRequestHas(string $key, mixed $expectedContent = null): void
    {
        Assert::assertGreaterThan(0, count($this->requests), 'There were no requests sent');

        $lastPayload = Arr::last($this->requests)['arguments'];

        Assert::assertTrue(Arr::has($lastPayload, $key), 'The last payload doesnt have the expected key. ');

        if ($expectedContent === null) {
            return;
        }

        $actualContent = Arr::get($lastPayload, $key);

        Assert::assertEquals($expectedContent, $actualContent);
    }

    public function assertLastRequestContains(string $key, mixed $expectedContent = null): void
    {
        Assert::assertGreaterThan(0, count($this->requests), 'There were no requests sent');

        $lastPayload = Arr::last($this->requests)['arguments'];

        Assert::assertTrue(Arr::has($lastPayload, $key), 'The last payload doesnt have the expected key. ');

        if ($expectedContent === null) {
            return;
        }

        $actualContent = Arr::get($lastPayload, $key);

        self::assertArraySubset($expectedContent, $actualContent);
    }

    /** @return array<string, mixed>|null */
    public function getLastPayload(): ?array
    {
        return Arr::last($this->requests)['arguments'];
    }
}
