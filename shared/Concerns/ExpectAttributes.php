<?php

namespace Spatie\FlareClient\Tests\Shared\Concerns;

use Closure;
use Spatie\FlareClient\Contracts\WithAttributes;

trait ExpectAttributes
{
    abstract protected function entity(): WithAttributes;

    public function hasAttributeCount(int $count): self
    {
        expect($this->entity()->attributes)->toHaveCount($count);

        return $this;
    }

    public function hasAttribute(string $name, mixed $value = null): self
    {
        if (func_num_args() === 1) {
            expect($this->entity()->attributes)->toHaveKey($name);

            return $this;
        }

        if ($value instanceof Closure) {
            expect($this->entity()->attributes)->toHaveKey($name);

            $value($this->entity()->attributes[$name]);

            return $this;
        }

        expect($this->entity()->attributes[$name])->toEqual($value);

        return $this;
    }
}
