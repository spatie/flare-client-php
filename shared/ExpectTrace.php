<?php

namespace Spatie\FlareClient\Tests\Shared;

class ExpectTrace
{
    public static function create(array $trace): self
    {
        return new self($trace);
    }

    public function __construct(
        public array $trace
    ) {
    }

    public function expectSpan(int $index): ExpectSpan
    {
        return new ExpectSpan($this->trace['resourceSpans'][0]['scopeSpans'][0]['spans'][$index]);
    }

    public function expectSpanCount(int $count): self
    {
        expect($this->trace['resourceSpans'][0]['scopeSpans'][0]['spans'])->toHaveCount($count);

        return $this;
    }

    public function expectNoSpans(): self
    {
        expect($this->trace['resourceSpans'][0]['scopeSpans'][0]['spans'])->toBeEmpty();

        return $this;
    }

    public function expectResource(): ExpectResource
    {
        return new ExpectResource($this->trace['resourceSpans'][0]['resource']);
    }

    public function expectScope(): ExpectScope
    {
        return ExpectScope::create($this->trace['resourceSpans'][0]['scopeSpans'][0]['scope']);
    }
}
