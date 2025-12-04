<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes2;

class ExpectSpanEvent2
{
    use ExpectAttributes2;

    public static function create(array $spanEvent): self
    {
        return new self($spanEvent);
    }

    public function __construct(
        public array $spanEvent
    ) {
    }

    public function expectName(string $name): self
    {
        expect($this->spanEvent['name'])->toEqual($name);

        return $this;
    }

    public function expectType(FlareSpanEventType $type): self
    {
        expect($this->attributes()['flare.span_event_type'])->toEqual($type->value);

        return $this;
    }

    protected function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper())->attributesToPHP($this->spanEvent['attributes']);
    }
}
