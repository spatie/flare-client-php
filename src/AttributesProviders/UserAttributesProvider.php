<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\AttributesProvider;

abstract class UserAttributesProvider implements AttributesProvider
{
    public function __construct(
        protected mixed $user,
    ) {
    }

    public function id(): string|int|null
    {
        return null;
    }

    public function fullName(): ?string
    {
        return null;
    }

    public function email(): ?string
    {
        return null;
    }

    public function attributes(): array
    {
        return [];
    }

    public function toArray(): array
    {
        if ($this->user === null) {
            return [];
        }

        return array_filter([
            'user.id' => $this->id(),
            'user.full_name' => $this->fullName(),
            'user.email' => $this->email(),
            'user.attributes' => $this->attributes(),
        ]);
    }
}
