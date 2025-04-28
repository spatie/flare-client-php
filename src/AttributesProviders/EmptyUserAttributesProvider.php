<?php

namespace Spatie\FlareClient\AttributesProviders;

class EmptyUserAttributesProvider extends UserAttributesProvider
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
}
