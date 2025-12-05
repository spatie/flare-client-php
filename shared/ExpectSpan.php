<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use DateTimeInterface;
use Exception;
use Spatie\FlareClient\Concerns\HasAttributes;
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

    public static function fromSpan(array $span): self
    {
        return new self($span);
    }

    public function __construct(
        public array $span
    ) {
    }

    public function expectName(string $name): self
    {
        expect($this->span['name'])->toBe($name);

        return $this;
    }

    public function expectId(string $spanId): self
    {
        expect($this->span['spanId'])->toEqual($spanId);

        return $this;
    }

    public function expectTrace(string $expectTrace): self
    {
        expect($this->span['traceId'])->toEqual($expectTrace);

        return $this;
    }


    public function expectParent(Span|ExpectSpan|string $expectedSpan): self
    {
        $id = match (true) {
            $expectedSpan instanceof Span => $expectedSpan->spanId,
            $expectedSpan instanceof ExpectSpan => $expectedSpan->span['spanId'],
            default => $expectedSpan,
        };

        expect($this->span['parentSpanId'])->toEqual($id);

        return $this;
    }

    public function expectMissingParent(): self
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

    public function expectSpanEventCount(int $count): self
    {
        expect($this->span['events'])->toHaveCount($count);

        return $this;
    }

    public function expectSpanEvent(
        int $index,
    ): ExpectSpanEvent {
        return new ExpectSpanEvent($this->span['events'][$index]);
    }

    protected function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper)->attributesToPHP($this->span['attributes']);
    }
}
