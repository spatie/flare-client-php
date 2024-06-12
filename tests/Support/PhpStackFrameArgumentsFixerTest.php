<?php

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;

it('can enable stack trace arguments on a PHP level', function () {
    ini_set('zend.exception_ignore_args', 1);

    $report = Flare::make()
        ->withStackFrameArguments(true, forcePHPIniSetting: false)
        ->createReport(TraceArguments::create()->exception('string', new DateTime()))
        ->toArray();

    expect($report['stacktrace'][1]['arguments'])->toBeNull();

    $report = Flare::make()
        ->withStackFrameArguments(true, forcePHPIniSetting: true)
        ->createReport(TraceArguments::create()->exception('string', new DateTime()))
        ->toArray();

    expect($report['stacktrace'][1]['arguments'])
        ->toBeArray()
        ->toHaveCount(2);
});
