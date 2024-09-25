<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Spans\SpanEvent;

class ExpectSpanEvent
{
    use Concerns\ExpectAttributes;

    public function __construct(
        public SpanEvent $spanEvent
    ) {
    }

    public function hasName(string $name): self
    {
        expect($this->spanEvent->name)->toEqual($name);

        return $this;
    }

    public function hasType(FlareSpanEventType $type): self
    {
        expect($this->spanEvent->attributes['flare.span_event_type'])->toEqual($type);

        return $this;
    }

    protected function entity(): WithAttributes
    {
        return $this->spanEvent;
    }
}
