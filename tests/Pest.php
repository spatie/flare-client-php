<?php

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\FlareProvider;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeMemory;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;

uses()->beforeEach(function () {
    Container::instance()->reset();
    FakeSender::reset();
    FakeApi::reset();
    FakeTime::reset();
    FakeIds::reset();
    FakeMemory::reset();
})->in(__DIR__);

function makePathsRelative(string $text): string
{
    return str_replace(dirname(__DIR__, 1), '', $text);
}

/**
 * @param ?Closure(FlareConfig):void $closure
 */
function setupFlare(
    ?Closure $closure = null,
    bool $alwaysSampleTraces = false,
    bool $withoutApiKey = false,
    bool $isUsingSubtasks = false,
    bool $useFakeApi = true,
): Flare {
    $config = new FlareConfig(
        apiToken: $withoutApiKey ? null : 'fake-api-key',
        trace: true,
        log: true,
    );

    if ($useFakeApi) {
        $config->api = FakeApi::class;
    }

    if ($alwaysSampleTraces) {
        $config->alwaysSampleTraces();
    }

    if (FakeTime::isSetup()) {
        $config->time = FakeTime::class;
    }

    if (FakeIds::isSetup()) {
        $config->ids = FakeIds::class;
    }

    if (FakeMemory::isSetup()) {
        $config->memory = FakeMemory::class;
    }

    if ($closure) {
        $closure($config);
    }

    $container = Container::instance();

    $provider = new FlareProvider(
        $config,
        $container,
        isUsingSubtasksClosure: fn () => $isUsingSubtasks,
    );

    $provider->register();
    $provider->boot();

    return test()->flare = $container->get(Flare::class);
}

function getStubPath(string $stubName): string
{
    return __DIR__."/stubs/{$stubName}";
}
