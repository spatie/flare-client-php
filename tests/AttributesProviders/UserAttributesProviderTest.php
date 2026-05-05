<?php

use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;
use Spatie\FlareClient\Tests\Shared\FakeUserAttributesProvider;

it('returns an empty array when the user is null', function () {
    $provider = new FakeUserAttributesProvider(null);

    expect($provider->toArray())->toBe([]);
});

it('returns user attributes when the subclass exposes them', function () {
    $provider = new FakeUserAttributesProvider([
        'id' => 42,
        'name' => 'Ruben',
        'email' => 'ruben@spatie.be',
        'attributes' => ['role' => 'admin'],
    ]);

    expect($provider->toArray())->toBe([
        'user.id' => 42,
        'user.full_name' => 'Ruben',
        'user.email' => 'ruben@spatie.be',
        'user.attributes' => ['role' => 'admin'],
    ]);
});

it('filters null and empty values from the attributes array', function () {
    $provider = new FakeUserAttributesProvider([
        'id' => 7,
    ]);

    expect($provider->toArray())->toBe(['user.id' => 7]);
});

it('returns an empty array for the empty provider when no user is present', function () {
    expect((new EmptyUserAttributesProvider(null))->toArray())->toBe([]);
});

it('returns no fields for the empty provider even when a user is set', function () {
    expect((new EmptyUserAttributesProvider(['id' => 1]))->toArray())->toBe([]);
});
