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
