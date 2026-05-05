<?php

use Spatie\FlareClient\AttributesProviders\PhpRequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;

it('returns minimal attributes from $_SERVER', function () {
    $provider = new PhpRequestAttributesProvider(
        new Redactor(),
        server: [
            'REQUEST_URI' => '/api/users?page=2',
            'REQUEST_METHOD' => 'POST',
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '443',
            'REMOTE_ADDR' => '1.2.3.4',
            'QUERY_STRING' => 'page=2',
        ],
        headers: [
            'User-Agent' => 'PestRunner/1.0',
            'Accept' => 'application/json',
        ],
    );

    $attributes = $provider->toArray();

    expect($attributes)
        ->toHaveKey('url.full', 'https://example.com/api/users?page=2')
        ->toHaveKey('url.scheme', 'https')
        ->toHaveKey('url.path', '/api/users')
        ->toHaveKey('url.query', 'page=2')
        ->toHaveKey('server.address', 'example.com')
        ->toHaveKey('server.port', '443')
        ->toHaveKey('user_agent.original', 'PestRunner/1.0')
        ->toHaveKey('http.request.method', 'POST')
        ->toHaveKey('client.address', '1.2.3.4')
        ->toHaveKey('http.request.headers');

    expect($attributes['http.request.headers'])
        ->toHaveKey('User-Agent', 'PestRunner/1.0')
        ->toHaveKey('Accept', 'application/json');
});

it('falls back to HTTP_* keys when getallheaders() output is missing', function () {
    $provider = new PhpRequestAttributesProvider(
        new Redactor(),
        server: [
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.test',
            'HTTP_X_CUSTOM' => 'value',
            'CONTENT_TYPE' => 'application/json',
        ],
        headers: null,
    );

    $headers = $provider->toArray()['http.request.headers'] ?? [];

    expect($headers)->toMatchArray([
        'host' => 'example.test',
        'x-custom' => 'value',
        'content-type' => 'application/json',
    ]);
});

it('censors the client IP and headers via the redactor', function () {
    $provider = new PhpRequestAttributesProvider(
        new Redactor(censorClientIps: true, censorHeaders: ['Authorization']),
        server: [
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '9.9.9.9',
        ],
        headers: ['Authorization' => 'Bearer token'],
    );

    $attributes = $provider->toArray();

    expect($attributes)->not->toHaveKey('client.address');
    expect($attributes['http.request.headers'])->toHaveKey('Authorization', '<CENSORED:string>');
});

it('reports method, path, and url through getters', function () {
    $provider = new PhpRequestAttributesProvider(
        new Redactor(),
        server: [
            'REQUEST_URI' => '/foo?x=1',
            'REQUEST_METHOD' => 'put',
            'HTTP_HOST' => 'example.com',
        ],
    );

    expect($provider->method())->toBe('PUT');
    expect($provider->path())->toBe('/foo');
    expect($provider->url())->toBe('http://example.com/foo?x=1');
});
