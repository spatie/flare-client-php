<?php

namespace Spatie\FlareClient\Concerns;

trait HasCustomContext
{
    public array $customContext = [];

    public function context(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->customContext = array_merge_recursive_distinct(
                $this->customContext,
                $key
            );

            return $this;
        }

        $this->customContext[$key] = $value;

        return $this;
    }

    public function hydrateCustomContext(array $context): self
    {
        $this->customContext = $context;

        return $this;
    }

    public function clearCustomContext(): self
    {
        $this->customContext = [];

        return $this;
    }
}
