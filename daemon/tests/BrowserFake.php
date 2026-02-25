<?php

namespace Tests;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;
use Spatie\FlareDaemon\Contracts\Browser;

class BrowserFake implements Browser
{
    /** @var array<int, Request> */
    private array $requests = [];

    /** @var array<string, array<int, ResponseInterface>> */
    private array $queuedResponses = [];

    /** @var ResponseInterface|null */
    private ?ResponseInterface $defaultResponse = null;

    /** @var \Throwable|null */
    private ?\Throwable $defaultError = null;

    public function __construct()
    {
        $this->defaultResponse = Response::created();
    }

    /**
     * @param array<string, string> $headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface
    {
        $request = new Request('POST', $url, $headers, $body);
        $this->requests[] = $request;

        return $this->resolveRequest($url);
    }

    /**
     * @param array<string, string> $headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function get(string $url, array $headers = []): PromiseInterface
    {
        $request = new Request('GET', $url, $headers, '');
        $this->requests[] = $request;

        return $this->resolveRequest($url);
    }

    /**
     * Queue a response for a URL pattern (matched with str_contains).
     */
    public function queueResponse(string $urlPattern, ResponseInterface $response): self
    {
        $this->queuedResponses[$urlPattern][] = $response;

        return $this;
    }

    /**
     * Queue multiple responses for a URL pattern.
     *
     * @param array<int, ResponseInterface> $responses
     */
    public function queueResponses(string $urlPattern, array $responses): self
    {
        foreach ($responses as $response) {
            $this->queueResponse($urlPattern, $response);
        }

        return $this;
    }

    public function setDefaultResponse(ResponseInterface $response): self
    {
        $this->defaultResponse = $response;
        $this->defaultError = null;

        return $this;
    }

    public function setDefaultError(\Throwable $error): self
    {
        $this->defaultError = $error;
        $this->defaultResponse = null;

        return $this;
    }

    /**
     * @return array<int, Request>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }

    /**
     * @return array<int, Request>
     */
    public function postRequests(): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (Request $r) => $r->method === 'POST',
        ));
    }

    /**
     * @return array<int, Request>
     */
    public function getRequests(): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (Request $r) => $r->method === 'GET',
        ));
    }

    public function lastRequest(): ?Request
    {
        if ($this->requests === []) {
            return null;
        }

        return $this->requests[count($this->requests) - 1];
    }

    public function assertRequestCount(int $expected): self
    {
        Assert::assertCount($expected, $this->requests, "Expected {$expected} requests, got " . count($this->requests));

        return $this;
    }

    public function assertPostCount(int $expected): self
    {
        $count = count($this->postRequests());
        Assert::assertSame($expected, $count, "Expected {$expected} POST requests, got {$count}");

        return $this;
    }

    public function assertGetCount(int $expected): self
    {
        $count = count($this->getRequests());
        Assert::assertSame($expected, $count, "Expected {$expected} GET requests, got {$count}");

        return $this;
    }

    public function assertRequestedUrl(string $urlPattern): self
    {
        $found = false;

        foreach ($this->requests as $request) {
            if (str_contains($request->url, $urlPattern)) {
                $found = true;

                break;
            }
        }

        Assert::assertTrue($found, "No request found matching URL pattern '{$urlPattern}'");

        return $this;
    }

    public function assertNoRequests(): self
    {
        Assert::assertEmpty($this->requests, 'Expected no requests, but found ' . count($this->requests));

        return $this;
    }

    public function reset(): void
    {
        $this->requests = [];
        $this->queuedResponses = [];
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
    private function resolveRequest(string $url): PromiseInterface
    {
        foreach ($this->queuedResponses as $pattern => $responses) {
            if (str_contains($url, $pattern) && $responses !== []) {
                /** @var ResponseInterface $response */
                $response = array_shift($this->queuedResponses[$pattern]);

                return \React\Promise\resolve($response);
            }
        }

        if ($this->defaultError !== null) {
            return \React\Promise\reject($this->defaultError);
        }

        if ($this->defaultResponse !== null) {
            return \React\Promise\resolve($this->defaultResponse);
        }

        return \React\Promise\resolve(Response::created());
    }
}
