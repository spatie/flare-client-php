<?php

namespace Spatie\FlareClient\Tests\Shared;

class ExpectTrace2
{
    public static function create(array $trace): self
    {
        return new self($trace);
    }

    public function __construct(
        public array $trace
    ) {
    }

    public function expectSpan(int $index): ExpectSpan2
    {
        return new ExpectSpan2($this->trace['resourceSpans'][0]['scopeSpans'][0]['spans'][$index]);
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

    public function expectResource(): ExpectResource2
    {
        return new ExpectResource2($this->trace['resourceSpans'][0]['resource']);
    }

    public function expectScope(): ExpectScope2
    {
        return ExpectScope2::create($this->trace['resourceSpans'][0]['scopeSpans'][0]['scope']);
    }
}
