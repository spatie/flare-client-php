<?php

namespace Spatie\FlareClient\Arguments;

use ReflectionParameter;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\TruncatedReducedArgument;
use function _PHPStan_1f608dc6a\React\Promise\reduce;

class ProvidedArgument
{
    public static function fromReflectionParameter(ReflectionParameter $parameter): self
    {
        return new self(
            $parameter->getName(),
            $parameter->isPassedByReference(),
            $parameter->isVariadic(),
            $parameter->isDefaultValueAvailable(),
            $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
        );
    }

    public static function fromNonReflectableParameter(
        int $index,
    ): self {
        return new self(
            (string) $index,
            false,
            false,
            false,
            false
        );
    }

    public function __construct(
        public string $name,
        public bool $passedByReference = false,
        public bool $isVariadic = false,
        public bool $hasDefaultValue = false,
        public mixed $defaultValue = null,
        public bool $defaultValueUsed = false,
        public bool $truncated = false,
        public mixed $reducedValue = null,
    ) {
        if ($this->isVariadic) {
            $this->defaultValue = [];
        }
    }

    public function setReducedArgument(
        ReducedArgument $reducedArgument
    ): self {
        $this->reducedValue = $reducedArgument->value;

        if ($reducedArgument instanceof TruncatedReducedArgument) {
            $this->truncated = true;
        }

        return $this;
    }

    public function defaultValueUsed(): self
    {
        $this->defaultValueUsed = true;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->defaultValueUsed
                ? $this->defaultValue
                : $this->reducedValue,
            'passed_by_reference' => $this->passedByReference,
            'is_variadic' => $this->isVariadic,
            'truncated' => $this->truncated,
        ];
    }
}
