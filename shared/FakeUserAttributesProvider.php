<?php

namespace Spatie\FlareClient\Tests\Shared;

use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;

class FakeUserAttributesProvider extends UserAttributesProvider
{
    public function id(): string|int|null
    {
        return $this->user['id'] ?? null;
    }

    public function fullName(): ?string
    {
        return $this->user['name'] ?? null;
    }

    public function email(): ?string
    {
        return $this->user['email'] ?? null;
    }

    public function attributes(): array
    {
        return $this->user['attributes'] ?? [];
    }
}
