<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
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

    public function hasAttribute(string $name, mixed $value = null): self
    {
        if (func_num_args() === 1) {
            expect($this->spanEvent->attributes)->toHaveKey($name);

            return $this;
        }

        if ($value instanceof Closure) {
            expect($this->spanEvent->attributes)->toHaveKey($name);

            $value($this->spanEvent->attributes[$name]);

            return $this;
        }

        expect($this->spanEvent->attributes[$name])->toEqual($value);

        return $this;
    }
}
