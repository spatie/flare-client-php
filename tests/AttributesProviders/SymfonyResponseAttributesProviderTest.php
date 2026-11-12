<?php

use Spatie\FlareClient\AttributesProviders\SymfonyResponseAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Symfony\Component\HttpFoundation\Response;

it('exposes the status code, body size, and headers', function () {
    $response = new Response('Hello world', 201, [
        'Content-Type' => 'text/plain',
        'X-Trace-Id' => 'abc',
    ]);

    $provider = new SymfonyResponseAttributesProvider(new Redactor(), $response);

    $attributes = $provider->toArray();

    expect($provider->statusCode())->toBe(201);
    expect($attributes['http.response.status_code'])->toBe(201);
    expect($attributes['http.response.body.size'])->toBe(strlen('Hello world'));
    expect($attributes)
        ->toHaveKey('http.response.header.content-type', ['text/plain'])
        ->toHaveKey('http.response.header.x-trace-id', ['abc']);
});

it('censors response headers via the redactor', function () {
    $response = new Response('', 200, [
        'Set-Cookie' => 'session=abc',
        'X-Public' => 'visible',
    ]);

    $provider = new SymfonyResponseAttributesProvider(
        new Redactor(censorHeaders: ['Set-Cookie']),
        $response,
    );

    expect($provider->toArray())
        ->toHaveKey('http.response.header.set-cookie', ['<CENSORED:string>'])
        ->toHaveKey('http.response.header.x-public', ['visible']);
});

it('returns a zero body size for an empty response', function () {
    $response = new Response('', 204);

    $provider = new SymfonyResponseAttributesProvider(new Redactor(), $response);

    expect($provider->toArray()['http.response.body.size'])->toBe(0);
});
