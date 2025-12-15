<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use DateTimeInterface;
use Exception;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;
use Spatie\FlareClient\Time\TimeHelper;

class ExpectSpan
{
    use ExpectAttributes;

    public ?string $type;

    /** @var array<int, ExpectSpanEvent> */
    protected array $expectSpanEvents;

    public static function fromSpan(array $span): self
    {
        return new self($span);
    }

    public function __construct(
        public array $span,
    ) {
        $this->type =  $this->attributes()['flare.span_type'] ?? null;

        $this->expectSpanEvents = array_map(
            fn (array $spanEvent) => new ExpectSpanEvent($spanEvent),
            $this->span['events'] ?? []
        );
    }

    public function expectName(string $name): self
    {
        expect($this->span['name'])->toBe($name);

        return $this;
    }

    public function expectSpanId(string $spanId): self
    {
        expect($this->span['spanId'])->toEqual($spanId);

        return $this;
    }

    public function expectTraceId(string $expectTrace): self
    {
        expect($this->span['traceId'])->toEqual($expectTrace);

        return $this;
    }


    public function expectParentId(Span|ExpectSpan|string $expectedSpan): self
    {
        $id = match (true) {
            $expectedSpan instanceof Span => $expectedSpan->spanId,
            $expectedSpan instanceof ExpectSpan => $expectedSpan->span['spanId'],
            default => $expectedSpan,
        };

        expect($this->span['parentSpanId'])->toEqual($id);

        return $this;
    }

    public function expectMissingParentId(): self
    {
        expect($this->span['parentSpanId'])->toBeNull();

        return $this;
    }

    public function expectType(FlareSpanType $type): self
    {
        expect($this->attributes()['flare.span_type'])->toEqual($type->value);

        return $this;
    }

    public function expectStart(DateTimeInterface|int $startTimeUnixNano): self
    {
        $expectedStartTimeUnixNano = $startTimeUnixNano instanceof DateTimeInterface
            ? TimeHelper::dateTimeToNano($startTimeUnixNano)
            : $startTimeUnixNano;

        expect($this->span['startTimeUnixNano'])->toEqual($expectedStartTimeUnixNano);

        return $this;
    }

    public function expectEnd(DateTimeInterface|int $endTimeUnixNano): self
    {
        $expectedEndTimeUnixNano = $endTimeUnixNano instanceof DateTimeInterface
            ? TimeHelper::dateTimeToNano($endTimeUnixNano)
            : $endTimeUnixNano;

        expect($this->span['endTimeUnixNano'])->toEqual($expectedEndTimeUnixNano);

        return $this;
    }

    public function expectEnded(): self
    {
        expect($this->span['endTimeUnixNano'])->not()->toBeNull();

        return $this;
    }

    public function expectSpanEventCount(int $count, ?FlareSpanEventType $type = null): self
    {
        $spans = $this->expectSpanEvents;

        if ($type !== null) {
            $spans = array_filter($spans, fn (ExpectSpanEvent $span) => $span->type === $type->value);
        }

        expect($spans)->toHaveCount($count);

        return $this;
    }

    public function expectSpanEvent(int|FlareSpanEventType $index): ExpectSpanEvent
    {
        if (is_int($index)) {
            return $this->expectSpanEvents[$index];
        }

        $expectedSpan = null;

        $this->expectSpanEvents(
            $index,
            function (ExpectSpanEvent $event) use (&$expectedSpan) {
                $expectedSpan = $event;
            }
        );

        return $expectedSpan;
    }

    public function expectSpanEvents(FlareSpanEventType $type, Closure ...$closures): self
    {
        $eventsWithType = array_values(array_filter(
            $this->expectSpanEvents,
            fn (ExpectSpanEvent $event) => $event->type === $type->value
        ));

        $expectedCount = count($closures);
        $realCount = count($eventsWithType);

        expect($eventsWithType)->toHaveCount($expectedCount, "Expected to find {$expectedCount} span events of type {$type->value} but found {$realCount}.");

        foreach ($closures as $i => $closure) {
            $closure($eventsWithType[$i]);
        }

        return $this;
    }

    public function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper)->attributesToPHP($this->span['attributes']);
    }
}
