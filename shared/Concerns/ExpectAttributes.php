<?php

namespace Spatie\FlareClient\Tests\Shared\Concerns;

use Closure;

trait ExpectAttributes
{
    abstract public function attributes(): array;

    public function expectAttributesCount(int $count): self
    {
        expect($this->attributes())->toHaveCount($count);

        return $this;
    }

    public function expectAttribute(string $key, mixed $value): self
    {
        if($value instanceof Closure){
            $value($this->attributes()[$key]);

            return $this;
        }

        expect($this->attributes()[$key])->toBe($value);

        return $this;
    }

    public function expectHasAttribute(string $key): self
    {
        expect($this->attributes())->toHaveKey($key);

        return $this;
    }

    public function expectMissingAttribute(string $key): self
    {
        expect($this->attributes())->not->toHaveKey($key);

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
