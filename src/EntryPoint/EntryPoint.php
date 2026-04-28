<?php

namespace Spatie\FlareClient\EntryPoint;

use Spatie\FlareClient\Enums\EntryPointType;

class EntryPoint
{
    public bool $handlerResolved = false;

    public string $handlerIdentifier;

    public ?string $handlerName;

    public ?string $handlerType;

    public function __construct(
        public EntryPointType $type,
        public string $value,
    ) {
    }

    public function updateValue(string $value): void
    {
        $this->value = $value;
    }

    public function setHandler(
        string $handlerIdentifier,
        ?string $handlerName,
        ?string $handlerType,
    ): void {
        $this->handlerIdentifier = $handlerIdentifier;
        $this->handlerName = $handlerName;
        $this->handlerType = $handlerType;
        $this->handlerResolved = true;
    }

    /** @return array<string, string|null> */
    public function toAttributes(): array
    {
        $attributes = [
            'flare.entry_point.type' => $this->type->value,
            'flare.entry_point.value' => $this->value,
        ];

        if ($this->handlerResolved) {
            $attributes['flare.entry_point.handler.identifier'] = $this->handlerIdentifier;
            $attributes['flare.entry_point.handler.name'] = $this->handlerName;
            $attributes['flare.entry_point.handler.type'] = $this->handlerType;
        }

        return $attributes;
    }
}
