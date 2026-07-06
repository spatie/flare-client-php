<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Spans\SpanEvent;

class ConcreteSpanEventsRecorder extends SpanEventsRecorder
{
    public static function type(): string|RecorderType
    {
        return 'spans';
    }

    public function record(string $message, array $attributes = []): ?SpanEvent
    {
        return $this->spanEvent(
            name: "Span Event - {$message}",
            attributes: fn () => [
                'message' => $message,
                ...$attributes,
            ],
        );
    }

    protected function shouldTrimAttributes(): bool
    {
        return true;
    }
}
