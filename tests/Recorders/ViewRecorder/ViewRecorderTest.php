<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;

it('records a view rendering span with name, file, and data attributes', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectViews(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->view()->recordRendering(
        viewName: 'users.show',
        data: ['user' => ['id' => 42]],
        file: '/resources/views/users/show.blade.php',
        attributes: ['custom.key' => 'value'],
    );

    expect($span)->not->toBeNull();
    expect($span->name)->toBe('View - users.show');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::View)
        ->toHaveKey('view.name', 'users.show')
        ->toHaveKey('view.file', '/resources/views/users/show.blade.php')
        ->toHaveKey('view.data', ['user' => ['id' => 42]])
        ->toHaveKey('custom.key', 'value');
});

it('closes the span on recordRendered', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectViews(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $flare->view()->recordRendering('users.index');
    $span = $flare->view()->recordRendered();

    expect($span)->not->toBeNull();
    expect($span->end)->not->toBeNull();
    expect($span->name)->toBe('View - users.index');
});
