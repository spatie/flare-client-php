<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\EntryPointType;

beforeEach(function () {
    $this->originalServer = $_SERVER;
    $this->originalEnv = $_ENV;
});

afterEach(function () {
    $_SERVER = $this->originalServer;
    $_ENV = $this->originalEnv;
});

it('lazily resolves a CLI entry point from $_SERVER argv when running in console', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
    $_SERVER['argv'] = ['artisan', 'app:sync', '--force'];

    $resolver = new EntryPointResolver();

    $entryPoint = $resolver->get();

    expect($entryPoint->type)->toBe(EntryPointType::Cli);
    expect($entryPoint->value)->toBe('artisan app:sync --force');
});

it('falls back to an empty CLI value when argv is missing', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
    unset($_SERVER['argv']);

    $resolver = new EntryPointResolver();

    expect($resolver->get()->value)->toBe('');
});

it('resolves a Web entry point from $_SERVER URL parts when not in console', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/users/42';
    unset($_SERVER['HTTPS']);

    $resolver = new EntryPointResolver();

    $entryPoint = $resolver->get();

    expect($entryPoint->type)->toBe(EntryPointType::Web);
    expect($entryPoint->value)->toBe('http://example.com/users/42');
});

it('uses https scheme when HTTPS server var is on', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/secure';

    $resolver = new EntryPointResolver();

    expect($resolver->get()->value)->toBe('https://example.com/secure');
});

it('falls back to SERVER_NAME and localhost when HTTP_HOST is missing', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    unset($_SERVER['HTTP_HOST']);
    $_SERVER['SERVER_NAME'] = 'fallback.test';
    $_SERVER['REQUEST_URI'] = '/';

    $resolver = new EntryPointResolver();

    expect($resolver->get()->value)->toBe('http://fallback.test/');

    unset($_SERVER['SERVER_NAME']);

    $resolver = new EntryPointResolver();

    expect($resolver->get()->value)->toBe('http://localhost/');
});

it('caches the resolved entry point across calls', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
    $_SERVER['argv'] = ['artisan', 'first'];

    $resolver = new EntryPointResolver();

    $first = $resolver->get();

    $_SERVER['argv'] = ['artisan', 'changed'];

    expect($resolver->get())->toBe($first);
});

it('forces re-resolution after clear()', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
    $_SERVER['argv'] = ['artisan', 'first'];

    $resolver = new EntryPointResolver();

    $first = $resolver->get();

    $_SERVER['argv'] = ['artisan', 'second'];

    $resolver->clear();

    $second = $resolver->get();

    expect($second)->not->toBe($first);
    expect($second->value)->toBe('artisan second');
});

it('respects FLARE_FAKE_WEB_REQUEST to force a Web entry point', function () {
    unset($_ENV['APP_RUNNING_IN_CONSOLE']);
    $_ENV['FLARE_FAKE_WEB_REQUEST'] = 'true';
    $_SERVER['HTTP_HOST'] = 'fake-web.test';
    $_SERVER['REQUEST_URI'] = '/forced';

    $resolver = new EntryPointResolver();

    $entryPoint = $resolver->get();

    expect($entryPoint->type)->toBe(EntryPointType::Web);
    expect($entryPoint->value)->toBe('http://fake-web.test/forced');
});
