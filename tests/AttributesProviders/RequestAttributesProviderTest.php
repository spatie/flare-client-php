<?php

use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tests\Concerns\MatchesCodeSnippetSnapshots;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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

it('can strip body fields using nested and wildcard support', function () {
    $post = [
        'admin' => [
            'email' => 'test@flareapp.io',
            'name' => 'test',
            'counts' => [
                'posts' => 10,
                'followers' => 1000,
            ],
        ],
        'users' => [
            [
                'email' => 'test-2@flareapp.io',
                'name' => 'test-2',
                'counts' => [
                    'posts' => 5,
                    'followers' => 500,
                ],
            ],
            [
                'email' => 'test-3@flareapp.io',
                'name' => 'test-3',
                'counts' => [
                    'posts' => 2,
                    'followers' => 200,
                ],
            ],
        ],
        'wildcard' => [
            'key1' => 'value1',
            'key2' => 'value2',
        ],
        'object' => new StdClass(),
        'nested_object' => (object) [
            'key' => 'value',
        ],
    ];

    $server = [
        'REQUEST_METHOD' => 'POST',
    ];


    $request = new Request(request: $post, server: $server);

    $provider = new RequestAttributesProvider(
        new Redactor(censorBodyFields: [
            'admin.name',
            'admin.counts.followers',
            'users.*.name',
            'users.*.counts.followers',
            'wildcard.*',
            'object',
            'nested_object.key',
        ]),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.body.contents', [
        'admin' => [
            'email' => 'test@flareapp.io',
            'name' => '<CENSORED:string>',
            'counts' => [
                'posts' => 10,
                'followers' => '<CENSORED:int>',
            ],
        ],
        'users' => [
            [
                'email' => 'test-2@flareapp.io',
                'name' => '<CENSORED:string>',
                'counts' => [
                    'posts' => 5,
                    'followers' => '<CENSORED:int>',
                ],
            ],
            [
                'email' => 'test-3@flareapp.io',
                'name' => '<CENSORED:string>',
                'counts' => [
                    'posts' => 2,
                    'followers' => '<CENSORED:int>',
                ],
            ],
        ],
        'wildcard' => [
            'key1' => '<CENSORED:string>',
            'key2' => '<CENSORED:string>',
        ],
        'object' => '<CENSORED:stdClass>',
        'nested_object' => (object) [
            'key' => 'value',
        ],
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

it('can censor cookies', function () {
    $cookies = [
        'session_id' => 'abc123',
        'auth_token' => 'secret',
    ];

    $request = new Request(cookies: $cookies);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.cookies', [
        'session_id' => 'abc123',
        'auth_token' => 'secret',
    ]);

    $provider = new RequestAttributesProvider(
        new Redactor(censorCookies: true),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->not->toHaveKey('http.request.cookies');
});

it('can censor session', function () {
    $request = new Request();

    $session = new Session(new MockArraySessionStorage());
    $session->set('user_id', 123);
    $session->set('cart', ['item1', 'item2']);

    $request->setSession($session);

    $provider = new RequestAttributesProvider(
        new Redactor(),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->toHaveKey('http.request.session', [
        'user_id' => 123,
        'cart' => ['item1', 'item2'],
    ]);

    $provider = new RequestAttributesProvider(
        new Redactor(censorSession: true),
        new EmptyUserAttributesProvider()
    );

    $attributes = $provider->toArray($request);

    expect($attributes)->not->toHaveKey('http.request.session');
});
