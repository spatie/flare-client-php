<?php

namespace Spatie\FlareClient\Recorders;

use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
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

    protected function configure(array $config): void
    {

    }

    public function boot(): void
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

    public function __call(string $name, array $arguments): void
    {
        // Ignore recording calls
    }
}
