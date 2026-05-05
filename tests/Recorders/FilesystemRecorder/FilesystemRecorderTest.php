<?php

use Spatie\FlareClient\Enums\FilesystemOperation;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;

it('records a filesystem operation span via the raw start/end methods', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectFilesystemOperations(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->filesystem()->recordOperationStart(
        operation: FilesystemOperation::Get,
        description: 'reading config',
        attributes: ['filesystem.path' => '/etc/app.conf'],
    );

    expect($span)->not->toBeNull();
    expect($span->name)->toBe('reading config');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Filesystem)
        ->toHaveKey('filesystem.operation', FilesystemOperation::Get)
        ->toHaveKey('filesystem.path', '/etc/app.conf');

    $end = $flare->filesystem()->recordOperationEnd(['filesystem.bytes' => 200]);

    expect($end->end)->not->toBeNull();
    expect($end->attributes)->toHaveKey('filesystem.bytes', 200);
});

it('records distinct attribute shapes per filesystem operation', function (string $method, array $args, FilesystemOperation $expectedOperation, array $expectedAttributes) {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectFilesystemOperations(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->filesystem()->{$method}(...$args);

    expect($span)->not->toBeNull();
    expect($span->name)->toBe("Filesystem - {$expectedOperation->value}");
    expect($span->attributes)->toHaveKey('filesystem.operation', $expectedOperation);

    foreach ($expectedAttributes as $key => $value) {
        expect($span->attributes)->toHaveKey($key, $value);
    }
})->with([
    'recordGet' => [
        'recordGet',
        ['/storage/file.txt'],
        FilesystemOperation::Get,
        ['filesystem.path' => '/storage/file.txt'],
    ],
    'recordPut' => [
        'recordPut',
        ['/storage/out.txt', 'hello'],
        FilesystemOperation::Put,
        ['filesystem.path' => '/storage/out.txt', 'filesystem.contents.size' => '5 B'],
    ],
    'recordDelete' => [
        'recordDelete',
        [['/a.txt', '/b.txt']],
        FilesystemOperation::Delete,
        ['filesystem.paths' => '/a.txt, /b.txt'],
    ],
    'recordCopy' => [
        'recordCopy',
        ['/from.txt', '/to.txt'],
        FilesystemOperation::Copy,
        ['filesystem.from_path' => '/from.txt', 'filesystem.to_path' => '/to.txt'],
    ],
    'recordMakeDirectory' => [
        'recordMakeDirectory',
        ['/storage/cache'],
        FilesystemOperation::MakeDirectory,
        ['filesystem.path' => '/storage/cache'],
    ],
    'recordUrl' => [
        'recordUrl',
        ['/storage/file.txt'],
        FilesystemOperation::Url,
        ['filesystem.path' => '/storage/file.txt'],
    ],
]);
