<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Exception;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;

class ExpectSpan
{
    protected int $spanEventAssertCounter = 0;

    public function __construct(
        public Span $span
    ) {
    }

    public function hasName(string $name): self
    {
        expect($this->span->name)->toEqual($name);

        return $this;
    }

    public function hasParent(Span|ExpectSpan|string $span): self
    {
        $id = match (true){
            $span instanceof Span => $span->spanId,
            $span instanceof ExpectSpan => $span->span->spanId,
            default => $span,
        };

        expect($this->span->parentSpanId)->toEqual($id);

        return $this;
    }

    public function missingParent(): self
    {
        expect($this->span->parentSpanId)->toBeNull();

        return $this;
    }

    public function hasType(FlareSpanType $type): self
    {
        expect($this->span->attributes['flare.span_type'])->toEqual($type);

        return $this;
    }

    public function hasAttributeCount(int $count): self
    {
        expect($this->span->attributes)->toHaveCount($count);

        return $this;
    }

    public function hasAttribute(string $name, mixed $value): self
    {
        expect($this->span->attributes[$name])->toEqual($value);

        return $this;
    }

    public function isEnded(): self
    {
        expect($this->span->end)->not()->toBeNull();

        return $this;
    }

    public function hasSpanEventCount(int $count): self
    {
        expect($this->span->events)->toHaveCount($count);

        return $this;
    }

    public function spanEvent(
        Closure $closure,
        ?SpanEvent &$spanEvent = null,
    ): self
    {
        $spanEvent = array_values($this->span->events)[$this->spanEventAssertCounter] ?? null;

        if($spanEvent === null){
            throw new Exception('Span Event is not recorded');
        }

        $closure(new ExpectSpanEvent($spanEvent));

        $this->spanEventAssertCounter++;

        return $this;
    }
}
