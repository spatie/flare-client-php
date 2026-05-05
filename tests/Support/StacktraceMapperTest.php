<?php

use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Support\StacktraceMapper;

it('maps frames into the OTEL stacktrace shape', function () {
    $mapped = (new StacktraceMapper())->map([
        new Frame('/app/src/Foo.php', 12, null, 'doStuff', 'App\\Foo', null, true),
        new Frame('/vendor/pkg/Bar.php', 99, null, 'run', 'Pkg\\Bar', null, false),
    ], throwable: null);

    expect($mapped)->toHaveCount(2);
    expect($mapped[0])->toMatchArray([
        'file' => '/app/src/Foo.php',
        'lineNumber' => 12,
        'method' => 'doStuff',
        'class' => 'App\\Foo',
        'isApplicationFrame' => true,
    ]);
    expect($mapped[0])->toHaveKey('codeSnippet');
    expect($mapped[1])->toMatchArray([
        'file' => '/vendor/pkg/Bar.php',
        'lineNumber' => 99,
        'method' => 'run',
        'class' => 'Pkg\\Bar',
        'isApplicationFrame' => false,
    ]);
});

it('strips wrapper frames when mapping an ErrorException', function () {
    $error = new ErrorException('boom', 0, E_WARNING, '/app/src/Trigger.php', 50);

    $frames = [
        new Frame('/vendor/error-handler/Handler.php', 1, null, 'handle', 'Handler', null, false),
        new Frame('/vendor/error-handler/Wrap.php', 2, null, 'wrap', 'Wrap', null, false),
        new Frame('/app/src/Trigger.php', 50, ['arg' => 'value'], 'trigger', 'App\\Trigger', null, true),
        new Frame('/app/src/Caller.php', 7, null, 'call', 'App\\Caller', null, true),
    ];

    $mapped = (new StacktraceMapper())->map($frames, $error);

    expect($mapped)->toHaveCount(2);
    expect($mapped[0]['file'])->toBe('/app/src/Trigger.php');
    expect($mapped[0]['arguments'])->toBeNull();
    expect($mapped[1]['file'])->toBe('/app/src/Caller.php');
});

it('returns the original frames when no error file matches', function () {
    $error = new ErrorException('boom', 0, E_WARNING, '/app/src/Missing.php', 1);

    $frames = [
        new Frame('/vendor/handler.php', 10, null, 'handle', 'Handler', null, false),
        new Frame('/app/src/Other.php', 20, null, 'other', 'App\\Other', null, true),
    ];

    $mapped = (new StacktraceMapper())->map($frames, $error);

    expect($mapped)->toHaveCount(2);
    expect($mapped[0]['file'])->toBe('/vendor/handler.php');
});

it('does not strip frames for non-ErrorException throwables', function () {
    $error = new RuntimeException('boom');

    $frames = [
        new Frame('/app/src/A.php', 10, null, 'a', 'A', null, true),
        new Frame('/app/src/B.php', 20, null, 'b', 'B', null, true),
    ];

    $mapped = (new StacktraceMapper())->map($frames, $error);

    expect($mapped)->toHaveCount(2);
});
