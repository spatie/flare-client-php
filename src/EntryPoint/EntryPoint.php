<?php

namespace Spatie\FlareClient\EntryPoint;

use Spatie\FlareClient\Contracts\AttributesProvider;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\SamplingAttributesProvider;
use Spatie\FlareClient\Enums\EntryPointType;

class EntryPoint
{
    public bool $handlerResolved = false;

    public string $handlerIdentifier;

    public ?string $handlerName;

    public ?string $handlerType;

    /** @var array<string, mixed> */
    public array $samplingAttributes = [];

    public function __construct(
        public EntryPointType $type,
        public string $value,
    ) {
    }

    public function updateValue(string $value): void
    {
        $this->value = $value;
    }

    /** @param array<string, mixed> $samplingAttributes */
    public function setHandler(
        string $handlerIdentifier,
        ?string $handlerName,
        ?string $handlerType,
        array $samplingAttributes = [],
    ): void {
        $this->handlerIdentifier = $handlerIdentifier;
        $this->handlerName = $handlerName;
        $this->handlerType = $handlerType;
        $this->samplingAttributes = $samplingAttributes;
        $this->handlerResolved = true;
    }

    public function setHandlerFromAttributesProvider(
        AttributesProvider&EntryPointHandlerProvider $provider,
    ): void {
        $this->setHandler(
            handlerIdentifier: $provider->entryPointHandlerIdentifier() ?? 'unknown',
            handlerName: $provider->entryPointHandlerName(),
            handlerType: $provider->entryPointHandlerType() ?? 'unknown',
            samplingAttributes: $provider instanceof SamplingAttributesProvider
                ? $provider->samplingAttributes()
                : [],
        );
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
