<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;
use Spatie\FlareClient\Tests\Shared\Concerns\ExpectAttributes;

class ExpectSpanEvent
{
    use ExpectAttributes;

    public ?string $type;

    public function __construct(
        public array $spanEvent,
        public int $depth,
    ) {
        $this->type =  $this->attributes()['flare.span_event_type'] ?? null;
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

    public function attributes(): array
    {
        return (new OpenTelemetryAttributeMapper())->attributesToPHP($this->spanEvent['attributes']);
    }

    public function toString(): string
    {
        $indentPrefix = $this->getIndentPrefix($this->depth);

        $output = [
            "{$indentPrefix}◆ {$this->spanEvent['name']} - " . $this->type ?? 'undefined type'
        ];

        array_push($output, ...$this->attributesToStrings($this->depth, prefix: '  •'));

        return implode(PHP_EOL, $output);
    }
}
