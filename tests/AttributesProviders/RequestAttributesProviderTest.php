<?php

use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tests\Concerns\MatchesCodeSnippetSnapshots;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

uses(MatchesCodeSnippetSnapshots::class);

it('can return the request context as an array', function () {
    $get = [
        'get-key-1' => 'get-value-1',
        'get-key-2' => 'get-value-2',
    ];

    $post = ['post-key-1' => 'post-value-1'];

    $request = [];

    $cookies = ['cookie-key-1' => 'cookie-value-1'];

    $files = [
        'file-one' => new UploadedFile(
            getStubPath('file.txt'),
            'file-name.txt',
            'text/plain',
            UPLOAD_ERR_OK
        ),
        'file-two' => new UploadedFile(
            getStubPath('file.txt'),
            'file-name.txt',
            'text/plain',
            UPLOAD_ERR_OK
        ),
    ];

    $server = [
        'HTTP_HOST' => 'example.com',
        'REMOTE_ADDR' => '1.2.3.4',
        'SERVER_PORT' => '80',
        'REQUEST_URI' => '/test',
    ];

    $content = 'my content';

    $request = new Request($get, $post, $request, $cookies, $files, $server, $content);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    $this->assertMatchesCodeSnippetSnapshot($attributes);
});

it('can hide the IP', function () {
    $server = [
        'REMOTE_ADDR' => '1.2.3.4',
    ];

    $request = new Request(server: $server);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('client.address', '1.2.3.4');

    $provider = new RequestAttributesProvider(
        new Redactor(censorClientIps: true),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->not->toHaveKey('client.address');
});

it('can strip headers', function () {
    $server = [
        'HTTP_AUTHORIZATION' => 'Bearer token',
        'HTTP_OTHER_HEADER' => 'other',
        'HTTP_lower_case_header' => 'lower',
        'HTTP_KEEP' => 'keep',
    ];

    $request = new Request(server: $server);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.headers', [
        'authorization' => 'Bearer token',
        'other-header' => 'other',
        'lower-case-header' => 'lower',
        'keep' => 'keep',
    ]);

    $provider = new RequestAttributesProvider(
        new Redactor(
            censorHeaders: ['AUTHORIZATION', 'other-header', 'lower_case_header'],
        ),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.headers', [
        'authorization' => '<CENSORED:string>',
        'other-header' => '<CENSORED:string>',
        'lower-case-header' => '<CENSORED:string>',
        'keep' => 'keep',
    ]);
});

it('can strip body fields', function () {
    $post = [
        'password' => 'secret',
        'API_KEY' => 'secret',
        'token' => 'secret',
        'keep' => 'keep',
    ];

    $server = [
        'REQUEST_METHOD' => 'POST',
    ];

    $request = new Request(request: $post, server: $server);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.body.contents', [
        'password' => 'secret',
        'API_KEY' => 'secret',
        'token' => 'secret',
        'keep' => 'keep',
    ]);

    $provider = new RequestAttributesProvider(
        new Redactor(censorBodyFields: ['password', 'api_key', 'token']),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.body.contents', [
        'password' => '<CENSORED:string>',
        'API_KEY' => '<CENSORED:string>',
        'token' => '<CENSORED:string>',
        'keep' => 'keep',
    ]);
});

it('can retrieve the body contents of a json request', function () {
    $content = '{"key": "value"}';

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
    ];

    $request = new Request(server: $server, content: $content);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    expect($provider->toArray($request)['http.request.body.contents'])->toBe(['key' => 'value']);
});

it('will not crash when a json body is invalid', function () {
    $content = 'SOME INVALID JSON';

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
    ];

    $request = new Request(server: $server, content: $content);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );
    expect($provider->toArray($request))->not()->toHaveKey('http.request.body.contents');
});

it('can retrieve the body contents of a POST request', function () {
    $post = ['key' => 'value'];

    $server['REQUEST_METHOD'] = 'POST';

    $request = new Request(request: $post, server: $server);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );
    expect($provider->toArray($request)['http.request.body.contents'])->toBe(['key' => 'value']);
});

it('can retrieve the body contents of a GET request', function () {
    $query = ['key' => 'value'];

    $server['REQUEST_METHOD'] = 'GET';

    $request = new Request(query: $query, server: $server);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    expect($provider->toArray($request)['http.request.body.contents'])->toBe(['key' => 'value']);
});
