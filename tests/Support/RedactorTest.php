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

it('censors top-level body fields', function () {
    $redactor = new Redactor(censorBodyFields: ['password', 'token']);

    $censored = $redactor->censorBody([
        'password' => 'secret',
        'token' => 'abc',
        'username' => 'ruben',
    ]);

    expect($censored)->toBe([
        'password' => '<CENSORED:string>',
        'token' => '<CENSORED:string>',
        'username' => 'ruben',
    ]);
});

it('censors nested body fields using dot notation', function () {
    $redactor = new Redactor(censorBodyFields: ['user.password', 'user.tokens.api']);

    $censored = $redactor->censorBody([
        'user' => [
            'name' => 'ruben',
            'password' => 'secret',
            'tokens' => [
                'api' => 'sensitive',
                'public' => 'fine',
            ],
        ],
    ]);

    expect($censored)->toBe([
        'user' => [
            'name' => 'ruben',
            'password' => '<CENSORED:string>',
            'tokens' => [
                'api' => '<CENSORED:string>',
                'public' => 'fine',
            ],
        ],
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

it('matches body field names case-insensitively', function () {
    $redactor = new Redactor(censorBodyFields: ['API_KEY']);

    $censored = $redactor->censorBody([
        'api_key' => 'secret',
        'API_KEY' => 'secret',
    ]);

    expect($censored['api_key'])->toBe('<CENSORED:string>');
    expect($censored['API_KEY'])->toBe('<CENSORED:string>');
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
