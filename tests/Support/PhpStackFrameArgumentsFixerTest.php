<?php

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;

it('can enable stack trace arguments on a PHP level', function () {
    ini_set('zend.exception_ignore_args', 1);

    $report = Flare::make(
        FlareConfig::make('FAKE-API-KEY')->collectStackFrameArguments(forcePHPIniSetting: false)
    )
        ->report(TraceArguments::create()->exception('string', new DateTime()))
        ->toArray();

    expect($report['stacktrace'][1]['arguments'])->toBeNull();

    $report = Flare::make(
        FlareConfig::make('FAKE-API-KEY')->collectStackFrameArguments(forcePHPIniSetting: true)
    )
        ->report(TraceArguments::create()->exception('string', new DateTime()))
        ->toArray();

    expect($report['stacktrace'][1]['arguments'])
        ->toBeArray()
        ->toHaveCount(2);
});
