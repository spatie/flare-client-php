<?php

use Spatie\FlareClient\AttributesProviders\PhpResponseAttributesProvider;
use Spatie\FlareClient\Support\Redactor;

it('returns the status code', function () {
    $provider = new PhpResponseAttributesProvider(new Redactor(), 200);

    expect($provider->toArray())->toBe(['http.response.status_code' => 200]);
    expect($provider->statusCode())->toBe(200);
});

it('includes body size when provided', function () {
    $provider = new PhpResponseAttributesProvider(new Redactor(), 201, bodySize: 42);

    expect($provider->toArray())->toMatchArray([
        'http.response.status_code' => 201,
        'http.response.body.size' => 42,
    ]);
});

it('censors response headers via the redactor', function () {
    $provider = new PhpResponseAttributesProvider(
        new Redactor(censorHeaders: ['Set-Cookie']),
        200,
        headers: [
            'Content-Type' => 'application/json',
            'Set-Cookie' => 'session=abc',
        ],
    );

    $attributes = $provider->toArray();

    expect($attributes['http.response.headers'])
        ->toHaveKey('Content-Type', 'application/json')
        ->toHaveKey('Set-Cookie', '<CENSORED:string>');
});

it('emits an empty payload when nothing is set', function () {
    $provider = new PhpResponseAttributesProvider(new Redactor());

    expect($provider->toArray())->toBe([]);
    expect($provider->statusCode())->toBeNull();
});
