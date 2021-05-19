<?php

namespace Spatie\FlareClient\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Spatie\FlareClient\Glows\Glow;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Tests\TestClasses\FakeTime;

class TestCase extends BaseTestCase
{
    public static function makePathsRelative(string $text): string
    {
        return str_replace(dirname(__DIR__, 1), '', $text);
    }

    public function useTime(string $dateTime, string $format = 'Y-m-d H:i:s')
    {
        $fakeTime = new FakeTime($dateTime, $format);

        Report::useTime($fakeTime);
        Glow::useTime($fakeTime);
    }

    public function getStubPath(string $stubName): string
    {
        return __DIR__."/stubs/{$stubName}";
    }
}
