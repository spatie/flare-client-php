<?php

use Spatie\FlareClient\FlareMiddleware\AddRequestInformation;
use Spatie\FlareClient\Support\Redactor;

beforeEach(function () {
    $this->originalEnv = $_ENV;
    $this->originalServer = $_SERVER;
});

afterEach(function () {
    $_ENV = $this->originalEnv;
    $_SERVER = $this->originalServer;
});

it('does nothing when running in console', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';

    $middleware = new AddRequestInformation(new Redactor());

    $flare = setupFlare();
    $report = $flare->createReport(new Exception('boom'));

    $countBefore = count($report->attributes);

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)->toHaveCount($countBefore);
});

it('adds Symfony request attributes when not running in console', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_ENV['FLARE_FAKE_WEB_REQUEST'] = 'true';
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/users/42';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

    $middleware = new AddRequestInformation(new Redactor());

    $flare = setupFlare();
    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)
        ->toHaveKey('url.full')
        ->toHaveKey('client.address', '203.0.113.1');
});

it('honors the redactor when censoring the client IP', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_ENV['FLARE_FAKE_WEB_REQUEST'] = 'true';
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

    $middleware = new AddRequestInformation(new Redactor(censorClientIps: true));

    $flare = setupFlare();
    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)->not->toHaveKey('client.address');
});
