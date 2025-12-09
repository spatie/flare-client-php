<?php

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Support\PhpStackFrameArgumentsFixer;
use Spatie\FlareClient\Tests\TestClasses\TraceArguments;

it('can enable stack trace arguments on a PHP level', function () {
    ini_set('zend.exception_ignore_args', 1);

    $flare = setupFlare(fn(FlareConfig $config) => $config->collectStackFrameArguments(forcePHPIniSetting: false));

    $report = $flare->report(TraceArguments::create()->exception('string', new DateTime()))->toArray();

    expect($report['stacktrace'][1]['arguments'])->toBeNull();

    (new PhpStackFrameArgumentsFixer())->enable();

    $report = $flare->report(TraceArguments::create()->exception('string', new DateTime()))->toArray();

    expect($report['stacktrace'][1]['arguments'])
        ->toBeArray()
        ->toHaveCount(2);
});

it('can enables stack trace arguments on a PHP level by default', function () {
    ini_set('zend.exception_ignore_args', 1);

    $flare = setupFlare(fn(FlareConfig $config) => $config->collectStackFrameArguments());

    $report = $flare->report(TraceArguments::create()->exception('string', new DateTime()))->toArray();

    expect($report['stacktrace'][1]['arguments'])
        ->toBeArray()
        ->toHaveCount(2);
});

