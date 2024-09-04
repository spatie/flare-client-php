<?php

namespace Spatie\FlareClient\AttributesProviders;

class CommonEntitiesAttributesProvider
{
    public function userName(mixed $user): string
    {
        return $user->name ?? $user['name'] ?? 'unknown';
    }

    public function userEmail(mixed $user): string
    {
        return $user->email ?? $user['email'] ?? 'unknown';
    }

    public function extraUserAttributes(mixed $user): array
    {
        return [];
    }
}
