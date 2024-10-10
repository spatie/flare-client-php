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
}
