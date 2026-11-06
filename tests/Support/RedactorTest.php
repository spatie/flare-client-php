<?php

use Spatie\FlareClient\Support\Redactor;

it('leaves data untouched when no censoring is configured', function () {
    $redactor = new Redactor();

    $headers = ['Authorization' => 'Bearer token', 'X-Trace-Id' => 'abc'];

    expect($redactor->censorHeaders($headers))->toBe($headers);
    expect($redactor->censorBody(['password' => 'secret']))->toBe(['password' => 'secret']);
});

it('censors headers regardless of input casing or separators', function () {
    $redactor = new Redactor(censorHeaders: ['Authorization', 'X-API-Key']);

    $censored = $redactor->censorHeaders([
        'AUTHORIZATION' => 'Bearer token',
        'authorization' => 'Bearer token',
        'X_API_KEY' => 'secret',
        'x-api-key' => 'secret',
        'X-Public' => 'visible',
    ]);

    expect($censored['AUTHORIZATION'])->toBe('<CENSORED:string>');
    expect($censored['authorization'])->toBe('<CENSORED:string>');
    expect($censored['X_API_KEY'])->toBe('<CENSORED:string>');
    expect($censored['x-api-key'])->toBe('<CENSORED:string>');
    expect($censored['X-Public'])->toBe('visible');
});

it('censors body fields without dot notation at any depth', function () {
    $redactor = new Redactor(censorBodyFields: ['password']);

    $censored = $redactor->censorBody([
        'password' => 'secret',
        'user' => [
            'name' => 'ruben',
            'password' => 'secret',
            'devices' => [
                ['name' => 'phone', 'password' => 'secret'],
            ],
        ],
    ]);

    expect($censored)->toBe([
        'password' => '<CENSORED:string>',
        'user' => [
            'name' => 'ruben',
            'password' => '<CENSORED:string>',
            'devices' => [
                ['name' => 'phone', 'password' => '<CENSORED:string>'],
            ],
        ],
    ]);
});

it('only censors body fields with dot notation at that exact path', function () {
    $redactor = new Redactor(censorBodyFields: ['user.password', 'user.tokens.api']);

    $censored = $redactor->censorBody([
        'password' => 'visible',
        'user' => [
            'password' => 'secret',
            'tokens' => ['api' => 'secret', 'public' => 'visible'],
        ],
        'admin' => ['user' => ['password' => 'visible']],
    ]);

    expect($censored)->toBe([
        'password' => 'visible',
        'user' => [
            'password' => '<CENSORED:string>',
            'tokens' => ['api' => '<CENSORED:string>', 'public' => 'visible'],
        ],
        'admin' => ['user' => ['password' => 'visible']],
    ]);
});

it('supports wildcards in body field paths', function () {
    $redactor = new Redactor(censorBodyFields: ['users.*.email']);

    $censored = $redactor->censorBody([
        'users' => [
            ['email' => 'a@example.com', 'name' => 'a'],
            ['email' => 'b@example.com', 'name' => 'b'],
        ],
    ]);

    expect($censored['users'][0]['email'])->toBe('<CENSORED:string>');
    expect($censored['users'][1]['email'])->toBe('<CENSORED:string>');
    expect($censored['users'][0]['name'])->toBe('a');
});

it('supports prefix wildcards in body field paths', function () {
    $redactor = new Redactor(censorBodyFields: ['pass*', 'user.api_*']);

    $censored = $redactor->censorBody([
        'password' => 'secret',
        'passphrase' => 'secret',
        'user' => ['api_token' => 'secret', 'name' => 'ruben'],
    ]);

    expect($censored)->toBe([
        'password' => '<CENSORED:string>',
        'passphrase' => '<CENSORED:string>',
        'user' => ['api_token' => '<CENSORED:string>', 'name' => 'ruben'],
    ]);
});

it('matches body field names case-insensitively', function () {
    $redactor = new Redactor(censorBodyFields: ['API_KEY']);

    $censored = $redactor->censorBody([
        'api_key' => 'secret',
        'API_KEY' => 'secret',
    ]);

    expect($censored['api_key'])->toBe('<CENSORED:string>');
    expect($censored['API_KEY'])->toBe('<CENSORED:string>');
});

it('censors every top-level field with a bare wildcard', function () {
    $redactor = new Redactor(censorBodyFields: ['*']);

    $censored = $redactor->censorBody([
        'password' => 'secret',
        'user' => ['name' => 'ruben'],
    ]);

    expect($censored)->toBe([
        'password' => '<CENSORED:string>',
        'user' => '<CENSORED:array>',
    ]);
});

it('censors cookie values by name inside the cookie headers', function () {
    $redactor = new Redactor(censorCookieNames: ['laravel_session', 'remember_*']);

    $censored = $redactor->censorHeaders([
        'Cookie' => 'laravel_session=secret; remember_web_abc=secret; theme=dark',
        'Set-Cookie' => 'XSRF-TOKEN=visible; path=/; samesite=lax, laravel_session=secret; expires=Thu, 06 Nov 2026 12:00:00 GMT; path=/; httponly',
        'X-Other' => 'laravel_session=visible',
    ]);

    expect($censored['Cookie'])->toBe('laravel_session=<CENSORED:string>; remember_web_abc=<CENSORED:string>; theme=dark');
    expect($censored['Set-Cookie'])->toBe('XSRF-TOKEN=visible; path=/; samesite=lax, laravel_session=<CENSORED:string>; expires=Thu, 06 Nov 2026 12:00:00 GMT; path=/; httponly');
    expect($censored['X-Other'])->toBe('laravel_session=visible');
});

it('encodes the type in the censor placeholder', function () {
    $redactor = new Redactor(censorBodyFields: ['count']);

    $censored = $redactor->censorBody(['count' => 42]);

    expect($censored['count'])->toBe('<CENSORED:int>');
});


it('normalizes header and body field names', function () {
    $redactor = new Redactor();

    expect($redactor->normalizeHeaderName('X_AUTH_TOKEN'))->toBe('x-auth-token');
    expect($redactor->normalizeHeaderName('Set-Cookie'))->toBe('set-cookie');
    expect($redactor->normalizeBodyFieldName('API_KEY'))->toBe('api_key');
});
