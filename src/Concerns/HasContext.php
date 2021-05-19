<?php

namespace Spatie\FlareClient\Concerns;

trait HasContext
{
    protected ?string $messageLevel = null;

    protected ?string $stage = null;

    protected array $userProvidedContext = [];

    public function stage(?string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public function messageLevel(?string $messageLevel): self
    {
        $this->messageLevel = $messageLevel;

        return $this;
    }

    public function getGroup(string $groupName = 'context', $default = []): array
    {
        return $this->userProvidedContext[$groupName] ?? $default;
    }

    public function context($key, $value): self
    {
        return $this->group('context', [$key => $value]);
    }

    public function group(string $groupName, array $properties): self
    {
        $group = $this->userProvidedContext[$groupName] ?? [];

        $this->userProvidedContext[$groupName] = array_merge_recursive_distinct(
            $group,
            $properties
        );

        return $this;
    }
}
