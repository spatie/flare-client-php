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

    protected $requests = [];

    public function __construct($apiToken = null, $apiSecret = null)
    {
        parent::__construct($apiToken ?? uniqid(), $apiSecret ?? uniqid());
    }

    public function makeCurlRequest(string $httpVerb, string $fullUrl, array $headers = [], array $arguments = []): Response
    {
        $this->requests[] = compact('httpVerb', 'fullUrl', 'headers', 'arguments');

        return new Response([], 'my response', '');
    }

    public function assertRequestsSent(int $expectedCount)
    {
        Assert::assertCount($expectedCount, $this->requests);
    }

    public function assertLastRequestHas($key, $expectedContent = null)
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

    public function assertLastRequestContains($key, $expectedContent = null)
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

    public function getLastPayload(): ?array
    {
        return Arr::last($this->requests)['arguments'];
    }
}
