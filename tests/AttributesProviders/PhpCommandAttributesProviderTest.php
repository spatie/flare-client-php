<?php

use Spatie\FlareClient\AttributesProviders\PhpCommandAttributesProvider;

it('returns the explicitly provided arguments', function () {
    $arguments = [
        'argument 1',
        'argument 2',
        'argument 3',
    ];

    $context = new PhpCommandAttributesProvider('migrate', arguments: $arguments);

    expect($context->toArray())->toEqual([
        'process.command_args' => $arguments,
    ]);
});

it('falls back to $_SERVER[argv] when no arguments are passed', function () {
    $original = $_SERVER['argv'] ?? null;
    $_SERVER['argv'] = ['artisan', 'migrate', '--force'];

    try {
        $context = new PhpCommandAttributesProvider('migrate');

        expect($context->toArray())->toEqual([
            'process.command_args' => ['artisan', 'migrate', '--force'],
        ]);
    } finally {
        if ($original === null) {
            unset($_SERVER['argv']);
        } else {
            $_SERVER['argv'] = $original;
        }
    }
});

it('exposes the command and command class', function () {
    $context = new PhpCommandAttributesProvider('migrate', 'App\\Commands\\Migrate');

    expect($context->command())->toBe('migrate');
    expect($context->commandClass())->toBe('App\\Commands\\Migrate');
});
