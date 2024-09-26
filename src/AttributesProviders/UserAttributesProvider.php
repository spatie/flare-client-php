<?php

namespace Spatie\FlareClient\AttributesProviders;

class UserAttributesProvider
{
    public function id(mixed $user): string|int|null
    {
        return null;
    }

    public function fullName(mixed $user): string|null
    {
        return null;
    }

    public function email(mixed $user): string|null
    {
        return null;
    }

    public function attributes(mixed $user): array
    {
        return [];
    }

    public function toArray(mixed $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_filter([
            'user.id' => $this->id($user),
            'user.full_name' => $this->fullName($user),
            'user.email' => $this->email($user),
            'user.attributes' => $this->attributes($user),
        ]);
    }
}
