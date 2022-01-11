<?php

use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;

uses()->beforeEach(function () {
    Report::$fakeTrackingUuid = 'fake-uuid';
})->in(__DIR__);

function makePathsRelative(string $text): string
{
    return str_replace(dirname(__DIR__, 1), '', $text);
}

function useTime(string $dateTime, string $format = 'Y-m-d H:i:s')
{
    $fakeTime = new FakeTime($dateTime, $format);

    Report::useTime($fakeTime);
    Glow::useTime($fakeTime);
}

function getStubPath(string $stubName): string
{
    return __DIR__."/stubs/{$stubName}";
}
