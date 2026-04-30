<?php

use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeMemory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

it('records a command span with the expected attributes', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectCommands(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('migrate', ['--force'], 'App\\Commands\\Migrate');

    expect($span)->not()->toBeNull();
    expect($span->name)->toBe('Command - migrate');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Command)
        ->toHaveKey('process.command', 'migrate')
        ->toHaveKey('process.command_args', ['--force'])
        ->toHaveKey('flare.entry_point.handler.identifier', 'migrate')
        ->toHaveKey('flare.entry_point.handler.name', 'App\\Commands\\Migrate')
        ->toHaveKey('flare.entry_point.handler.type', 'php_command');
});

it('merges additional attributes into the started span', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectCommands(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments(
        'migrate',
        [],
        'App\\Commands\\Migrate',
        attributes: ['custom.key' => 'custom-value'],
    );

    expect($span->attributes)->toHaveKey('custom.key', 'custom-value');
});

it('records the exit code and peak memory usage on recordEnd', function () {
    FakeMemory::setup()->nextMemoryUsage(7 * 1024 * 1024);

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectCommands(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $flare->command()->recordStartFromArguments('migrate', []);
    $span = $flare->command()->recordEnd(exitCode: 2, attributes: ['custom.key' => 'value']);

    expect($span)->not()->toBeNull();
    expect($span->end)->not()->toBeNull();
    expect($span->attributes)
        ->toHaveKey('process.exit_code', 2)
        ->toHaveKey('flare.peak_memory_usage', 7 * 1024 * 1024)
        ->toHaveKey('custom.key', 'value');
});

it('records a span from a Symfony InputInterface', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectCommands(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $input = new ArrayInput([
        'command' => 'migrate',
        'name' => 'users',
        '--force' => true,
        '--connection' => 'pgsql',
        '--tags' => ['one', 'two'],
    ]);

    $input->bind(new InputDefinition([
        new InputArgument('command'),
        new InputArgument('name'),
        new InputOption('force', null, InputOption::VALUE_NONE),
        new InputOption('pretend', null, InputOption::VALUE_NONE),
        new InputOption('connection', null, InputOption::VALUE_REQUIRED),
        new InputOption('tags', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        new InputOption('empty', null, InputOption::VALUE_OPTIONAL),
    ]));

    $span = $flare->command()->recordStartFromSymfonyInput(
        'migrate users --force --connection=pgsql --tags=one,two',
        $input,
    );

    expect($span->attributes['process.command_args'])->toBe([
        'migrate',
        'users',
        '--force',
        '--connection=pgsql',
        '--tags=one,two',
    ]);
});

it('sets the entry point handler when no handler has been resolved yet', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(),
        alwaysSampleTraces: true,
        entryPoint: new EntryPoint(EntryPointType::Cli, 'artisan migrate'),
    );

    $flare->tracer->startTrace();

    $flare->command()->recordStartFromArguments('migrate', [], 'App\\Commands\\Migrate');

    $entryPoint = Container::instance()->get(EntryPointResolver::class)->get();

    expect($entryPoint->handlerResolved)->toBeTrue();
    expect($entryPoint->handlerIdentifier)->toBe('migrate');
    expect($entryPoint->handlerName)->toBe('App\\Commands\\Migrate');
    expect($entryPoint->handlerType)->toBe('php_command');
});

it('does not override the entry point handler when one is already resolved', function () {
    $entryPoint = new EntryPoint(EntryPointType::Cli, 'artisan outer');

    $entryPoint->setHandler('outer', 'App\\Commands\\Outer', 'php_command');

    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(),
        alwaysSampleTraces: true,
        entryPoint: $entryPoint,
    );

    $flare->tracer->startTrace();

    $flare->command()->recordStartFromArguments('inner', [], 'App\\Commands\\Inner');

    expect($entryPoint->handlerIdentifier)->toBe('outer');
    expect($entryPoint->handlerName)->toBe('App\\Commands\\Outer');
});

it('ignores commands by name from config', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredCommands: ['schedule:run']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('schedule:run', []);

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('ignores commands by name using a wildcard pattern', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredCommands: ['make:*']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('make:migration', []);

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('does not ignore commands when wildcard pattern does not match', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredCommands: ['make:*']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('migrate', []);

    expect($span)->not()->toBeNull();
    expect($flare->tracer->isSampling())->toBeTrue();
});

it('ignores command classes using a wildcard pattern', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredClasses: ['App\\Commands\\Internal\\*']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('internal', [], 'App\\Commands\\Internal\\Cleanup');

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('ignores commands by class from ignored_classes config', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredClasses: ['App\\Commands\\Internal']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('internal', [], 'App\\Commands\\Internal');

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('unsamples a root ignored command', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredCommands: ['ignored:cmd']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->command()->recordStartFromArguments('ignored:cmd', []);

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->isSamplingPaused())->toBeFalse();
});

it('pauses sampling for a nested ignored command and resumes after that command ends', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectCommands(ignoredCommands: ['ignored:cmd']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $outerSpan = $flare->command()->recordStartFromArguments('outer', []);

    expect($outerSpan)->not()->toBeNull();
    expect($flare->tracer->isSampling())->toBeTrue();

    $innerSpan = $flare->command()->recordStartFromArguments('ignored:cmd', []);

    expect($innerSpan)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->isSamplingPaused())->toBeTrue();

    $flare->command()->recordEnd();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->isSamplingPaused())->toBeFalse();

    $flare->command()->recordEnd();

    expect($outerSpan->end)->not()->toBeNull();

    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectName('Command - outer')
        ->expectType(SpanType::Command)
        ->expectMissingParentId()
        ->expectEnded();
});
