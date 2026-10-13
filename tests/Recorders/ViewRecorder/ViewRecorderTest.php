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

it('caps recorded view spans for error reports according to custom max_items_with_errors', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectViews(maxItemsWithErrors: 2),
    );

    $flare->view()->recordRendering('view.one');
    $flare->view()->recordRendering('view.two');
    $flare->view()->recordRendering('view.three');

    expect($flare->view()->getSpans())->toHaveCount(2);
    expect($flare->view()->getSpans()[0]->name)->toBe('View - view.two');
    expect($flare->view()->getSpans()[1]->name)->toBe('View - view.three');
});

it('limits view entries to 100 by default when configured via useDefaults', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->useDefaults(),
    );

    foreach (range(1, 105) as $i) {
        $flare->view()->recordRendering("view.{$i}");
    }

    expect($flare->view()->getSpans())->toHaveCount(100);
    expect($flare->view()->getSpans()[0]->name)->toBe('View - view.6');
    expect($flare->view()->getSpans()[99]->name)->toBe('View - view.105');
});

it('supports passing extra array as third positional argument to collectViews for backward compatibility', function () {
    /** @var FlareConfig|null $flareConfig */
    $flareConfig = null;

    $flare = setupFlare(function (FlareConfig $config) use (&$flareConfig) {
        $flareConfig = $config->collectViews(true, true, ['custom_key' => 'custom_val']);
    });

    expect($flareConfig->collects['views']['options'])
        ->toHaveKey('custom_key', 'custom_val');

    foreach (range(1, 105) as $i) {
        $flare->view()->recordRendering("view.{$i}");
    }

    expect($flare->view()->getSpans())->toHaveCount(100);
    expect($flare->view()->getSpans()[0]->name)->toBe('View - view.6');
    expect($flare->view()->getSpans()[99]->name)->toBe('View - view.105');
});

it('allows disabling view entry limit by explicitly setting max_items_with_errors to null', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectViews(maxItemsWithErrors: null),
    );

    foreach (range(1, 120) as $i) {
        $flare->view()->recordRendering("view.{$i}");
    }

    expect($flare->view()->getSpans())->toHaveCount(120);
});

it('allows disabling view entry limit by setting max_items_with_errors to null in positional extra array', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectViews(true, true, ['max_items_with_errors' => null]),
    );

    foreach (range(1, 120) as $i) {
        $flare->view()->recordRendering("view.{$i}");
    }

    expect($flare->view()->getSpans())->toHaveCount(120);
});
