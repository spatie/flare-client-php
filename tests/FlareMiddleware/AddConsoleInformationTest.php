<?php

use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation;

beforeEach(function () {
    $this->originalEnv = $_ENV;
    $this->originalServer = $_SERVER;
});

afterEach(function () {
    $_ENV = $this->originalEnv;
    $_SERVER = $this->originalServer;
});

it('does nothing when not running in console', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_ENV['FLARE_FAKE_WEB_REQUEST'] = 'true';

    $middleware = new AddConsoleInformation();

    $flare = setupFlare();

    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)->not->toHaveKey('process.command_args');
});

it('adds the process command args from $_SERVER when running in console', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
    $_SERVER['argv'] = ['artisan', 'app:sync', '--force'];

    $middleware = new AddConsoleInformation();

    $flare = setupFlare();

    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)
        ->toHaveKey('process.command_args', ['artisan', 'app:sync', '--force']);
});

it('falls back to an empty array when argv is missing', function () {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'true';
    unset($_SERVER['argv']);

    $middleware = new AddConsoleInformation();

    $flare = setupFlare();

    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)->toHaveKey('process.command_args', []);
});
