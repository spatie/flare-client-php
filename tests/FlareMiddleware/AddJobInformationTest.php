<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\FlareMiddleware\AddJobInformation;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;

beforeEach(function () {
    AddJobInformation::clearLatestJobInfo();
});

it('switches the entry point, attaches the failed job span, and propagates the tracking uuid onto the next report', function () {
    FakeIds::setup()->nextUuid('job-tracking-uuid');

    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(withErrors: false),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();
    $flare->job()->recordStart('App\\Jobs\\Send', 'App\\Jobs\\SendJob');
    $flare->job()->recordFailed(new Exception('Job failed'));
    $flare->tracer->endTrace();

    $flare->report(new Exception('Boom'));

    $report = FakeApi::lastReport()
        ->expectTrackingUuid('job-tracking-uuid')
        ->expectAttribute('flare.entry_point.type', 'queue')
        ->expectAttribute('flare.entry_point.value', 'App\\Jobs\\SendJob')
        ->expectAttribute('flare.entry_point.handler.identifier', 'App\\Jobs\\Send')
        ->expectAttribute('flare.entry_point.handler.name', 'App\\Jobs\\SendJob')
        ->expectAttribute('flare.entry_point.handler.type', 'php_job')
        ->expectEventCount(1, SpanType::Job);

    $report->expectEvent(SpanType::Job)
        ->expectType(SpanType::Job)
        ->expectAttribute('flare.entry_point.handler.identifier', 'App\\Jobs\\Send')
        ->expectAttribute('flare.entry_point.handler.name', 'App\\Jobs\\SendJob')
        ->expectAttribute('flare.entry_point.value', 'App\\Jobs\\SendJob');

    $jobSpan = FakeApi::lastTrace()
        ->expectSpanCount(1, SpanType::Job)
        ->expectSpan(SpanType::Job)
        ->expectName('Job - App\\Jobs\\Send')
        ->expectType(SpanType::Job)
        ->expectAttribute('flare.entry_point.type', 'queue')
        ->expectAttribute('flare.entry_point.value', 'App\\Jobs\\SendJob')
        ->expectAttribute('flare.entry_point.handler.identifier', 'App\\Jobs\\Send')
        ->expectAttribute('flare.entry_point.handler.name', 'App\\Jobs\\SendJob')
        ->expectAttribute('flare.entry_point.handler.type', 'php_job')
        ->expectSpanEventCount(1, SpanEventType::Exception);

    $jobSpan->expectSpanEvent(SpanEventType::Exception)
        ->expectName('Exception - '.Exception::class)
        ->expectType(SpanEventType::Exception)
        ->expectAttribute('exception.id', 'job-tracking-uuid')
        ->expectAttribute('exception.message', 'Job failed')
        ->expectAttribute('exception.type', Exception::class);

    expect(AddJobInformation::$usedTrackingUuid)->toBeNull();
    expect(AddJobInformation::$latestJob)->toBeNull();
});

it('is a no-op when no job has run before the report', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs());

    $flare->report(new Exception('Boom'));

    FakeApi::lastReport()->expectEventCount(0, SpanType::Job);

    expect(AddJobInformation::$usedTrackingUuid)->toBeNull();
    expect(AddJobInformation::$latestJob)->toBeNull();
});
