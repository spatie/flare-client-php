<?php

namespace Tests;

use PHPUnit\Framework\Assert;

class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public string $body,
    ) {
    }

    public function assertUrl(string $expected): self
    {
        Assert::assertSame($expected, $this->url, "Expected URL '{$expected}', got '{$this->url}'");

        return $this;
    }

    public function assertUrlContains(string $expected): self
    {
        Assert::assertStringContainsString($expected, $this->url, "Expected URL to contain '{$expected}', got '{$this->url}'");

        return $this;
    }

    public function assertMethod(string $expected): self
    {
        Assert::assertSame($expected, $this->method, "Expected method '{$expected}', got '{$this->method}'");

        return $this;
    }

    public function assertHeader(string $name, string $expected): self
    {
        Assert::assertArrayHasKey($name, $this->headers, "Expected header '{$name}' not found");
        Assert::assertSame($expected, $this->headers[$name], "Expected header '{$name}' to be '{$expected}', got '{$this->headers[$name]}'");

        return $this;
    }

    public function assertBodyContains(string $expected): self
    {
        $decoded = $this->decompressedBody();

        Assert::assertStringContainsString($expected, $decoded, "Expected body to contain '{$expected}'");

        return $this;
    }

    public function assertBodyEquals(string $expected): self
    {
        $decoded = $this->decompressedBody();

        Assert::assertSame($expected, $decoded);

        return $this;
    }

    public function decompressedBody(): string
    {
        $isGzipped = isset($this->headers['Content-Encoding']) && $this->headers['Content-Encoding'] === 'gzip';

        if ($isGzipped) {
            $decoded = @gzdecode($this->body);

            if ($decoded === false) {
                return $this->body;
            }

            return $decoded;
        }

        return $this->body;
    }

    /**
     * @return array<mixed>
     */
    public function decodedBody(): array
    {
        $decoded = json_decode($this->decompressedBody(), true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
