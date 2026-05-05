<?php

use Spatie\FlareClient\Truncation\TrimStackFrameArgumentsStrategy;

it('nulls out arguments on every stack frame', function () {
    $payload = [
        'stacktrace' => [
            ['file' => '/a.php', 'arguments' => ['arg1', 'arg2']],
            ['file' => '/b.php', 'arguments' => ['huge' => str_repeat('x', 1000)]],
            ['file' => '/c.php', 'arguments' => null],
        ],
    ];

    $trimmed = (new TrimStackFrameArgumentsStrategy())->execute($payload);

    expect($trimmed['stacktrace'][0]['arguments'])->toBeNull();
    expect($trimmed['stacktrace'][1]['arguments'])->toBeNull();
    expect($trimmed['stacktrace'][2]['arguments'])->toBeNull();
});

it('preserves all other frame fields', function () {
    $payload = [
        'stacktrace' => [
            ['file' => '/a.php', 'lineNumber' => 10, 'method' => 'doStuff', 'arguments' => ['x']],
        ],
    ];

    $trimmed = (new TrimStackFrameArgumentsStrategy())->execute($payload);

    expect($trimmed['stacktrace'][0])->toMatchArray([
        'file' => '/a.php',
        'lineNumber' => 10,
        'method' => 'doStuff',
        'arguments' => null,
    ]);
});
