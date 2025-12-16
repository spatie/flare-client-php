<?php

namespace Spatie\FlareClient\Tests\Resources;

use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Resources\Resource;

test('it always includes base attributes and custom attributes', function () {
    $resource = new Resource(
        serviceName: 'test-service',
        serviceVersion: '1.0.0',
        serviceStage: 'production',
        attributes: ['custom.key' => 'custom-value']
    );

    $exported = $resource->export(FlareEntityType::Errors);

    expect($exported)->toHaveKey('service.name', 'test-service');
    expect($exported)->toHaveKey('service.version', '1.0.0');
    expect($exported)->toHaveKey('service.stage', 'production');
    expect($exported)->toHaveKey('telemetry.sdk.language', 'php');
    expect($exported)->toHaveKey('telemetry.sdk.name');
    expect($exported)->toHaveKey('custom.key', 'custom-value');
});

test('it can include git data', function () {
    $gitProvider = new GitAttributesProvider(__DIR__.'/../');

    $resource = new Resource(
        serviceName: 'test-service'
    );

    $resource->git(
        attributesProvider: $gitProvider,
    );

    $exported = $resource->export(FlareEntityType::Errors);

    expect($exported)->toHaveKeys([
        'git.branch',
        'git.hash',
//        'git.message', CI environments may not have access to commit messages
        'git.remote',
    ]);
});

test('it can include operating system data', function () {
    $resource = new Resource(
        serviceName: 'test-service'
    );

    $resource->operatingSystem();

    $exported = $resource->export(FlareEntityType::Errors);

    expect($exported)->toHaveKeys([
        'os.type',
        'os.description',
        'os.name',
        'os.version',
    ]);
});

test('it can include process data', function () {
    $resource = new Resource(
        serviceName: 'test-service'
    );

    $resource->process();

    $exported = $resource->export(FlareEntityType::Errors);

    expect($exported)->toHaveKeys([
        'process.pid',
        'process.executable.path',
    ]);
});

test('it can include process runtime data', function () {
    $resource = new Resource(
        serviceName: 'test-service'
    );

    $resource->processRuntime();

    $exported = $resource->export(FlareEntityType::Errors);

    expect($exported)->toHaveKeys([
        'process.runtime.name',
        'process.runtime.version',
    ]);
});

test('it can include composer packages', function () {
    $resource = new Resource(
        serviceName: 'test-service'
    );

    $resource->composerPackages([FlareEntityType::Errors]);

    $exported = $resource->export(FlareEntityType::Errors);

    expect($exported)->toHaveKey('composer.packages');
});

test('it uses different includes based on the payload type', function () {
    $resource = new Resource(
        serviceName: 'test-service'
    );

    $resource->operatingSystem(
        [FlareEntityType::Errors],
    );

    $resource->processRuntime(
        [FlareEntityType::Traces],
    );

    $resource->process(
        [FlareEntityType::Logs],
    );

    $errorExport = $resource->export(FlareEntityType::Errors);

    expect($errorExport)->toHaveKeys([
        'os.type',
        'os.description',
        'os.name',
        'os.version',
    ]);

    expect($errorExport)->not()->toHaveKeys([
        'process.pid',
        'process.executable.path',
        'process.runtime.name',
        'process.runtime.version',
    ]);

    $tracesExport = $resource->export(FlareEntityType::Traces);

    expect($tracesExport)->toHaveKeys([
        'process.runtime.name',
        'process.runtime.version',
    ]);

    expect($tracesExport)->not()->toHaveKeys([
        'os.type',
        'os.description',
        'os.name',
        'os.version',
        'process.pid',
        'process.executable.path',
    ]);

    $logsExport = $resource->export(FlareEntityType::Logs);

    expect($logsExport)->toHaveKeys([
        'process.pid',
        'process.executable.path',
    ]);

    expect($logsExport)->not()->toHaveKeys([
        'os.type',
        'os.description',
        'os.name',
        'os.version',
        'process.runtime.name',
        'process.runtime.version',
    ]);
});
