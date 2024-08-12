<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;

class ExpectSpanEvent
{
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

    public function hasAttributeCount(int $count): self
    {
        expect($this->spanEvent->attributes)->toHaveCount($count);

        return $this;
    }

    public function hasAttribute(string $name, mixed $value): self
    {
        expect($this->spanEvent->attributes[$name])->toEqual($value);

        return $this;
    }
}
