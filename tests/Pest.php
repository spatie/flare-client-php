<?php

use Spatie\FlareClient\Report;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Tests\Mocks\FakeClient;
use Spatie\FlareClient\Tests\Mocks\FakeSender;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\SpanEventsRecorder;
use Spatie\FlareClient\Tracer;

uses()->beforeEach(function () {
    Container::instance()->reset();
    FakeSender::reset();
})->in(__DIR__);

function makePathsRelative(string $text): string
{
    return str_replace(dirname(__DIR__, 1), '', $text);
}

function useTime(string $dateTime, string $format = 'Y-m-d H:i:s')
{
    $fakeTime = new FakeTime($dateTime, $format);

    Report::useTime($fakeTime);
    GlowSpanEvent::useTime($fakeTime);
    Tracer::useTime($fakeTime);
    Span::useTime($fakeTime);
}

/**
 * @param ?Closure(FlareConfig):void $closure
 */
function setupFlare(
    ?Closure $closure = null,
    bool $sendReportsImmediately = true,
    bool $useFakeSender = true
): Flare {
    $config = new FlareConfig(
        apiToken: 'fake-api-key',
        sendReportsImmediately: $sendReportsImmediately,
    );

    if($useFakeSender){
        $config->sender = FakeSender::class;
    }

    if($closure){
        $closure($config);
    }

    return test()->flare = Flare::makeFromConfig($config);
}

function getStubPath(string $stubName): string
{
    return __DIR__."/stubs/{$stubName}";
}
