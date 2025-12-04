<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Shared\ExpectSpan;
use Spatie\FlareClient\Tests\Shared\ExpectSpanEvent;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use Spatie\FlareClient\Tests\Shared\ExpectTracer;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext;

it('can trace throwables', function () {
    FakeIds::setup()->nextUuid('fake-uuid');;

    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectErrorsWithTraces()->collectCommands()->trace()->alwaysSampleTraces()
    );

    $flare->tracer->startTrace();
    $flare->command()->recordStart('command', []);

    $flare->report(new ExceptionWithContext('We failed'));

    $flare->command()->recordEnd(1);
    $flare->tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpan(0)->expectSpanEvent(0)
        ->expectName('Exception - Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext')
        ->expectType(SpanEventType::Exception)
        ->expectAttribute('exception.message', 'We failed')
        ->expectAttribute('exception.type', 'Spatie\FlareClient\Tests\TestClasses\ExceptionWithContext')
        ->expectAttribute('exception.handled', null)
        ->expectAttribute('exception.id', 'fake-uuid');
});
