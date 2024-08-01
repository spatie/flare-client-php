<?php

namespace Spatie\FlareClient\Recorders;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Contracts\SpanEventsRecorder;
use Spatie\FlareClient\Contracts\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;

class NullRecorder implements SpansRecorder, SpanEventsRecorder
{
    public static self $instance;

    public static function type(): string|RecorderType
    {
        return RecorderType::Null;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }


    public function configure(array $config): void
    {

    }

    public function start(): void
    {

    }

    public function reset(): void
    {

    }

    public function getSpanEvents(): array
    {
        return [];
    }

    public function getSpans(): array
    {
        return [];
    }

    public function __call(string $name, array $arguments)
    {
        // Ignore recording calls
    }
}
