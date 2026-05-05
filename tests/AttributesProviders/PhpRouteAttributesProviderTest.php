<?php

use Spatie\FlareClient\AttributesProviders\PhpRouteAttributesProvider;

beforeEach(function () {
    $this->originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
});

afterEach(function () {
    if ($this->originalRequestMethod === null) {
        unset($_SERVER['REQUEST_METHOD']);

        return;
    }

    $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
});

it('returns no attributes when no route is set', function () {
    $provider = new PhpRouteAttributesProvider();

    expect($provider->toArray())->toBe([]);
    expect($provider->route())->toBeNull();
    expect($provider->entryPointHandlerIdentifier())->toBeNull();
});

it('exposes route and method', function () {
    $provider = new PhpRouteAttributesProvider('/users/{id}', 'GET');

    expect($provider->toArray())->toBe(['http.route' => '/users/{id}']);
    expect($provider->route())->toBe('/users/{id}');
    expect($provider->method())->toBe('GET');
});

it('builds a method+route entry-point identifier', function () {
    $provider = new PhpRouteAttributesProvider('/users/{id}', 'POST');

    expect($provider->entryPointHandlerType())->toBe('php_request');
    expect($provider->entryPointHandlerIdentifier())->toBe('POST /users/{id}');
});

it('initializes the method from $_SERVER when none is provided', function () {
    $_SERVER['REQUEST_METHOD'] = 'patch';

    $provider = new PhpRouteAttributesProvider('/users');

    expect($provider->method())->toBe('PATCH');
    expect($provider->entryPointHandlerIdentifier())->toBe('PATCH /users');
});

it('falls back to GET when no method is provided and $_SERVER has none', function () {
    unset($_SERVER['REQUEST_METHOD']);

    $provider = new PhpRouteAttributesProvider('/users');

    expect($provider->method())->toBe('GET');
    expect($provider->entryPointHandlerIdentifier())->toBe('GET /users');
});

it('accepts a manually provided handler name', function () {
    $provider = new PhpRouteAttributesProvider('/users', 'GET', 'App\\Http\\Controllers\\UsersController@index');

    expect($provider->entryPointHandlerName())->toBe('App\\Http\\Controllers\\UsersController@index');
});

it('returns null for handler name when none is provided', function () {
    $provider = new PhpRouteAttributesProvider('/users', 'GET');

    expect($provider->entryPointHandlerName())->toBeNull();
});
