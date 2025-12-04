<?php

namespace Spatie\FlareClient\Tests\Shared\Concerns;

trait ExpectAttributes
{
    abstract protected function attributes(): array;

    public function expectAttributesCount(int $count): self
    {
        expect($this->attributes())->toHaveCount($count);

        return $this;
    }

    public function expectAttribute(string $key, mixed $value): self
    {
        expect($this->attributes()[$key])->toBe($value);

        return $this;
    }

    public function expectHasAttribute(string $key): self
    {
        expect($this->attributes())->toHaveKey($key);

        return $this;
    }

    public function expectAttributes(array $attributes, bool $exact = false): self
    {
        if($exact) {
            expect($this->attributes())->toEqualCanonicalizing($attributes);

            return $this;
        }

        foreach ($attributes as $key => $value) {
            expect($this->attributes()[$key])->toBe($value);
        }

        return $this;
    }
}
