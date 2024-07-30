<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use PHPUnit\Framework\Assert;
use Spatie\Snapshots\Drivers\YamlDriver;
use Symfony\Component\Yaml\Yaml;

class ReportDriver extends YamlDriver
{
    public function serialize($data): string
    {
        $data = $this->removeTimeValues($data);
        $data = $this->emptyStacktrace($data);
        $data = $this->removePhpunitArguments($data);
        $data = $this->freezeLanguageVersion($data);
        $data = $this->removeUuid($data);

        $yaml = parent::serialize($data);

        return makePathsRelative($yaml);
    }

    public function match($expected, $actual)
    {
        $actual = $this->removeTimeValues($actual);
        $actual = $this->emptyStacktrace($actual);
        $actual = $this->removePhpunitArguments($actual);
        $actual = $this->freezeLanguageVersion($actual);
        $actual = $this->removeUuid($actual);


        if (is_array($actual)) {
            $actual = Yaml::dump($actual, PHP_INT_MAX);
        }

        $actual = makePathsRelative($actual);

        Assert::assertEquals($expected, $actual);
    }

    protected function removeTimeValues(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            if ($key === 'time' || $key === 'microtime') {
                $value = 1234;
            }
        });

        return $data;
    }

    protected function emptyStacktrace(array $data): array
    {
        $data['stacktrace'] = [];

        return $data;
    }

    protected function removePhpunitArguments(array $data): array
    {
        $data['context']['arguments'] = ['[phpunit arguments removed]'];

        return $data;
    }

    protected function freezeLanguageVersion(array $data): array
    {
        data_set($data, 'language_version', '7.3.2', true);
        data_set($data, 'context.env.php_version', '7.3.2', true);

        return $data;
    }

    protected function removeUuid(array $data): array
    {
        data_set($data, 'uuid', 'fake-uuid', true);

        return $data;
    }
}
