<?php

use Spatie\FlareClient\Context\RequestContextProvider;
use Spatie\FlareClient\Tests\Concerns\MatchesCodeSnippetSnapshots;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

uses(MatchesCodeSnippetSnapshots::class);

it('can return the request context as an array', function () {
    $get = ['get-key-1' => 'get-value-1'];

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

    $context = new RequestContextProvider($request);

    $contextArray = $context->toArray();

    $this->assertMatchesCodeSnippetSnapshot($contextArray);
});

it('can retrieve the body contents of a json request', function () {
    $content = '{"key": "value"}';

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
    ];

    $request = new Request(server: $server, content: $content);

    $context = new RequestContextProvider($request);

    expect($context->toArray()['request_data']['body'])->toBe(['key' => 'value']);
});

it('will not crash when a json body is invalid', function () {
    $content = 'SOME INVALID JSON';

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
    ];

    $request = new Request(server: $server, content: $content);

    $context = new RequestContextProvider($request);

    expect($context->toArray()['request_data']['body'])->toBe([]);
});

it('can retrieve the body contents of a POST request', function () {
    $post = ['key' => 'value'];

    $server['REQUEST_METHOD'] = 'POST';

    $request = new Request(request: $post, server: $server);

    $context = new RequestContextProvider($request);

    expect($context->toArray()['request_data']['body'])->toBe(['key' => 'value']);
});

it('can retrieve the body contents of a GET request', function () {
    $query = ['key' => 'value'];

    $server['REQUEST_METHOD'] = 'GET';

    $request = new Request(query: $query, server: $server);

    $context = new RequestContextProvider($request);

    expect($context->toArray()['request_data']['body'])->toBe(['key' => 'value']);
});
