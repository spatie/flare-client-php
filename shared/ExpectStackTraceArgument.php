<?php

namespace Spatie\FlareClient\Tests\Shared;

class ExpectStackTraceArgument
{
    public function __construct(
        public array $argument
    ) {
    }

    public function expectName(string $name): self
    {
        expect($this->argument['name'])->toBe($name);

        return $this;
    }

    public function expectValue(mixed $value): self
    {
        expect($this->argument['value'])->toBe($value);

        return $this;
    }

    public function expectOriginalType(string $type): self
    {
        expect($this->argument['original_type'])->toBe($type);

        return $this;
    }

    public function expectPassedByReference(bool $passedByReference = true): self
    {
        expect($this->argument['passed_by_reference'])->toBe($passedByReference);

        return $this;
    }

    public function expectIsVariadic(bool $isVariadic = true): self
    {
        expect($this->argument['is_variadic'])->toBe($isVariadic);

        return $this;
    }

    public function expectTruncated(bool $truncated = true): self
    {
        expect($this->argument['truncated'])->toBe($truncated);

        return $this;
    }
}