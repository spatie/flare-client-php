<?php

use Monolog\Level;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;

it('will add logs to an error report', function () {
    FakeTime::setup('2019-01-01 12:34:56');

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectLogsWithErrors());

    $flare->logger->record('Error message', Level::Error);

    $flare->report(new Exception('Test exception'));

    FakeApi::assertSent(reports: 1);

    FakeApi::lastReport()
        ->expectEventCount(1)
        ->expectEvent(0)
        ->expectStart(new DateTime('2019-01-01 12:34:56'))
        ->expectMissingEnd()
        ->expectType(SpanEventType::Log)
        ->expectAttribute('log.message', 'Error message')
        ->expectAttribute('log.level', 'error');
});

it('will only add logs higher than the minimal level', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectLogsWithErrors(
        minimalLevel: Level::Warning
    ));

    $flare->logger->record('Debug message', Level::Debug);
    $flare->logger->record('Info message', Level::Info);
    $flare->logger->record('Notice message', Level::Notice);
    $flare->logger->record('Warning message', Level::Warning);
    $flare->logger->record('Error message', Level::Error);
    $flare->logger->record('Critical message', Level::Critical);
    $flare->logger->record('Alert message', Level::Alert);
    $flare->logger->record('Emergency message', Level::Emergency);

    $flare->report(new Exception('Test exception'));

    FakeApi::assertSent(reports: 1);

    $report = FakeApi::lastReport()->expectEventCount(5);

    $report
        ->expectEvent(0)
        ->expectAttribute('log.message', 'Warning message')
        ->expectAttribute('log.level', 'warning');

    $report
        ->expectEvent(1)
        ->expectAttribute('log.message', 'Error message')
        ->expectAttribute('log.level', 'error');

    $report
        ->expectEvent(2)
        ->expectAttribute('log.message', 'Critical message')
        ->expectAttribute('log.level', 'critical');

    $report
        ->expectEvent(3)
        ->expectAttribute('log.message', 'Alert message')
        ->expectAttribute('log.level', 'alert');

    $report
        ->expectEvent(4)
        ->expectAttribute('log.message', 'Emergency message')
        ->expectAttribute('log.level', 'emergency');
});

it('will only add a max items of logs and keeps the latest ones', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectLogsWithErrors(
        maxItems: 3,
    ));

    $flare->logger->record('First error', Level::Error);
    $flare->logger->record('Second error', Level::Error);
    $flare->logger->record('Third error', Level::Error);
    $flare->logger->record('Fourth error', Level::Error);
    $flare->logger->record('Fifth error', Level::Error);

    $flare->report(new Exception('Test exception'));

    FakeApi::assertSent(reports: 1);

    $report = FakeApi::lastReport()->expectEventCount(3);

    $report
        ->expectEvent(0)
        ->expectAttribute('log.message', 'Third error');

    $report
        ->expectEvent(1)
        ->expectAttribute('log.message', 'Fourth error');

    $report
        ->expectEvent(2)
        ->expectAttribute('log.message', 'Fifth error');
});
