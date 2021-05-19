<?php

namespace Spatie\FlareClient\Tests\Stacktrace;

use PHPUnit\Framework\Assert;
use Spatie\FlareClient\Tests\TestCase;
use Spatie\Snapshots\Drivers\YamlDriver;
use Symfony\Component\Yaml\Yaml;

class CodeSnippetDriver extends YamlDriver
{
    public function serialize($data): string
    {
        $yaml = parent::serialize($data);

        return TestCase::makePathsRelative($yaml);
    }

    public function match($expected, $actual)
    {
        if (is_array($actual)) {
            $actual = Yaml::dump($actual, PHP_INT_MAX);
        }

        $actual = TestCase::makePathsRelative($actual);

        Assert::assertEquals($expected, $actual);
    }
}
