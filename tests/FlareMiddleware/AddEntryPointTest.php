<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\FlareMiddleware\AddEntryPoint;

it('adds the resolved entry point attributes onto the report', function () {
    $resolver = new EntryPointResolver();
    $resolver->set(new EntryPoint(EntryPointType::Web, 'https://example.com/users'));

    $middleware = new AddEntryPoint($resolver);

    $flare = setupFlare();

    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)
        ->toHaveKey('flare.entry_point.type', 'web')
        ->toHaveKey('flare.entry_point.value', 'https://example.com/users');
});

it('includes handler attributes when the entry point has a resolved handler', function () {
    $entryPoint = new EntryPoint(EntryPointType::Cli, 'artisan app:sync');
    $entryPoint->setHandler('app:sync', 'SyncCommand', 'php_command');

    $resolver = new EntryPointResolver();
    $resolver->set($entryPoint);

    $middleware = new AddEntryPoint($resolver);

    $flare = setupFlare();

    $report = $flare->createReport(new Exception('boom'));

    $middleware->handle($report, fn ($report) => $report);

    expect($report->attributes)
        ->toHaveKey('flare.entry_point.handler.identifier', 'app:sync')
        ->toHaveKey('flare.entry_point.handler.name', 'SyncCommand')
        ->toHaveKey('flare.entry_point.handler.type', 'php_command');
});
