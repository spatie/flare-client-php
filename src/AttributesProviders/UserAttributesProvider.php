<?php

namespace Spatie\FlareClient\AttributesProviders;

abstract class UserAttributesProvider
{
    abstract public function id(mixed $user): string|int|null;

    abstract public function fullName(mixed $user): string|null;

    abstract public function email(mixed $user): string|null;

    abstract public function attributes(mixed $user): array;

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
