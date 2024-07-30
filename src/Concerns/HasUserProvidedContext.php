<?php

namespace Spatie\FlareClient\Concerns;

trait HasUserProvidedContext
{
    public array $userProvidedContext = [];

    public function context(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->userProvidedContext = array_merge_recursive_distinct(
                $this->userProvidedContext,
                $key
            );

            return $this;
        }

        $this->userProvidedContext[$key] = $value;

        return $this;
    }
}
