<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;

class ConcreteSpansRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return 'spans';
    }

    public function pushSpan(string $name): ?Span
    {
        return $this->startSpan(name: $name);
    }

    public function popSpan(): ?Span
    {
        return $this->endSpan();
    }

    public function record(
        string $name,
        int $duration,
    ): ?Span {
        return $this->span(
            $name,
            duration: $duration,
        );
    }
}
